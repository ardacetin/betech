<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Setting;

class LdapAuthenticator
{
    public function __construct(
        private readonly Setting $settingModel
    ) {
    }

    /**
     * @return array{name: string, email: string, external_id: string, department: string|null}|null
     */
    public function authenticate(string $username, string $password): ?array
    {
        if (!extension_loaded('ldap') || trim($username) === '' || $password === '') {
            return null;
        }

        $config = $this->settingModel->getLdapConfig();

        if ($config['host'] === '' || $config['base_dn'] === '') {
            return null;
        }

        $formattedUsername = $this->formatBindUsername($username, $config);
        $connection = $this->connect($config);

        if ($connection === null) {
            return null;
        }

        try {
            $hasServiceAccount = $config['bind_dn'] !== '' && $config['bind_password'] !== '';

            if ($hasServiceAccount) {
                if (!$this->bindServiceAccount($connection, $config)) {
                    return null;
                }

                $entry = $this->findUserEntry($connection, $config['base_dn'], $username, $formattedUsername);

                if ($entry === null) {
                    return null;
                }

                $userDn = (string) ($entry['dn'] ?? '');

                if ($userDn === '') {
                    return null;
                }

                if (!$this->bindUserCredentials($connection, $userDn, $username, $formattedUsername, $password)) {
                    return null;
                }

                return $this->mapEntry($entry, $username);
            }

            if (!$this->bindUserCredentials($connection, '', $username, $formattedUsername, $password)) {
                return null;
            }

            $entry = $this->findUserEntry($connection, $config['base_dn'], $username, $formattedUsername);

            return $entry === null ? null : $this->mapEntry($entry, $username);
        } finally {
            @ldap_unbind($connection);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function formatBindUsername(string $username, array $config): string
    {
        $trimmed = trim($username);

        if ($trimmed === '') {
            return $trimmed;
        }

        if (str_contains($trimmed, '@') || str_contains($trimmed, '\\')) {
            return $trimmed;
        }

        $suffix = trim((string) ($config['account_suffix'] ?? ''));

        if ($suffix === '') {
            return $trimmed;
        }

        if (!str_starts_with($suffix, '@')) {
            $suffix = '@' . ltrim($suffix, '@');
        }

        return $trimmed . $suffix;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function connect(array $config): \LDAP\Connection|false|null
    {
        $connection = @ldap_connect($config['host'], (int) $config['port'] > 0 ? (int) $config['port'] : 389);

        if ($connection === false) {
            return null;
        }

        @ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        @ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, 5);

        if ($config['use_tls'] && @ldap_start_tls($connection) !== true) {
            return null;
        }

        return $connection;
    }

    /**
     * @param \LDAP\Connection $connection
     * @param array<string, mixed> $config
     */
    private function bindServiceAccount(\LDAP\Connection $connection, array $config): bool
    {
        if ($config['bind_dn'] !== '' && $config['bind_password'] !== '') {
            return @ldap_bind($connection, $config['bind_dn'], $config['bind_password']) === true;
        }

        return @ldap_bind($connection) === true;
    }

    private function bindUserCredentials(
        \LDAP\Connection $connection,
        string $userDn,
        string $username,
        string $formattedUsername,
        string $password
    ): bool {
        $candidates = [];

        if ($userDn !== '') {
            $candidates[] = $userDn;
        }

        if ($formattedUsername !== '') {
            $candidates[] = $formattedUsername;
        }

        if ($username !== '' && $username !== $formattedUsername) {
            $candidates[] = $username;
        }

        foreach (array_values(array_unique($candidates)) as $bindIdentity) {
            if (@ldap_bind($connection, $bindIdentity, $password) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findUserEntry(
        \LDAP\Connection $connection,
        string $baseDn,
        string $username,
        string $formattedUsername
    ): ?array {
        $filter = $this->buildIdentityFilter($username, $formattedUsername);
        $attributes = ['cn', 'mail', 'department', 'uid', 'displayName', 'sAMAccountName', 'userPrincipalName'];
        $search = @ldap_search($connection, $baseDn, $filter, $attributes, 0, 1);

        if ($search === false) {
            return null;
        }

        $entries = ldap_get_entries($connection, $search);

        if (!is_array($entries) || ($entries['count'] ?? 0) < 1) {
            return null;
        }

        return $entries[0];
    }

    private function buildIdentityFilter(string $username, string $formattedUsername): string
    {
        $trimmed = trim($username);
        $escaped = $this->escapeFilterValue($trimmed);

        if (str_contains($trimmed, '@')) {
            return sprintf(
                '(&(objectClass=person)(|(mail=%1$s)(userPrincipalName=%1$s)))',
                $escaped
            );
        }

        if (str_contains($trimmed, '\\')) {
            $parts = explode('\\', $trimmed, 2);
            $samAccountName = $this->escapeFilterValue(trim($parts[1] ?? $trimmed));

            return sprintf('(&(objectClass=person)(sAMAccountName=%s))', $samAccountName);
        }

        $clauses = [
            sprintf('(uid=%s)', $escaped),
            sprintf('(sAMAccountName=%s)', $escaped),
            sprintf('(cn=%s)', $escaped),
        ];

        if ($formattedUsername !== $trimmed && str_contains($formattedUsername, '@')) {
            $formattedEscaped = $this->escapeFilterValue($formattedUsername);
            $clauses[] = sprintf('(userPrincipalName=%s)', $formattedEscaped);
            $clauses[] = sprintf('(mail=%s)', $formattedEscaped);
        }

        return sprintf('(&(objectClass=person)(|%s))', implode('', $clauses));
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
     * @return array{name: string, email: string, external_id: string, department: string|null}
     */
    private function mapEntry(array $entry, string $fallbackUsername): array
    {
        $email = $this->firstAttribute($entry, 'mail')
            ?: $this->firstAttribute($entry, 'userPrincipalName')
            ?: '';
        $uid = $this->firstAttribute($entry, 'uid')
            ?: $this->firstAttribute($entry, 'sAMAccountName')
            ?: ($email !== '' ? strtolower($email) : trim($fallbackUsername));

        $name = $this->firstAttribute($entry, 'displayName')
            ?: $this->firstAttribute($entry, 'cn')
            ?: $uid;

        $department = $this->firstAttribute($entry, 'department');

        if ($email === '') {
            $email = sprintf('%s@ldap.local', preg_replace('/[^a-z0-9._-]+/i', '-', $uid) ?: 'user');
        }

        return [
            'name' => $name,
            'email' => $email,
            'external_id' => $uid,
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
