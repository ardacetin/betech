<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Services\AppLogger;
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class MailService
{
    /** @var list<string> */
    private array $smtpTrace = [];

    private ?string $lastMessageId = null;

    public function __construct(
        private readonly MailConfigResolver $configResolver,
        private readonly AppLogger $appLogger
    ) {
    }

    public function getLastMessageId(): ?string
    {
        return $this->lastMessageId;
    }

    /**
     * @param array<string, mixed>|null $configOverride
     */
    public function isConfigured(?array $configOverride = null): bool
    {
        $config = $this->configResolver->resolve($configOverride);

        if (!$config['enabled']) {
            return false;
        }

        if ($config['host'] === '' || $config['from_address'] === '') {
            return false;
        }

        return filter_var($config['from_address'], FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param list<string> $recipients
     * @param array<string, mixed>|null $configOverride
     * @param array{
     *     cc?: list<string>|null,
     *     messageId?: string|null,
     *     inReplyTo?: string|null,
     *     references?: string|list<string>|null,
     *     replyTo?: string|null
     * } $options
     */
    public function sendHtml(
        array $recipients,
        string $subject,
        string $htmlBody,
        ?array $configOverride = null,
        array $options = []
    ): bool {
        $this->smtpTrace = [];
        $this->lastMessageId = null;
        $config = $this->configResolver->resolve($configOverride);

        $this->appLogger->log('mail.dispatch.start', [
            'subject' => $subject,
            'stage' => 'config_resolved',
            'enabled' => $config['enabled'],
            'host' => $config['host'],
            'port' => $config['port'],
            'encryption' => $config['encryption'],
            'from_address' => $config['from_address'],
            'from_name' => $config['from_name'],
            'username' => $config['username'],
            'password_configured' => $config['password'] !== '',
            'support_inbox_addresses' => $config['support_addresses'],
        ]);

        if (!$this->isConfigured($configOverride)) {
            $this->appLogger->error('mail.skipped', [
                'reason' => 'mail_not_configured',
                'subject' => $subject,
                'stage' => 'pre_send_validation',
                'enabled' => $config['enabled'],
                'host' => $config['host'],
                'from_address' => $config['from_address'],
            ]);

            return false;
        }

        $normalizedRecipients = $this->normalizeRecipients($recipients);
        $normalizedCc = $this->normalizeRecipients(
            is_array($options['cc'] ?? null) ? $options['cc'] : []
        );
        $normalizedCc = array_values(array_filter(
            $normalizedCc,
            static fn (string $email): bool => !in_array($email, $normalizedRecipients, true)
        ));

        $this->appLogger->log('mail.dispatch.recipients', [
            'subject' => $subject,
            'stage' => 'recipients_normalized',
            'recipients' => $normalizedRecipients,
            'cc' => $normalizedCc,
        ]);

        if ($normalizedRecipients === []) {
            $this->appLogger->error('mail.skipped', [
                'reason' => 'no_recipients',
                'subject' => $subject,
                'stage' => 'pre_send_validation',
            ]);

            return false;
        }

        $messageId = $this->normalizeMessageId(
            isset($options['messageId']) ? (string) $options['messageId'] : null,
            $config['from_address']
        );

        try {
            $mailer = $this->createMailer($config);

            $this->appLogger->log('mail.dispatch.mailer_ready', [
                'subject' => $subject,
                'stage' => 'smtp_client_initialized',
                'smtp_auth' => $config['username'] !== '',
            ]);

            foreach ($normalizedRecipients as $recipient) {
                $mailer->addAddress($recipient);
            }

            foreach ($normalizedCc as $cc) {
                $mailer->addCC($cc);
            }

            $replyTo = trim((string) ($options['replyTo'] ?? ''));

            if ($replyTo === '' && $config['support_addresses'] !== []) {
                $replyTo = (string) $config['support_addresses'][0];
            }

            if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false) {
                $mailer->addReplyTo($replyTo);
            }

            $mailer->MessageID = $messageId;

            $inReplyTo = $this->normalizeOptionalMessageId(
                isset($options['inReplyTo']) ? (string) $options['inReplyTo'] : null
            );

            if ($inReplyTo !== null) {
                $mailer->addCustomHeader('In-Reply-To', $inReplyTo);
            }

            $references = $this->normalizeReferences($options['references'] ?? null);

            if ($references !== '') {
                $mailer->addCustomHeader('References', $references);
            }

            $mailer->Subject = $subject;
            $mailer->Body = $htmlBody;
            $mailer->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));

            $this->appLogger->log('mail.dispatch.sending', [
                'subject' => $subject,
                'stage' => 'smtp_send',
                'message_id' => $messageId,
            ]);

            $mailer->send();
            $this->lastMessageId = $messageId;

            $this->appLogger->log('mail.sent', [
                'subject' => $subject,
                'recipients' => $normalizedRecipients,
                'cc' => $normalizedCc,
                'message_id' => $messageId,
                'stage' => 'completed',
            ]);

            return true;
        } catch (MailerException $exception) {
            $this->appLogger->error('mail.failed', [
                'subject' => $subject,
                'recipients' => $normalizedRecipients,
                'stage' => 'smtp_send_failed',
                'error' => $exception->getMessage(),
                'phpmailer_error' => isset($mailer) ? $mailer->ErrorInfo : null,
                'smtp_trace' => $this->smtpTrace,
            ]);

            return false;
        } catch (\Throwable $exception) {
            $this->appLogger->error('mail.failed', [
                'subject' => $subject,
                'recipients' => $normalizedRecipients,
                'stage' => 'unexpected_failure',
                'error' => $exception->getMessage(),
                'exception_class' => $exception::class,
                'smtp_trace' => $this->smtpTrace,
            ]);

            return false;
        }
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
     */
    private function createMailer(array $config): PHPMailer
    {
        $mailer = new PHPMailer(true);
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->isSMTP();
        $mailer->Host = $config['host'];
        $mailer->Port = max(1, $config['port']);
        $mailer->SMTPAuth = $config['username'] !== '';
        $mailer->Username = $config['username'];
        $mailer->Password = $config['password'];
        $mailer->Timeout = 15;
        $mailer->SMTPKeepAlive = false;
        $mailer->SMTPDebug = SMTP::DEBUG_SERVER;
        $mailer->Debugoutput = function (string $line, int $level): void {
            $trimmed = trim($line);

            if ($trimmed === '') {
                return;
            }

            $this->smtpTrace[] = $trimmed;
            $this->appLogger->log('mail.smtp.trace', [
                'line' => $trimmed,
                'level' => $level,
            ]);
        };

        $encryption = $config['encryption'];

        if ($encryption === 'tls') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mailer->SMTPSecure = '';
            $mailer->SMTPAutoTLS = false;
        }

        $mailer->setFrom($config['from_address'], $config['from_name']);
        $mailer->isHTML(true);

        return $mailer;
    }

    /**
     * @param list<string> $recipients
     *
     * @return list<string>
     */
    private function normalizeRecipients(array $recipients): array
    {
        $unique = [];

        foreach ($recipients as $recipient) {
            $email = strtolower(trim($recipient));

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $unique[$email] = true;
        }

        return array_keys($unique);
    }

    private function normalizeMessageId(?string $messageId, string $fromAddress): string
    {
        $normalized = $this->normalizeOptionalMessageId($messageId);

        if ($normalized !== null) {
            return $normalized;
        }

        $domain = 'localhost';

        if (str_contains($fromAddress, '@')) {
            $domain = substr($fromAddress, (int) strrpos($fromAddress, '@') + 1) ?: 'localhost';
        }

        return '<' . bin2hex(random_bytes(12)) . '.' . time() . '@' . $domain . '>';
    }

    private function normalizeOptionalMessageId(?string $messageId): ?string
    {
        $value = trim((string) $messageId);

        if ($value === '') {
            return null;
        }

        if ($value[0] !== '<') {
            $value = '<' . $value;
        }

        if (!str_ends_with($value, '>')) {
            $value .= '>';
        }

        return $value;
    }

    /**
     * @param mixed $references
     */
    private function normalizeReferences(mixed $references): string
    {
        if (is_string($references)) {
            $parts = preg_split('/\s+/', trim($references)) ?: [];
        } elseif (is_array($references)) {
            $parts = $references;
        } else {
            return '';
        }

        $normalized = [];

        foreach ($parts as $part) {
            $messageId = $this->normalizeOptionalMessageId(is_string($part) ? $part : null);

            if ($messageId !== null) {
                $normalized[$messageId] = true;
            }
        }

        return implode(' ', array_keys($normalized));
    }
}
