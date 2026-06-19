<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Services\AppLogger;
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
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
        $config = $this->configResolver->resolve($configOverride);

        if (!$this->isConfigured($configOverride)) {
            $this->appLogger->log('mail.skipped', [
                'reason' => 'mail_not_configured',
                'subject' => $subject,
            ]);

            return false;
        }

        $normalizedRecipients = $this->normalizeRecipients($recipients);

        if ($normalizedRecipients === []) {
            $this->appLogger->log('mail.skipped', [
                'reason' => 'no_recipients',
                'subject' => $subject,
            ]);

            return false;
        }

        try {
            $mailer = $this->createMailer($config);

            foreach ($normalizedRecipients as $recipient) {
                $mailer->addAddress($recipient);
            }

            $mailer->Subject = $subject;
            $mailer->Body = $htmlBody;
            $mailer->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));

            $mailer->send();

            return true;
        } catch (MailerException $exception) {
            $this->appLogger->log('mail.failed', [
                'subject' => $subject,
                'recipients' => $normalizedRecipients,
                'error' => $exception->getMessage(),
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
