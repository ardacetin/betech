<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\AssetColumnSchemaService;
use App\Services\AuditLogger;
use App\Services\Auth\SessionAuthService;
use App\Services\Mail\MailConfigResolver;
use App\Services\Mail\MailService;
use App\Services\ViewRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SettingsController
{
    private const ALLOWED_AUTH_DRIVERS = ['local', 'ldap', 'google', 'azure'];

    private const ALLOWED_FIELD_TYPES = ['text', 'number', 'textarea'];

    private const ALLOWED_SMTP_ENCRYPTION = ['tls', 'ssl', 'none'];

    public function __construct(
        private readonly Setting $settingModel,
        private readonly MailService $mailService,
        private readonly MailConfigResolver $mailConfigResolver,
        private readonly ViewRenderer $viewRenderer,
        private readonly SessionAuthService $sessionAuthService,
        private readonly User $userModel,
        private readonly string $appUrl,
        private readonly AuditLogger $auditLogger,
        private readonly AssetColumnSchemaService $assetColumnSchemaService,
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

        $beforeSettings = $this->settingModel->getAdminBundle();
        $changedSections = [];

        if (array_key_exists('active_auth_driver', $payload)) {
            $changedSections[] = 'active_auth_driver';
        }

        if (array_key_exists('zimmet_template', $payload)) {
            $changedSections[] = 'zimmet_template';
        }

        if (array_key_exists('custom_fields', $payload)) {
            $changedSections[] = 'custom_fields';
        }

        if (array_key_exists('ldap_config', $payload)) {
            $changedSections[] = 'ldap_config';
        }

        if (array_key_exists('google_config', $payload)) {
            $changedSections[] = 'google_config';
        }

        if (array_key_exists('login_config', $payload)) {
            $changedSections[] = 'login_config';
        }

        if (array_key_exists('smtp_config', $payload)) {
            $changedSections[] = 'smtp_config';
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
            $normalizedCustomFields = $this->normalizeCustomFields($payload['custom_fields']);
            $previousCustomFields = is_array($beforeSettings['custom_fields'] ?? null)
                ? $beforeSettings['custom_fields']
                : [];

            try {
                $this->assetColumnSchemaService->syncCustomFieldColumns($normalizedCustomFields, $previousCustomFields);
            } catch (\RuntimeException $exception) {
                return $this->jsonResponse($response, 422, [
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                ]);
            }

            $this->settingModel->setJson('custom_fields', $normalizedCustomFields);
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

        if (array_key_exists('smtp_config', $payload) && is_array($payload['smtp_config'])) {
            $this->settingModel->saveSmtpConfig($payload['smtp_config']);
        }

        if ($changedSections !== []) {
            $afterSettings = $this->settingModel->getAdminBundle();
            $diff = $this->auditLogger->buildSettingsDiff($beforeSettings, $afterSettings, $changedSections);
            $this->auditLogger->logFromRequest(
                $request,
                $this->sessionAuthService->userId(),
                AuditLog::ACTION_UPDATED,
                AuditLog::ENTITY_SETTING,
                null,
                $diff['old'],
                $diff['new']
            );
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => 'Settings updated successfully.',
            'data' => $this->settingModel->getAdminBundle(),
        ]);
    }

    public function sendTestSmtp(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request) ?? [];
        $recipient = strtolower(trim((string) ($payload['recipient'] ?? '')));

        if ($recipient === '') {
            $userId = $this->sessionAuthService->userId();

            if ($userId !== null && $userId > 0) {
                $user = $this->userModel->findById($userId);
                $recipient = strtolower(trim((string) ($user['email'] ?? '')));
            }
        }

        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('settings_smtp_test_recipient_invalid'),
            ]);
        }

        $smtpOverride = is_array($payload['smtp_config'] ?? null) ? $payload['smtp_config'] : null;
        $validationConfig = $smtpOverride ?? $this->settingModel->getSmtpConfigForAdmin();
        $errors = $this->validateSmtpConfig($validationConfig, true);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('settings_smtp_test_validation_failed'),
                'errors' => $errors,
            ]);
        }

        $html = $this->viewRenderer->render('emails/smtp_test', [
            'heading' => __('settings_smtp_test_email_heading'),
            'intro' => __('settings_smtp_test_email_intro'),
            'footer' => __('mail_ticket_footer'),
            'appUrl' => rtrim($this->appUrl, '/'),
        ], 'emails/layout');

        $testConfigOverride = is_array($smtpOverride) ? array_merge($smtpOverride, ['enabled' => true]) : ['enabled' => true];

        $sent = $this->mailService->sendHtml(
            [$recipient],
            __('settings_smtp_test_email_subject'),
            $html,
            $testConfigOverride
        );

        if (!$sent) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('settings_smtp_test_failed'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('settings_smtp_test_success', ['email' => $recipient]),
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

                $label = trim((string) ($field['label'] ?? ''));

                if ($label === '') {
                    $errors['custom_fields'][] = sprintf('Custom field at index %d requires a label.', $index);
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

        if (array_key_exists('smtp_config', $payload)) {
            if (!is_array($payload['smtp_config'])) {
                $errors['smtp_config'][] = __('settings_smtp_validation_object');
            } else {
                foreach ($this->validateSmtpConfig($payload['smtp_config']) as $field => $messages) {
                    $errors[$field] = $messages;
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, list<string>>
     */
    private function validateSmtpConfig(array $config, bool $forSend = false): array
    {
        $errors = [];

        $host = trim((string) ($config['host'] ?? ''));
        if ($host === '') {
            $errors['smtp_config.host'][] = __('settings_smtp_host_required');
        }

        $port = (int) ($config['port'] ?? 587);
        if ($port <= 0 || $port > 65535) {
            $errors['smtp_config.port'][] = __('settings_smtp_port_invalid');
        }

        $senderEmail = strtolower(trim((string) ($config['sender_email'] ?? '')));
        if ($senderEmail === '' || filter_var($senderEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors['smtp_config.sender_email'][] = __('settings_smtp_sender_email_invalid');
        }

        $encryption = strtolower(trim((string) ($config['encryption'] ?? 'tls')));
        if (!in_array($encryption, self::ALLOWED_SMTP_ENCRYPTION, true)) {
            $errors['smtp_config.encryption'][] = __('settings_smtp_encryption_invalid');
        }

        $password = trim((string) ($config['pass'] ?? $config['password'] ?? ''));
        $passwordConfigured = trim($password) !== ''
            || $this->toBool($config['pass_configured'] ?? false)
            || trim((string) ($this->settingModel->get('smtp_pass') ?? '')) !== '';

        if ($forSend && $password === '' && !$passwordConfigured && trim((string) ($config['user'] ?? '')) !== '') {
            $errors['smtp_config.pass'][] = __('settings_smtp_pass_required');
        }

        return $errors;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param mixed $fields
     *
     * @return list<array{id: int, name: string, label: string, type: string}>
     */
    private function normalizeCustomFields(mixed $fields): array
    {
        if (!is_array($fields)) {
            return [];
        }

        $normalized = [];
        $usedNames = [];
        $nextId = 1;

        foreach ($fields as $field) {
            if (is_array($field) && isset($field['id']) && is_numeric($field['id'])) {
                $nextId = max($nextId, ((int) $field['id']) + 1);
            }
        }

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $label = trim((string) ($field['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $type = trim((string) ($field['type'] ?? 'text'));

            if (!in_array($type, self::ALLOWED_FIELD_TYPES, true)) {
                $type = 'text';
            }

            $existingName = trim((string) ($field['name'] ?? ''));

            if ($existingName !== '' && $this->assetColumnSchemaService->isValidCustomColumnName($existingName)) {
                $name = $existingName;
            } else {
                $name = custom_field_code_from_label($label);
                $baseName = $name;
                $suffix = 2;

                while (
                    in_array($name, $usedNames, true)
                    || array_key_exists($name, AssetColumnSchemaService::NATIVE_COLUMN_LABELS)
                    || in_array($name, AssetColumnSchemaService::SYSTEM_COLUMNS, true)
                ) {
                    $name = $baseName . '_' . $suffix;
                    ++$suffix;
                }
            }

            $usedNames[] = $name;

            $id = isset($field['id']) && is_numeric($field['id']) ? (int) $field['id'] : $nextId;
            $nextId = max($nextId, $id + 1);

            $normalized[] = [
                'id' => $id,
                'name' => $name,
                'label' => $label,
                'type' => $type,
            ];
        }

        return $normalized;
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
