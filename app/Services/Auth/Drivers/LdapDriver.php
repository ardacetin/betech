<?php

declare(strict_types=1);

namespace App\Services\Auth\Drivers;

use App\Exceptions\LdapSyncException;
use App\Models\Setting;
use App\Services\Auth\UserIntegrationInterface;

class LdapDriver implements UserIntegrationInterface
{
    private const SEARCH_LIMIT = 20;
    private const SYNC_PAGE_SIZE = 500;

    /**
     * Active AD users only (excludes disabled accounts and computer objects).
     */
    private const ACTIVE_PERSONNEL_FILTER = '(&(objectClass=user)(objectCategory=person)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))';

    private const FALLBACK_PERSONNEL_FILTER = '(&(objectClass=person)(!(objectClass=computer)))';

    /** @var list<string> */
    private const PERSONNEL_ATTRIBUTES = [
        'cn',
        'mail',
        'department',
        'title',
        'jobTitle',
        'uid',
        'displayName',
        'sAMAccountName',
        'userPrincipalName',
    ];

    public function __construct(
        private readonly Setting $settingModel
    ) {
    }

    public function searchUsers(string $query): array
    {
        if (!extension_loaded('ldap')) {
            return [];
        }

        $config = $this->settingModel->getLdapConfig();

        if ($config['host'] === '' || $config['base_dn'] === '') {
            return [];
        }

        $connection = $this->connect($config);

        if ($connection === null) {
            return [];
        }

        try {
            if (!$this->bind($connection, $config)) {
                return [];
            }

            $filter = $this->buildSearchFilter($query);
            $search = @ldap_search(
                $connection,
                $config['base_dn'],
                $filter,
                self::PERSONNEL_ATTRIBUTES,
                0,
                self::SEARCH_LIMIT
            );

            if ($search === false) {
                return [];
            }

            $entries = ldap_get_entries($connection, $search);

            if (!is_array($entries) || ($entries['count'] ?? 0) === 0) {
                return [];
            }

            $users = [];

            for ($index = 0; $index < (int) $entries['count']; $index++) {
                $mapped = $this->mapEntry($entries[$index]);

                if ($mapped !== null) {
                    $users[] = $mapped;
                }
            }

            return $users;
        } finally {
            @ldap_unbind($connection);
        }
    }

    public function listAllUsers(): array
    {
        if (!extension_loaded('ldap')) {
            return [];
        }

        $config = $this->settingModel->getLdapConfig();

        if ($config['host'] === '' || $config['base_dn'] === '') {
            return [];
        }

        $connection = $this->connect($config);

        if ($connection === null) {
            return [];
        }

        try {
            if (!$this->bind($connection, $config)) {
                return [];
            }

            return $this->fetchAllEntries(
                $connection,
                (string) $config['base_dn'],
                '(&(objectClass=person)(!(objectClass=computer)))',
                false
            );
        } finally {
            @ldap_unbind($connection);
        }
    }

    /**
     * Pull active personnel from LDAP for synchronization. Throws on connection, bind, or query failures.
     *
     * @return list<array{id: string, external_id: string, name: string, email: string, department: string|null, title: string|null}>
     */
    public function listActivePersonnel(): array
    {
        try {
            if (!extension_loaded('ldap')) {
                throw new LdapSyncException(__('personnel_ldap_sync_extension_missing'));
            }

            $config = $this->settingModel->getLdapConfig();

            if ($config['host'] === '' || $config['base_dn'] === '') {
                throw new LdapSyncException(__('personnel_ldap_sync_not_configured'));
            }

            $connection = $this->connectStrict($config);

            try {
                $this->bindStrict($connection, $config);

                $users = $this->fetchAllEntries(
                    $connection,
                    (string) $config['base_dn'],
                    self::ACTIVE_PERSONNEL_FILTER,
                    false
                );

                // Some OpenLDAP / non-AD directories reject AD-specific filters; fall back.
                if ($users === []) {
                    $users = $this->fetchAllEntries(
                        $connection,
                        (string) $config['base_dn'],
                        self::FALLBACK_PERSONNEL_FILTER,
                        true
                    );
                }

                return $users;
            } finally {
                @ldap_unbind($connection);
            }
        } catch (LdapSyncException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new LdapSyncException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @return list<array{id: string, external_id: string, name: string, email: string, department: string|null, title?: string|null}>
     */
    private function fetchAllEntries(
        \LDAP\Connection $connection,
        string $baseDn,
        string $filter,
        bool $strict
    ): array {
        try {
            $users = [];
            $seenExternalIds = [];
            $cookie = '';
            // RFC 2696 / LDAP_CONTROL_PAGEDRESULTS keeps each LDAP response bounded (SYNC_PAGE_SIZE).
            $supportsPagedResults = defined('LDAP_CONTROL_PAGEDRESULTS');

            do {
                $controls = [];

                if ($supportsPagedResults) {
                    $controls = [[
                        'oid' => LDAP_CONTROL_PAGEDRESULTS,
                        'iscritical' => true,
                        'value' => [
                            'size' => self::SYNC_PAGE_SIZE,
                            'cookie' => $cookie,
                        ],
                    ]];
                }

                $search = @ldap_search(
                    $connection,
                    $baseDn,
                    $filter,
                    self::PERSONNEL_ATTRIBUTES,
                    0,
                    $supportsPagedResults ? 0 : self::SYNC_PAGE_SIZE,
                    0,
                    defined('LDAP_DEREF_NEVER') ? LDAP_DEREF_NEVER : 0,
                    $controls
                );

                if ($search === false) {
                    if ($strict) {
                        throw new LdapSyncException(
                            __('personnel_ldap_sync_query_failed') . $this->ldapErrorMessage($connection)
                        );
                    }

                    break;
                }

                $cookie = '';

                if ($supportsPagedResults) {
                    $errorCode = 0;
                    $errorMessage = '';
                    $matchedDn = '';
                    $referrals = [];
                    $responseControls = [];
                    @ldap_parse_result(
                        $connection,
                        $search,
                        $errorCode,
                        $matchedDn,
                        $errorMessage,
                        $referrals,
                        $responseControls
                    );

                    $cookie = $this->extractPagedResultsCookie($responseControls);
                }

                $entries = ldap_get_entries($connection, $search);

                if (!is_array($entries) || ($entries['count'] ?? 0) === 0) {
                    break;
                }

                for ($index = 0; $index < (int) $entries['count']; $index++) {
                    $mapped = $this->mapEntry($entries[$index]);

                    if ($mapped === null) {
                        continue;
                    }

                    $externalKey = strtolower($mapped['external_id']);

                    if (isset($seenExternalIds[$externalKey])) {
                        continue;
                    }

                    $seenExternalIds[$externalKey] = true;
                    $users[] = $mapped;
                }

                unset($entries, $search);

                if (!$supportsPagedResults) {
                    break;
                }
            } while ($cookie !== '');

            return $users;
        } catch (LdapSyncException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new LdapSyncException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @param array<int|string, mixed> $controls
     */
    private function extractPagedResultsCookie(array $controls): string
    {
        if ($controls === []) {
            return '';
        }

        $pagedOid = defined('LDAP_CONTROL_PAGEDRESULTS') ? (string) LDAP_CONTROL_PAGEDRESULTS : '1.2.840.113556.1.4.319';
        $candidates = [];

        if (isset($controls[$pagedOid]) && is_array($controls[$pagedOid])) {
            $candidates[] = $controls[$pagedOid];
        }

        foreach ($controls as $control) {
            if (is_array($control)) {
                $candidates[] = $control;
            }
        }

        foreach ($candidates as $control) {
            $oid = (string) ($control['oid'] ?? '');

            if ($oid !== '' && $oid !== $pagedOid) {
                continue;
            }

            if (!isset($control['value'])) {
                continue;
            }

            $value = $control['value'];

            if (is_string($value) && $value !== '') {
                return $value;
            }

            if (!is_array($value)) {
                continue;
            }

            $cookie = $value['cookie'] ?? null;

            // Paged-result cookies are opaque binary strings; keep empty-string as "no more pages".
            if (is_string($cookie)) {
                return $cookie;
            }
        }

        return '';
    }

    public function getUserById(string $id): ?array
    {
        if (!extension_loaded('ldap') || trim($id) === '') {
            return null;
        }

        $config = $this->settingModel->getLdapConfig();

        if ($config['host'] === '' || $config['base_dn'] === '') {
            return null;
        }

        $connection = $this->connect($config);

        if ($connection === null) {
            return null;
        }

        try {
            if (!$this->bind($connection, $config)) {
                return null;
            }

            $filter = $this->buildIdentityFilter($id);
            $search = @ldap_search(
                $connection,
                $config['base_dn'],
                $filter,
                self::PERSONNEL_ATTRIBUTES,
                0,
                1
            );

            if ($search === false) {
                return null;
            }

            $entries = ldap_get_entries($connection, $search);

            if (!is_array($entries) || ($entries['count'] ?? 0) < 1) {
                return null;
            }

            return $this->mapEntry($entries[0]);
        } finally {
            @ldap_unbind($connection);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function connect(array $config): \LDAP\Connection|false|null
    {
        try {
            $host = $config['host'];
            $port = (int) $config['port'];

            $connection = @ldap_connect($host, $port > 0 ? $port : 389);

            if ($connection === false) {
                return null;
            }

            @ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
            @ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
            @ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, 30);

            if ($config['use_tls']) {
                if (@ldap_start_tls($connection) !== true) {
                    return null;
                }
            }

            return $connection;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function connectStrict(array $config): \LDAP\Connection
    {
        try {
            $connection = $this->connect($config);

            if ($connection === null || $connection === false) {
                throw new LdapSyncException(
                    __('personnel_ldap_sync_connection_failed') . $this->lastPhpErrorMessage()
                );
            }

            return $connection;
        } catch (LdapSyncException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new LdapSyncException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function bindStrict(\LDAP\Connection $connection, array $config): void
    {
        try {
            if (!$this->bind($connection, $config)) {
                throw new LdapSyncException(
                    __('personnel_ldap_sync_bind_failed') . $this->ldapErrorMessage($connection)
                );
            }
        } catch (LdapSyncException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new LdapSyncException($exception->getMessage(), 0, $exception);
        }
    }

    private function ldapErrorMessage(\LDAP\Connection $connection): string
    {
        $error = @ldap_error($connection);

        if (is_string($error) && $error !== '') {
            return ' ' . $error;
        }

        return $this->lastPhpErrorMessage();
    }

    private function lastPhpErrorMessage(): string
    {
        $lastError = error_get_last();

        if (!is_array($lastError)) {
            return '';
        }

        $message = trim((string) ($lastError['message'] ?? ''));

        return $message !== '' ? ' ' . $message : '';
    }

    /**
     * @param \LDAP\Connection $connection
     * @param array<string, mixed> $config
     */
    private function bind(\LDAP\Connection $connection, array $config): bool
    {
        try {
            if ($config['bind_dn'] !== '' && $config['bind_password'] !== '') {
                return @ldap_bind($connection, $config['bind_dn'], $config['bind_password']) === true;
            }

            return @ldap_bind($connection) === true;
        } catch (\Exception) {
            return false;
        }
    }

    private function buildSearchFilter(string $query): string
    {
        $trimmedQuery = trim($query);

        if ($trimmedQuery === '') {
            return '(&(objectClass=person)(!(objectClass=computer)))';
        }

        $escaped = $this->escapeFilterValue($trimmedQuery);

        return sprintf(
            '(&(objectClass=person)(!(objectClass=computer))(|(cn=*%1$s*)(mail=*%1$s*)(uid=*%1$s*)(department=*%1$s*)(displayName=*%1$s*)(sAMAccountName=*%1$s*)))',
            $escaped
        );
    }

    private function buildIdentityFilter(string $id): string
    {
        $trimmedId = trim($id);
        $escaped = $this->escapeFilterValue($trimmedId);

        if (str_contains($trimmedId, '@')) {
            return sprintf('(&(objectClass=person)(mail=%s))', $escaped);
        }

        return sprintf(
            '(&(objectClass=person)(|(uid=%1$s)(sAMAccountName=%1$s)(cn=%1$s)))',
            $escaped
        );
    }

    private function escapeFilterValue(string $value): string
    {
        if (function_exists('ldap_escape')) {
            return ldap_escape($value, '', LDAP_ESCAPE_FILTER);
        }

        return addcslashes($value, '\\*()\\' . "\x00");
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array{id: string, external_id: string, name: string, email: string, department: string|null, title: string|null}|null
     */
    private function mapEntry(array $entry): ?array
    {
        $email = $this->firstAttribute($entry, 'mail')
            ?: $this->firstAttribute($entry, 'userPrincipalName');
        $uid = $this->firstAttribute($entry, 'sAMAccountName')
            ?: $this->firstAttribute($entry, 'uid')
            ?: ($email !== null && $email !== '' ? strtolower($email) : null);

        if ($uid === null || $uid === '') {
            return null;
        }

        // Prefer local-part of UPN when mail is missing but keep a usable identity.
        if (($email === null || $email === '') && str_contains($uid, '@')) {
            $email = $uid;
        }

        $name = $this->firstAttribute($entry, 'displayName')
            ?: $this->firstAttribute($entry, 'cn')
            ?: $uid;

        $department = $this->firstAttribute($entry, 'department');
        $title = $this->firstAttribute($entry, 'title')
            ?: $this->firstAttribute($entry, 'jobTitle');

        return [
            'id' => $uid,
            'external_id' => $uid,
            'name' => $name,
            'email' => $email ?? '',
            'department' => $department,
            'title' => $title,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function firstAttribute(array $entry, string $attribute): ?string
    {
        // ldap_get_entries() lowercases attribute names.
        $key = strtolower($attribute);

        if (!array_key_exists($key, $entry) && array_key_exists($attribute, $entry)) {
            $key = $attribute;
        }

        if (!array_key_exists($key, $entry)) {
            return null;
        }

        $value = $entry[$key];

        if (is_array($value)) {
            return isset($value[0]) && $value[0] !== '' ? (string) $value[0] : null;
        }

        return $value !== '' ? (string) $value : null;
    }
}
