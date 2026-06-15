<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Setting;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SettingsController
{
    private const ALLOWED_AUTH_DRIVERS = ['local', 'ldap', 'google', 'azure'];

    private const ALLOWED_FIELD_TYPES = ['text', 'number', 'textarea'];

    public function __construct(
        private readonly Setting $settingModel
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->settingModel->getAdminBundle(),
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => 'Invalid JSON payload. Send a valid JSON object in the request body.',
            ]);
        }

        $errors = $this->validatePayload($payload);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $errors,
            ]);
        }

        if (array_key_exists('active_auth_driver', $payload)) {
            $this->settingModel->set(
                'active_auth_driver',
                strtolower(trim((string) $payload['active_auth_driver']))
            );
        }

        if (array_key_exists('zimmet_template', $payload)) {
            $this->settingModel->set('zimmet_template', (string) $payload['zimmet_template']);
        }

        if (array_key_exists('custom_fields', $payload)) {
            $this->settingModel->setJson('custom_fields', $payload['custom_fields']);
        }

        if (array_key_exists('ldap_config', $payload) && is_array($payload['ldap_config'])) {
            $this->settingModel->saveLdapConfig($payload['ldap_config']);
        }

        if (array_key_exists('google_config', $payload) && is_array($payload['google_config'])) {
            $this->settingModel->saveGoogleConfig($payload['google_config']);
        }

        if (array_key_exists('login_config', $payload) && is_array($payload['login_config'])) {
            $this->settingModel->saveLoginConfig($payload['login_config']);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => 'Settings updated successfully.',
            'data' => $this->settingModel->getAdminBundle(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, list<string>>
     */
    private function validatePayload(array $payload): array
    {
        $errors = [];

        if (array_key_exists('active_auth_driver', $payload)) {
            $driver = strtolower(trim((string) $payload['active_auth_driver']));

            if (!in_array($driver, self::ALLOWED_AUTH_DRIVERS, true)) {
                $errors['active_auth_driver'][] = 'The selected auth driver is not supported.';
            }
        }

        if (array_key_exists('custom_fields', $payload) && !is_array($payload['custom_fields'])) {
            $errors['custom_fields'][] = 'Custom fields must be a JSON array.';
        }

        if (is_array($payload['custom_fields'] ?? null)) {
            foreach ($payload['custom_fields'] as $index => $field) {
                if (!is_array($field)) {
                    $errors['custom_fields'][] = sprintf('Custom field at index %d must be an object.', $index);
                    continue;
                }

                $name = trim((string) ($field['name'] ?? ''));

                if ($name === '') {
                    $errors['custom_fields'][] = sprintf('Custom field at index %d requires a name.', $index);
                }

                $type = trim((string) ($field['type'] ?? 'text'));

                if (!in_array($type, self::ALLOWED_FIELD_TYPES, true)) {
                    $errors['custom_fields'][] = sprintf('Custom field at index %d has an invalid type.', $index);
                }
            }
        }

        if (array_key_exists('ldap_config', $payload) && !is_array($payload['ldap_config'])) {
            $errors['ldap_config'][] = 'LDAP configuration must be an object.';
        }

        if (is_array($payload['ldap_config'] ?? null)) {
            $port = (int) ($payload['ldap_config']['port'] ?? 389);

            if ($port <= 0 || $port > 65535) {
                $errors['ldap_config'][] = 'LDAP port must be between 1 and 65535.';
            }
        }

        if (array_key_exists('google_config', $payload) && !is_array($payload['google_config'])) {
            $errors['google_config'][] = 'Google configuration must be an object.';
        }

        if (is_array($payload['google_config'] ?? null)) {
            $authMode = strtolower(trim((string) ($payload['google_config']['auth_mode'] ?? 'service_account')));

            if (!in_array($authMode, ['service_account', 'oauth'], true)) {
                $errors['google_config'][] = 'Google auth mode must be service_account or oauth.';
            }

            $serviceAccountJson = trim((string) ($payload['google_config']['service_account_json'] ?? ''));

            if ($serviceAccountJson !== '') {
                $decoded = json_decode($serviceAccountJson, true);

                if (!is_array($decoded)) {
                    $errors['google_config'][] = 'Google service account JSON is invalid.';
                } elseif (!isset($decoded['client_email'], $decoded['private_key'])) {
                    $errors['google_config'][] = 'Google service account JSON must include client_email and private_key.';
                }
            }

            $oauthTokenJson = trim((string) ($payload['google_config']['oauth_token_json'] ?? ''));

            if ($oauthTokenJson !== '') {
                $decoded = json_decode($oauthTokenJson, true);

                if (!is_array($decoded)) {
                    $errors['google_config'][] = 'Google OAuth token JSON is invalid.';
                }
            }
        }

        if (array_key_exists('login_config', $payload) && !is_array($payload['login_config'])) {
            $errors['login_config'][] = 'Login configuration must be an object.';
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePayload(ServerRequestInterface $request): ?array
    {
        $parsedBody = $request->getParsedBody();

        if (is_array($parsedBody)) {
            return $parsedBody;
        }

        $rawBody = (string) $request->getBody();

        if ($rawBody === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(ResponseInterface $response, int $statusCode, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}
