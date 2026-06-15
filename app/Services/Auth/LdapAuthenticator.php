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

        $connection = $this->connect($config);

        if ($connection === null) {
            return null;
        }

        try {
            if (!$this->bindServiceAccount($connection, $config)) {
                return null;
            }

            $entry = $this->findUserEntry($connection, $config['base_dn'], $username);

            if ($entry === null) {
                return null;
            }

            $userDn = (string) ($entry['dn'] ?? '');

            if ($userDn === '') {
                return null;
            }

            if (@ldap_bind($connection, $userDn, $password) !== true) {
                return null;
            }

            return $this->mapEntry($entry, $username);
        } finally {
            @ldap_unbind($connection);
        }
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

    /**
     * @return array<string, mixed>|null
     */
    private function findUserEntry(\LDAP\Connection $connection, string $baseDn, string $username): ?array
    {
        $filter = $this->buildIdentityFilter($username);
        $attributes = ['cn', 'mail', 'department', 'uid', 'displayName', 'sAMAccountName'];
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

    private function buildIdentityFilter(string $username): string
    {
        $trimmed = trim($username);
        $escaped = $this->escapeFilterValue($trimmed);

        if (str_contains($trimmed, '@')) {
            return sprintf('(&(objectClass=person)(mail=%s))', $escaped);
        }

        return sprintf(
            '(&(objectClass=person)(|(uid=%1$s)(sAMAccountName=%1$s)(cn=%1$s)(mail=%1$s)))',
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
     * @return array{name: string, email: string, external_id: string, department: string|null}
     */
    private function mapEntry(array $entry, string $fallbackUsername): array
    {
        $email = $this->firstAttribute($entry, 'mail') ?? '';
        $uid = $this->firstAttribute($entry, 'uid')
            ?: $this->firstAttribute($entry, 'sAMAccountName')
            ?: ($email !== '' ? $email : trim($fallbackUsername));

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
