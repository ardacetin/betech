<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Setting;

class ImapConfigResolver
{
    public function __construct(
        private readonly Setting $settingModel,
        private readonly MailConfigResolver $mailConfigResolver
    ) {
    }

    /**
     * @return array{
     *     enabled: bool,
     *     host: string,
     *     port: int,
     *     encryption: string,
     *     username: string,
     *     password: string,
     *     mailbox: string,
     *     support_inbox_address: string
     * }
     */
    public function resolve(): array
    {
        $smtp = $this->mailConfigResolver->resolve();
        $host = trim((string) ($_ENV['MAIL_IMAP_HOST'] ?? ''));

        if ($host === '') {
            $host = $this->deriveImapHostFromSmtp($smtp['host']);
        }

        $port = (int) ($_ENV['MAIL_IMAP_PORT'] ?? 993);

        if ($port < 1) {
            $port = 993;
        }

        $encryption = $this->normalizeEncryption((string) ($_ENV['MAIL_IMAP_ENCRYPTION'] ?? 'ssl'));
        $username = trim((string) ($_ENV['MAIL_IMAP_USERNAME'] ?? ''));

        if ($username === '') {
            $username = $smtp['username'];
        }

        $password = trim((string) ($_ENV['MAIL_IMAP_PASSWORD'] ?? ''));

        if ($password === '') {
            $password = $smtp['password'];
        }

        $mailbox = trim((string) ($_ENV['MAIL_IMAP_MAILBOX'] ?? 'INBOX'));

        if ($mailbox === '') {
            $mailbox = 'INBOX';
        }

        $supportInbox = strtolower(trim((string) ($_ENV['MAIL_FROM_ADDRESS'] ?? '')));

        if ($supportInbox === '' && $smtp['from_address'] !== '') {
            $supportInbox = $smtp['from_address'];
        }

        if ($supportInbox === '' && $smtp['support_addresses'] !== []) {
            $supportInbox = $smtp['support_addresses'][0];
        }

        $enabled = $this->toBool($_ENV['MAIL_IMAP_ENABLED'] ?? '');

        if (!$enabled && $host !== '' && $username !== '' && $password !== '') {
            $enabled = true;
        }

        return [
            'enabled' => $enabled,
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'password' => $password,
            'mailbox' => $mailbox,
            'support_inbox_address' => $supportInbox,
        ];
    }

    /**
     * @param array{
     *     host: string,
     *     port: int,
     *     encryption: string,
     *     username: string,
     *     mailbox: string,
     *     support_inbox_address: string
     * } $config
     */
    public function isConfigured(array $config): bool
    {
        if (!$config['enabled']) {
            return false;
        }

        if ($config['host'] === '' || $config['username'] === '' || $config['password'] === '') {
            return false;
        }

        return function_exists('imap_open');
    }

    /**
     * @param array{
     *     host: string,
     *     port: int,
     *     encryption: string,
     *     mailbox: string
     * } $config
     */
    public function buildMailboxPath(array $config): string
    {
        $flags = '/imap';

        if ($config['encryption'] === 'ssl') {
            $flags .= '/ssl';
        } elseif ($config['encryption'] === 'tls') {
            $flags .= '/tls';
        }

        $flags .= '/novalidate-cert';

        return sprintf(
            '{%s:%d%s}%s',
            $config['host'],
            max(1, $config['port']),
            $flags,
            $config['mailbox']
        );
    }

    private function deriveImapHostFromSmtp(string $smtpHost): string
    {
        $smtpHost = strtolower(trim($smtpHost));

        if ($smtpHost === '') {
            return '';
        }

        if (str_starts_with($smtpHost, 'smtp.')) {
            return 'imap.' . substr($smtpHost, 5);
        }

        return $smtpHost;
    }

    private function normalizeEncryption(string $encryption): string
    {
        $normalized = strtolower(trim($encryption));

        return in_array($normalized, ['ssl', 'tls', 'none'], true) ? $normalized : 'ssl';
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
