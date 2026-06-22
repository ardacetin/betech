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

    public function __construct(
        private readonly MailConfigResolver $configResolver,
        private readonly AppLogger $appLogger
    ) {
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
     */
    public function sendHtml(array $recipients, string $subject, string $htmlBody, ?array $configOverride = null): bool
    {
        $this->smtpTrace = [];
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

        $this->appLogger->log('mail.dispatch.recipients', [
            'subject' => $subject,
            'stage' => 'recipients_normalized',
            'recipients' => $normalizedRecipients,
        ]);

        if ($normalizedRecipients === []) {
            $this->appLogger->error('mail.skipped', [
                'reason' => 'no_recipients',
                'subject' => $subject,
                'stage' => 'pre_send_validation',
            ]);

            return false;
        }

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

            $mailer->Subject = $subject;
            $mailer->Body = $htmlBody;
            $mailer->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));

            $this->appLogger->log('mail.dispatch.sending', [
                'subject' => $subject,
                'stage' => 'smtp_send',
            ]);

            $mailer->send();

            $this->appLogger->log('mail.sent', [
                'subject' => $subject,
                'recipients' => $normalizedRecipients,
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
}
