<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use JsonException;
use Medoo\Medoo;

class Setting
{
    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->db()->get('settings', 'value', [
            'key' => $key,
        ]);

        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    /**
     * @return mixed
     */
    public function getJson(string $key, mixed $default = []): mixed
    {
        $rawValue = $this->get($key);

        if ($rawValue === null) {
            return $default;
        }

        try {
            $decoded = json_decode($rawValue, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $default;
        }

        return $decoded;
    }

    public function set(string $key, string $value): void
    {
        $exists = $this->db()->has('settings', ['key' => $key]);

        if ($exists) {
            $this->db()->update('settings', [
                'value' => $value,
            ], [
                'key' => $key,
            ]);

            return;
        }

        $this->db()->insert('settings', [
            'key' => $key,
            'value' => $value,
        ]);
    }

    /**
     * @param mixed $value
     */
    public function setJson(string $key, mixed $value): void
    {
        $this->set($key, json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array{
     *     active_auth_driver: string,
     *     zimmet_template: string,
     *     custom_fields: list<array<string, mixed>>,
     *     ldap_config: array<string, mixed>,
     *     google_config: array<string, mixed>
     * }
     */
    public function getAdminBundle(): array
    {
        $customFields = $this->getJson('custom_fields', []);

        return [
            'active_auth_driver' => $this->get('active_auth_driver', 'local') ?? 'local',
            'zimmet_template' => $this->get('zimmet_template', '') ?? '',
            'custom_fields' => is_array($customFields) ? $customFields : [],
            'ldap_config' => $this->getLdapConfigForAdmin(),
            'google_config' => $this->getGoogleConfigForAdmin(),
        ];
    }

    /**
     * @return array{
     *     host: string,
     *     port: int,
     *     base_dn: string,
     *     bind_dn: string,
     *     bind_password: string,
     *     use_tls: bool
     * }
     */
    public function getLdapConfig(): array
    {
        return [
            'host' => trim($this->get('ldap_host', '') ?? ''),
            'port' => max(1, (int) ($this->get('ldap_port', '389') ?? '389')),
            'base_dn' => trim($this->get('ldap_base_dn', '') ?? ''),
            'bind_dn' => trim($this->get('ldap_bind_dn', '') ?? ''),
            'bind_password' => $this->get('ldap_bind_password', '') ?? '',
            'use_tls' => $this->toBool($this->get('ldap_use_tls', '0')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getLdapConfigForAdmin(): array
    {
        $config = $this->getLdapConfig();

        return [
            'host' => $config['host'],
            'port' => (string) $config['port'],
            'base_dn' => $config['base_dn'],
            'bind_dn' => $config['bind_dn'],
            'bind_password' => '',
            'bind_password_configured' => $this->hasSecret('ldap_bind_password'),
            'use_tls' => $config['use_tls'],
        ];
    }

    /**
     * @return array{
     *     domain: string,
     *     admin_email: string,
     *     auth_mode: string,
     *     service_account: array<string, mixed>|null,
     *     oauth_token: array<string, mixed>|null
     * }
     */
    public function getGoogleConfig(): array
    {
        $serviceAccount = $this->decodeJsonSetting('google_service_account_json');
        $oauthToken = $this->decodeJsonSetting('google_oauth_token_json');
        $authMode = strtolower(trim($this->get('google_auth_mode', 'service_account') ?? 'service_account'));

        if (!in_array($authMode, ['service_account', 'oauth'], true)) {
            $authMode = 'service_account';
        }

        return [
            'domain' => trim($this->get('google_domain', '') ?? ''),
            'admin_email' => trim($this->get('google_admin_email', '') ?? ''),
            'auth_mode' => $authMode,
            'service_account' => $serviceAccount,
            'oauth_token' => $oauthToken,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getGoogleConfigForAdmin(): array
    {
        $config = $this->getGoogleConfig();

        return [
            'domain' => $config['domain'],
            'admin_email' => $config['admin_email'],
            'auth_mode' => $config['auth_mode'],
            'service_account_json' => '',
            'service_account_configured' => $this->hasSecret('google_service_account_json'),
            'oauth_token_json' => '',
            'oauth_token_configured' => $this->hasSecret('google_oauth_token_json'),
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public function saveLdapConfig(array $config): void
    {
        $this->set('ldap_host', trim((string) ($config['host'] ?? '')));
        $this->set('ldap_port', (string) max(1, (int) ($config['port'] ?? 389)));
        $this->set('ldap_base_dn', trim((string) ($config['base_dn'] ?? '')));
        $this->set('ldap_bind_dn', trim((string) ($config['bind_dn'] ?? '')));
        $this->set('ldap_use_tls', $this->toBool($config['use_tls'] ?? false) ? '1' : '0');

        $password = trim((string) ($config['bind_password'] ?? ''));

        if ($password !== '') {
            $this->set('ldap_bind_password', $password);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function saveGoogleConfig(array $config): void
    {
        $authMode = strtolower(trim((string) ($config['auth_mode'] ?? 'service_account')));

        if (!in_array($authMode, ['service_account', 'oauth'], true)) {
            $authMode = 'service_account';
        }

        $this->set('google_domain', trim((string) ($config['domain'] ?? '')));
        $this->set('google_admin_email', trim((string) ($config['admin_email'] ?? '')));
        $this->set('google_auth_mode', $authMode);

        $serviceAccountJson = trim((string) ($config['service_account_json'] ?? ''));

        if ($serviceAccountJson !== '') {
            $this->set('google_service_account_json', $serviceAccountJson);
        }

        $oauthTokenJson = trim((string) ($config['oauth_token_json'] ?? ''));

        if ($oauthTokenJson !== '') {
            $this->set('google_oauth_token_json', $oauthTokenJson);
        }
    }

    private function hasSecret(string $key): bool
    {
        $value = $this->get($key);

        return $value !== null && trim($value) !== '';
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonSetting(string $key): ?array
    {
        $rawValue = $this->get($key);

        if ($rawValue === null || trim($rawValue) === '') {
            return null;
        }

        try {
            $decoded = json_decode($rawValue, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
