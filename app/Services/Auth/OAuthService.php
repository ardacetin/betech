<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Setting;
use JsonException;

class OAuthService
{
    private const STATE_SESSION_KEY = 'oauth_state';
    private const PROVIDER_SESSION_KEY = 'oauth_provider';

    public function __construct(
        private readonly Setting $settingModel,
        private readonly string $appUrl
    ) {
    }

    public function buildAuthorizationUrl(string $provider): ?string
    {
        $provider = $this->normalizeProvider($provider);

        if ($provider === null) {
            return null;
        }

        $state = bin2hex(random_bytes(16));

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION[self::STATE_SESSION_KEY] = $state;
        $_SESSION[self::PROVIDER_SESSION_KEY] = $provider;

        if ($provider === 'google') {
            return $this->buildGoogleAuthorizationUrl($state);
        }

        return $this->buildMicrosoftAuthorizationUrl($state);
    }

    /**
     * @return array{name: string, email: string, external_id: string, department: string|null, auth_provider: string, provider_subject: string}|null
     */
    public function handleCallback(string $provider, string $code, string $state): ?array
    {
        $provider = $this->normalizeProvider($provider);

        if ($provider === null || $code === '') {
            return null;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $expectedState = (string) ($_SESSION[self::STATE_SESSION_KEY] ?? '');
        unset($_SESSION[self::STATE_SESSION_KEY], $_SESSION[self::PROVIDER_SESSION_KEY]);

        if ($expectedState === '' || !hash_equals($expectedState, $state)) {
            return null;
        }

        if ($provider === 'google') {
            return $this->handleGoogleCallback($code);
        }

        return $this->handleMicrosoftCallback($code);
    }

    private function buildGoogleAuthorizationUrl(string $state): ?string
    {
        $config = $this->settingModel->getGoogleSsoConfig();

        if ($config['client_id'] === '') {
            return null;
        }

        $query = http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $this->callbackUrl('google'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $query;
    }

    private function buildMicrosoftAuthorizationUrl(string $state): ?string
    {
        $config = $this->settingModel->getMicrosoftSsoConfig();

        if ($config['client_id'] === '') {
            return null;
        }

        $tenant = $config['tenant_id'] !== '' ? $config['tenant_id'] : 'organizations';
        $query = http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $this->callbackUrl('microsoft'),
            'response_type' => 'code',
            'scope' => 'openid profile email User.Read',
            'state' => $state,
            'response_mode' => 'query',
        ]);

        return sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/authorize?%s',
            rawurlencode($tenant),
            $query
        );
    }

    /**
     * @return array{name: string, email: string, external_id: string, department: string|null, auth_provider: string, provider_subject: string}|null
     */
    private function handleGoogleCallback(string $code): ?array
    {
        $config = $this->settingModel->getGoogleSsoConfig();
        $tokenResponse = $this->exchangeToken(
            'https://oauth2.googleapis.com/token',
            [
                'code' => $code,
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $this->callbackUrl('google'),
                'grant_type' => 'authorization_code',
            ]
        );

        if ($tokenResponse === null || !isset($tokenResponse['access_token'])) {
            return null;
        }

        $profile = $this->httpGetJson(
            'https://openidconnect.googleapis.com/v1/userinfo',
            ['Authorization: Bearer ' . $tokenResponse['access_token']]
        );

        if (!is_array($profile) || empty($profile['email'])) {
            return null;
        }

        $email = strtolower(trim((string) $profile['email']));

        if (!$this->isAllowedGoogleEmail($email, $config['domain'])) {
            return null;
        }

        $subject = (string) ($profile['sub'] ?? $email);
        $name = trim((string) ($profile['name'] ?? $profile['given_name'] ?? $email));

        return [
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
            'external_id' => 'google:' . $subject,
            'department' => null,
            'auth_provider' => 'google',
            'provider_subject' => $subject,
        ];
    }

    /**
     * @return array{name: string, email: string, external_id: string, department: string|null, auth_provider: string, provider_subject: string}|null
     */
    private function handleMicrosoftCallback(string $code): ?array
    {
        $config = $this->settingModel->getMicrosoftSsoConfig();
        $tenant = $config['tenant_id'] !== '' ? $config['tenant_id'] : 'organizations';
        $tokenResponse = $this->exchangeToken(
            sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', rawurlencode($tenant)),
            [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'code' => $code,
                'redirect_uri' => $this->callbackUrl('microsoft'),
                'grant_type' => 'authorization_code',
            ]
        );

        if ($tokenResponse === null || !isset($tokenResponse['access_token'])) {
            return null;
        }

        $profile = $this->httpGetJson(
            'https://graph.microsoft.com/v1.0/me',
            ['Authorization: Bearer ' . $tokenResponse['access_token']]
        );

        if (!is_array($profile)) {
            return null;
        }

        $email = strtolower(trim((string) ($profile['mail'] ?? $profile['userPrincipalName'] ?? '')));

        if ($email === '') {
            return null;
        }

        $subject = (string) ($profile['id'] ?? $email);
        $name = trim((string) ($profile['displayName'] ?? $email));
        $department = isset($profile['department']) && $profile['department'] !== null
            ? trim((string) $profile['department'])
            : null;

        return [
            'name' => $name !== '' ? $name : $email,
            'email' => $email,
            'external_id' => 'microsoft:' . $subject,
            'department' => $department !== '' ? $department : null,
            'auth_provider' => 'microsoft',
            'provider_subject' => $subject,
        ];
    }

    private function isAllowedGoogleEmail(string $email, string $domain): bool
    {
        if ($domain === '') {
            return true;
        }

        $emailDomain = substr(strrchr($email, '@') ?: '', 1);

        return strcasecmp($emailDomain, $domain) === 0;
    }

    /**
     * @param array<string, string> $fields
     *
     * @return array<string, mixed>|null
     */
    private function exchangeToken(string $url, array $fields): ?array
    {
        $response = $this->httpRequest('POST', $url, [
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query($fields));

        if ($response === null) {
            return null;
        }

        try {
            $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param list<string> $headers
     *
     * @return array<string, mixed>|null
     */
    private function httpGetJson(string $url, array $headers = []): ?array
    {
        $response = $this->httpRequest('GET', $url, $headers);

        if ($response === null) {
            return null;
        }

        try {
            $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param list<string> $headers
     */
    private function httpRequest(string $method, string $url, array $headers = [], ?string $body = null): ?string
    {
        $curl = curl_init($url);

        if ($curl === false) {
            return null;
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false || $statusCode >= 400) {
            return null;
        }

        return (string) $response;
    }

    private function callbackUrl(string $provider): string
    {
        return rtrim($this->appUrl, '/') . '/auth/callback/' . $provider;
    }

    private function normalizeProvider(string $provider): ?string
    {
        $normalized = strtolower(trim($provider));

        return match ($normalized) {
            'google' => 'google',
            'microsoft', 'azure', 'microsoft365' => 'microsoft',
            default => null,
        };
    }
}
