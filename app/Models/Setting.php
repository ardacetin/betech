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
     *     google_config: array<string, mixed>,
     *     login_config: array<string, mixed>
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
            'login_config' => $this->getLoginConfigForAdmin(),
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

    /**
     * @return array{
     *     local: bool,
     *     ldap: bool,
     *     google: bool,
     *     microsoft: bool
     * }
     */
    public function getLoginProviders(): array
    {
        return [
            'local' => $this->toBool($this->get('login_local_enabled', '1')),
            'ldap' => $this->toBool($this->get('login_ldap_enabled', '0')),
            'google' => $this->toBool($this->get('login_google_enabled', '0')),
            'microsoft' => $this->toBool($this->get('login_microsoft_enabled', '0')),
        ];
    }

    /**
     * @return array{
     *     client_id: string,
     *     client_secret: string,
     *     domain: string
     * }
     */
    public function getGoogleSsoConfig(): array
    {
        return [
            'client_id' => trim($this->get('google_sso_client_id', '') ?? ''),
            'client_secret' => $this->get('google_sso_client_secret', '') ?? '',
            'domain' => trim($this->get('google_domain', '') ?? ''),
        ];
    }

    /**
     * @return array{
     *     tenant_id: string,
     *     client_id: string,
     *     client_secret: string
     * }
     */
    public function getMicrosoftSsoConfig(): array
    {
        return [
            'tenant_id' => trim($this->get('azure_sso_tenant_id', '') ?? ''),
            'client_id' => trim($this->get('azure_sso_client_id', '') ?? ''),
            'client_secret' => $this->get('azure_sso_client_secret', '') ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getLoginConfigForAdmin(): array
    {
        $providers = $this->getLoginProviders();
        $googleSso = $this->getGoogleSsoConfig();
        $microsoftSso = $this->getMicrosoftSsoConfig();

        return [
            'providers' => $providers,
            'google_sso' => [
                'client_id' => $googleSso['client_id'],
                'client_secret' => '',
                'client_secret_configured' => $this->hasSecret('google_sso_client_secret'),
                'domain' => $googleSso['domain'],
            ],
            'microsoft_sso' => [
                'tenant_id' => $microsoftSso['tenant_id'],
                'client_id' => $microsoftSso['client_id'],
                'client_secret' => '',
                'client_secret_configured' => $this->hasSecret('azure_sso_client_secret'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getLoginConfigForLoginPage(): array
    {
        $config = $this->getLoginConfigForAdmin();
        $providers = $config['providers'];

        return [
            'providers' => $providers,
            'has_google_sso' => $providers['google']
                && trim((string) ($config['google_sso']['client_id'] ?? '')) !== ''
                && ($config['google_sso']['client_secret_configured'] ?? false),
            'has_microsoft_sso' => $providers['microsoft']
                && trim((string) ($config['microsoft_sso']['client_id'] ?? '')) !== ''
                && ($config['microsoft_sso']['client_secret_configured'] ?? false),
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public function saveLoginConfig(array $config): void
    {
        $providers = is_array($config['providers'] ?? null) ? $config['providers'] : [];

        $this->set('login_local_enabled', $this->toBool($providers['local'] ?? true) ? '1' : '0');
        $this->set('login_ldap_enabled', $this->toBool($providers['ldap'] ?? false) ? '1' : '0');
        $this->set('login_google_enabled', $this->toBool($providers['google'] ?? false) ? '1' : '0');
        $this->set('login_microsoft_enabled', $this->toBool($providers['microsoft'] ?? false) ? '1' : '0');

        if (is_array($config['google_sso'] ?? null)) {
            $this->set('google_sso_client_id', trim((string) ($config['google_sso']['client_id'] ?? '')));

            $clientSecret = trim((string) ($config['google_sso']['client_secret'] ?? ''));

            if ($clientSecret !== '') {
                $this->set('google_sso_client_secret', $clientSecret);
            }
        }

        if (is_array($config['microsoft_sso'] ?? null)) {
            $this->set('azure_sso_tenant_id', trim((string) ($config['microsoft_sso']['tenant_id'] ?? '')));
            $this->set('azure_sso_client_id', trim((string) ($config['microsoft_sso']['client_id'] ?? '')));

            $clientSecret = trim((string) ($config['microsoft_sso']['client_secret'] ?? ''));

            if ($clientSecret !== '') {
                $this->set('azure_sso_client_secret', $clientSecret);
            }
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
