<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Services\AppLogger;
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
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
    public function __construct(
        private readonly array $config,
        private readonly AppLogger $appLogger
    ) {
    }

    public function isConfigured(): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }

        if ($this->config['host'] === '' || $this->config['from_address'] === '') {
            return false;
        }

        return filter_var($this->config['from_address'], FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param list<string> $recipients
     */
    public function sendHtml(array $recipients, string $subject, string $htmlBody): bool
    {
        if (!$this->isConfigured()) {
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
            $mailer = $this->createMailer();

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

    private function createMailer(): PHPMailer
    {
        $mailer = new PHPMailer(true);
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->isSMTP();
        $mailer->Host = $this->config['host'];
        $mailer->Port = max(1, $this->config['port']);
        $mailer->SMTPAuth = $this->config['username'] !== '';
        $mailer->Username = $this->config['username'];
        $mailer->Password = $this->config['password'];
        $mailer->Timeout = 15;

        $encryption = $this->config['encryption'];

        if ($encryption === 'tls') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mailer->SMTPSecure = '';
            $mailer->SMTPAutoTLS = false;
        }

        $mailer->setFrom($this->config['from_address'], $this->config['from_name']);
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
