<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Setting;

class MailConfigResolver
{
    public function __construct(
        private readonly Setting $settingModel
    ) {
    }

    /**
     * @return array{
     *     enabled: bool,
     *     host: string,
     *     port: int,
     *     username: string,
     *     password: string,
     *     encryption: string,
     *     from_address: string,
     *     from_name: string,
     *     support_addresses: list<string>
     * }
     */
    public function resolve(?array $override = null): array
    {
        $stored = $this->settingModel->getSmtpConfig();

        if ($override === null) {
            return $this->applyEnvOverrides($stored);
        }

        return $this->applyEnvOverrides($this->mergeWithOverride($stored, $override));
    }

    /**
     * @param array{
     *     enabled: bool,
     *     host: string,
     *     port: int,
     *     username: string,
     *     password: string,
     *     encryption: string,
     *     from_address: string,
     *     from_name: string,
     *     support_addresses: list<string>
     * } $stored
     * @param array<string, mixed> $override
     *
     * @return array{
     *     enabled: bool,
     *     host: string,
     *     port: int,
     *     username: string,
     *     password: string,
     *     encryption: string,
     *     from_address: string,
     *     from_name: string,
     *     support_addresses: list<string>
     * }
     */
    private function mergeWithOverride(array $stored, array $override): array
    {
        $config = $stored;

        if (array_key_exists('enabled', $override)) {
            $config['enabled'] = $this->toBool($override['enabled']);
        }

        if (array_key_exists('host', $override)) {
            $config['host'] = trim((string) $override['host']);
        }

        if (array_key_exists('port', $override)) {
            $config['port'] = max(1, (int) $override['port']);
        }

        if (array_key_exists('user', $override)) {
            $config['username'] = trim((string) $override['user']);
        }

        $password = trim((string) ($override['pass'] ?? $override['password'] ?? ''));

        if ($password !== '') {
            $config['password'] = $password;
        }

        if (array_key_exists('encryption', $override)) {
            $config['encryption'] = $this->normalizeEncryption((string) $override['encryption']);
        }

        if (array_key_exists('sender_email', $override)) {
            $config['from_address'] = strtolower(trim((string) $override['sender_email']));
        }

        if (array_key_exists('sender_name', $override)) {
            $config['from_name'] = trim((string) $override['sender_name']);
        }

        if (array_key_exists('support_to', $override)) {
            $config['support_addresses'] = $this->parseEmailList((string) $override['support_to']);
        }

        return $config;
    }

    /**
     * @param list<string> $addresses
     */
    public function parseEmailList(string $raw): array
    {
        $entries = preg_split('/[\s,;]+/', $raw) ?: [];
        $unique = [];

        foreach ($entries as $entry) {
            $email = strtolower(trim((string) $entry));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $unique[$email] = true;
            }
        }

        return array_keys($unique);
    }

    private function normalizeEncryption(string $encryption): string
    {
        $normalized = strtolower(trim($encryption));

        return in_array($normalized, ['tls', 'ssl', 'none'], true) ? $normalized : 'tls';
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array{
     *     enabled: bool,
     *     host: string,
     *     port: int,
     *     username: string,
     *     password: string,
     *     encryption: string,
     *     from_address: string,
     *     from_name: string,
     *     support_addresses: list<string>
     * } $config
     *
     * @return array{
     *     enabled: bool,
     *     host: string,
     *     port: int,
     *     username: string,
     *     password: string,
     *     encryption: string,
     *     from_address: string,
     *     from_name: string,
     *     support_addresses: list<string>
     * }
     */
    private function applyEnvOverrides(array $config): array
    {
        $host = trim((string) ($_ENV['MAIL_HOST'] ?? ''));

        if ($host !== '') {
            $config['enabled'] = true;
            $config['host'] = $host;
        }

        if (isset($_ENV['MAIL_PORT']) && trim((string) $_ENV['MAIL_PORT']) !== '') {
            $config['port'] = max(1, (int) $_ENV['MAIL_PORT']);
        }

        if (isset($_ENV['MAIL_USERNAME']) && trim((string) $_ENV['MAIL_USERNAME']) !== '') {
            $config['username'] = trim((string) $_ENV['MAIL_USERNAME']);
        }

        if (isset($_ENV['MAIL_PASSWORD']) && trim((string) $_ENV['MAIL_PASSWORD']) !== '') {
            $config['password'] = (string) $_ENV['MAIL_PASSWORD'];
        }

        if (isset($_ENV['MAIL_ENCRYPTION']) && trim((string) $_ENV['MAIL_ENCRYPTION']) !== '') {
            $config['encryption'] = $this->normalizeEncryption((string) $_ENV['MAIL_ENCRYPTION']);
        }

        if (isset($_ENV['MAIL_FROM_ADDRESS']) && trim((string) $_ENV['MAIL_FROM_ADDRESS']) !== '') {
            $config['from_address'] = strtolower(trim((string) $_ENV['MAIL_FROM_ADDRESS']));
        }

        if (isset($_ENV['MAIL_FROM_NAME']) && trim((string) $_ENV['MAIL_FROM_NAME']) !== '') {
            $config['from_name'] = trim((string) $_ENV['MAIL_FROM_NAME']);
        }

        return $this->applySupportInboxFromFallback($config);
    }

    /**
     * When no explicit sender is configured, use the first support inbox address
     * ("Destek Gelen Kutusu") as MAIL_FROM / smtp_sender_email fallback.
     *
     * @param array{
     *     enabled: bool,
     *     host: string,
     *     port: int,
     *     username: string,
     *     password: string,
     *     encryption: string,
     *     from_address: string,
     *     from_name: string,
     *     support_addresses: list<string>
     * } $config
     *
     * @return array{
     *     enabled: bool,
     *     host: string,
     *     port: int,
     *     username: string,
     *     password: string,
     *     encryption: string,
     *     from_address: string,
     *     from_name: string,
     *     support_addresses: list<string>
     * }
     */
    private function applySupportInboxFromFallback(array $config): array
    {
        if ($config['from_address'] !== '' && filter_var($config['from_address'], FILTER_VALIDATE_EMAIL) !== false) {
            return $config;
        }

        if ($config['support_addresses'] === []) {
            return $config;
        }

        $config['from_address'] = $config['support_addresses'][0];

        return $config;
    }
}
