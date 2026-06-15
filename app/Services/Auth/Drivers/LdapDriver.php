<?php

declare(strict_types=1);

namespace App\Services\Auth\Drivers;

use App\Models\Setting;
use App\Services\Auth\UserIntegrationInterface;

class LdapDriver implements UserIntegrationInterface
{
    private const SEARCH_LIMIT = 20;
    private const SYNC_PAGE_SIZE = 500;

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
            $attributes = ['cn', 'mail', 'department', 'uid', 'displayName', 'sAMAccountName'];
            $search = @ldap_search(
                $connection,
                $config['base_dn'],
                $filter,
                $attributes,
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

            return $this->fetchAllEntries($connection, (string) $config['base_dn']);
        } finally {
            @ldap_unbind($connection);
        }
    }

    /**
     * @return list<array{id: string, external_id: string, name: string, email: string, department: string|null}>
     */
    private function fetchAllEntries(\LDAP\Connection $connection, string $baseDn): array
    {
        $filter = '(&(objectClass=person)(!(objectClass=computer)))';
        $attributes = ['cn', 'mail', 'department', 'uid', 'displayName', 'sAMAccountName'];
        $users = [];
        $cookie = '';
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
                $attributes,
                0,
                $supportsPagedResults ? 0 : self::SYNC_PAGE_SIZE,
                0,
                LDAP_DEREF_NEVER,
                $controls
            );

            if ($search === false) {
                break;
            }

            if ($supportsPagedResults) {
                $errorCode = 0;
                $errorMessage = '';
                $matchedDn = '';
                $referrals = [];
                @ldap_parse_result(
                    $connection,
                    $search,
                    $errorCode,
                    $matchedDn,
                    $errorMessage,
                    $referrals,
                    $controls
                );

                $cookie = (string) ($controls[0]['value']['cookie'] ?? '');
            } else {
                $cookie = '';
            }

            $entries = ldap_get_entries($connection, $search);

            if (!is_array($entries) || ($entries['count'] ?? 0) === 0) {
                break;
            }

            for ($index = 0; $index < (int) $entries['count']; $index++) {
                $mapped = $this->mapEntry($entries[$index]);

                if ($mapped !== null) {
                    $users[] = $mapped;
                }
            }

            if (!$supportsPagedResults) {
                break;
            }
        } while ($cookie !== '');

        return $users;
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
            $attributes = ['cn', 'mail', 'department', 'uid', 'displayName', 'sAMAccountName'];
            $search = @ldap_search(
                $connection,
                $config['base_dn'],
                $filter,
                $attributes,
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
        $host = $config['host'];
        $port = (int) $config['port'];

        $connection = @ldap_connect($host, $port > 0 ? $port : 389);

        if ($connection === false) {
            return null;
        }

        @ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        @ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, 5);

        if ($config['use_tls']) {
            if (@ldap_start_tls($connection) !== true) {
                return null;
            }
        }

        return $connection;
    }

    /**
     * @param \LDAP\Connection $connection
     * @param array<string, mixed> $config
     */
    private function bind(\LDAP\Connection $connection, array $config): bool
    {
        if ($config['bind_dn'] !== '' && $config['bind_password'] !== '') {
            return @ldap_bind($connection, $config['bind_dn'], $config['bind_password']) === true;
        }

        return @ldap_bind($connection) === true;
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
     * @return array{id: string, external_id: string, name: string, email: string, department: string|null}|null
     */
    private function mapEntry(array $entry): ?array
    {
        $email = $this->firstAttribute($entry, 'mail');
        $uid = $this->firstAttribute($entry, 'uid')
            ?: $this->firstAttribute($entry, 'sAMAccountName')
            ?: $email;

        if ($uid === null || $uid === '') {
            return null;
        }

        $name = $this->firstAttribute($entry, 'displayName')
            ?: $this->firstAttribute($entry, 'cn')
            ?: $uid;

        $department = $this->firstAttribute($entry, 'department');

        return [
            'id' => $uid,
            'external_id' => $uid,
            'name' => $name,
            'email' => $email ?? '',
            'department' => $department,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function firstAttribute(array $entry, string $attribute): ?string
    {
        if (!isset($entry[$attribute])) {
            return null;
        }

        $value = $entry[$attribute];

        if (is_array($value)) {
            return isset($value[0]) && $value[0] !== '' ? (string) $value[0] : null;
        }

        return $value !== '' ? (string) $value : null;
    }
}
