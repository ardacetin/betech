<?php

declare(strict_types=1);

namespace App\Services\Auth\Drivers;

use App\Models\Setting;
use App\Services\Auth\UserIntegrationInterface;
use JsonException;

class GoogleDriver implements UserIntegrationInterface
{
    private const DIRECTORY_SCOPE = 'https://www.googleapis.com/auth/admin.directory.user.readonly';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const DIRECTORY_BASE = 'https://admin.googleapis.com/admin/directory/v1/users';
    private const SEARCH_LIMIT = 20;

    public function __construct(
        private readonly Setting $settingModel
    ) {
    }

    public function searchUsers(string $query): array
    {
        $config = $this->settingModel->getGoogleConfig();

        if ($config['domain'] === '') {
            return [];
        }

        $accessToken = $this->resolveAccessToken($config);

        if ($accessToken === null) {
            return [];
        }

        $params = [
            'domain' => $config['domain'],
            'maxResults' => (string) self::SEARCH_LIMIT,
            'orderBy' => 'familyName',
            'projection' => 'full',
        ];

        $trimmedQuery = trim($query);

        if ($trimmedQuery !== '') {
            $params['query'] = $trimmedQuery;
        }

        $response = $this->directoryRequest('GET', self::DIRECTORY_BASE . '?' . http_build_query($params), $accessToken);

        if ($response === null || !isset($response['users']) || !is_array($response['users'])) {
            return [];
        }

        $users = [];

        foreach ($response['users'] as $user) {
            if (!is_array($user)) {
                continue;
            }

            $mapped = $this->mapUser($user);

            if ($mapped !== null) {
                $users[] = $mapped;
            }
        }

        return $users;
    }

    public function getUserById(string $id): ?array
    {
        $trimmedId = trim($id);

        if ($trimmedId === '') {
            return null;
        }

        $config = $this->settingModel->getGoogleConfig();

        if ($config['domain'] === '') {
            return null;
        }

        $accessToken = $this->resolveAccessToken($config);

        if ($accessToken === null) {
            return null;
        }

        $userKey = rawurlencode($trimmedId);
        $response = $this->directoryRequest(
            'GET',
            self::DIRECTORY_BASE . '/' . $userKey . '?projection=full',
            $accessToken
        );

        if (!is_array($response)) {
            return null;
        }

        return $this->mapUser($response);
    }

    public function listAllUsers(): array
    {
        $config = $this->settingModel->getGoogleConfig();

        if ($config['domain'] === '') {
            return [];
        }

        $accessToken = $this->resolveAccessToken($config);

        if ($accessToken === null) {
            return [];
        }

        $users = [];
        $pageToken = null;

        do {
            $params = [
                'domain' => $config['domain'],
                'maxResults' => '500',
                'orderBy' => 'familyName',
                'projection' => 'full',
            ];

            if ($pageToken !== null) {
                $params['pageToken'] = $pageToken;
            }

            $response = $this->directoryRequest(
                'GET',
                self::DIRECTORY_BASE . '?' . http_build_query($params),
                $accessToken
            );

            if ($response === null || !isset($response['users']) || !is_array($response['users'])) {
                break;
            }

            foreach ($response['users'] as $user) {
                if (!is_array($user)) {
                    continue;
                }

                $mapped = $this->mapUser($user);

                if ($mapped !== null) {
                    $users[] = $mapped;
                }
            }

            $nextPageToken = $response['nextPageToken'] ?? null;
            $pageToken = is_string($nextPageToken) && $nextPageToken !== '' ? $nextPageToken : null;
        } while ($pageToken !== null);

        return $users;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveAccessToken(array $config): ?string
    {
        if (($config['auth_mode'] ?? 'service_account') === 'oauth') {
            return $this->resolveOAuthAccessToken($config);
        }

        return $this->resolveServiceAccountAccessToken($config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveServiceAccountAccessToken(array $config): ?string
    {
        $serviceAccount = $config['service_account'] ?? null;

        if (!is_array($serviceAccount) || ($config['admin_email'] ?? '') === '') {
            return null;
        }

        $clientEmail = (string) ($serviceAccount['client_email'] ?? '');
        $privateKey = (string) ($serviceAccount['private_key'] ?? '');

        if ($clientEmail === '' || $privateKey === '') {
            return null;
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'sub' => $config['admin_email'],
            'scope' => self::DIRECTORY_SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $signatureInput = $header . '.' . $claims;
        $signature = '';

        if (!openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        $jwt = $signatureInput . '.' . $this->base64UrlEncode($signature);

        $tokenResponse = $this->curlJson(
            'POST',
            self::TOKEN_URL,
            [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ],
            ['Content-Type: application/x-www-form-urlencoded']
        );

        return is_array($tokenResponse) && isset($tokenResponse['access_token'])
            ? (string) $tokenResponse['access_token']
            : null;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveOAuthAccessToken(array $config): ?string
    {
        $oauth = $config['oauth_token'] ?? null;

        if (!is_array($oauth)) {
            return null;
        }

        $accessToken = trim((string) ($oauth['access_token'] ?? ''));
        $expiresAt = (int) ($oauth['expires_at'] ?? 0);

        if ($accessToken !== '' && ($expiresAt === 0 || $expiresAt > time() + 60)) {
            return $accessToken;
        }

        $refreshToken = trim((string) ($oauth['refresh_token'] ?? ''));
        $clientId = trim((string) ($oauth['client_id'] ?? ''));
        $clientSecret = trim((string) ($oauth['client_secret'] ?? ''));

        if ($refreshToken === '' || $clientId === '' || $clientSecret === '') {
            return $accessToken !== '' ? $accessToken : null;
        }

        $tokenResponse = $this->curlJson(
            'POST',
            self::TOKEN_URL,
            [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ],
            ['Content-Type: application/x-www-form-urlencoded']
        );

        if (!is_array($tokenResponse) || !isset($tokenResponse['access_token'])) {
            return $accessToken !== '' ? $accessToken : null;
        }

        $oauth['access_token'] = (string) $tokenResponse['access_token'];
        $oauth['expires_at'] = time() + (int) ($tokenResponse['expires_in'] ?? 3600);

        if (isset($tokenResponse['refresh_token'])) {
            $oauth['refresh_token'] = (string) $tokenResponse['refresh_token'];
        }

        $this->settingModel->setJson('google_oauth_token_json', $oauth);

        return (string) $oauth['access_token'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function directoryRequest(string $method, string $url, string $accessToken): ?array
    {
        return $this->curlJson(
            $method,
            $url,
            null,
            [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ]
        );
    }

    /**
     * @param array<string, string>|null $formBody
     * @param list<string> $headers
     *
     * @return array<string, mixed>|null
     */
    private function curlJson(string $method, string $url, ?array $formBody, array $headers = []): ?array
    {
        $curl = curl_init($url);

        if ($curl === false) {
            return null;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ];

        if ($formBody !== null) {
            $options[CURLOPT_POSTFIELDS] = http_build_query($formBody);
        }

        curl_setopt_array($curl, $options);

        $rawResponse = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (!is_string($rawResponse) || $rawResponse === '' || $statusCode >= 400) {
            return null;
        }

        try {
            $decoded = json_decode($rawResponse, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $user
     *
     * @return array{id: string, external_id: string, name: string, email: string, department: string|null}|null
     */
    private function mapUser(array $user): ?array
    {
        $primaryEmail = trim((string) ($user['primaryEmail'] ?? ''));

        if ($primaryEmail === '') {
            return null;
        }

        $googleId = trim((string) ($user['id'] ?? $primaryEmail));
        $fullName = trim((string) ($user['name']['fullName'] ?? ''));

        if ($fullName === '') {
            $given = trim((string) ($user['name']['givenName'] ?? ''));
            $family = trim((string) ($user['name']['familyName'] ?? ''));
            $fullName = trim($given . ' ' . $family);
        }

        if ($fullName === '') {
            $fullName = $primaryEmail;
        }

        $department = null;
        $organizations = $user['organizations'] ?? [];

        if (is_array($organizations) && isset($organizations[0]['department'])) {
            $departmentValue = trim((string) $organizations[0]['department']);
            $department = $departmentValue !== '' ? $departmentValue : null;
        }

        return [
            'id' => $googleId,
            'external_id' => $googleId,
            'name' => $fullName,
            'email' => $primaryEmail,
            'department' => $department,
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
