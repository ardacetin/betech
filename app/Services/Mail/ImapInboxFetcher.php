<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Services\AppLogger;

class ImapInboxFetcher
{
    /** @var resource|null */
    private $activeConnection = null;

    public function __construct(
        private readonly ImapConfigResolver $imapConfigResolver,
        private readonly AppLogger $appLogger
    ) {
    }

    /**
     * @return array{
     *     success: bool,
     *     skipped: bool,
     *     message: string,
     *     fetched: int,
     *     messages: list<array{
     *         uid: int,
     *         message_id: string,
     *         in_reply_to: string,
     *         references: list<string>,
     *         from: string,
     *         subject: string,
     *         body: string
     *     }>
     * }
     */
    public function fetchUnreadMessages(): array
    {
        $this->logStep('mail.imap.start', []);
        $this->closeActiveConnection();

        if (!function_exists('imap_open')) {
            $message = 'PHP IMAP extension is not loaded (ext-imap). Inbound email fetching is unavailable.';

            $this->logFailure('mail.imap.extension_missing', $message, []);

            return [
                'success' => false,
                'skipped' => true,
                'message' => $message,
                'fetched' => 0,
                'messages' => [],
            ];
        }

        $config = $this->imapConfigResolver->resolve();

        $this->logStep('mail.imap.config_resolved', [
            'enabled' => $config['enabled'],
            'host' => $config['host'],
            'port' => $config['port'],
            'encryption' => $config['encryption'],
            'mailbox' => $config['mailbox'],
            'username' => $config['username'],
            'support_inbox_address' => $config['support_inbox_address'],
            'password_configured' => $config['password'] !== '',
        ]);

        if (!$this->imapConfigResolver->isConfigured($config)) {
            $message = 'IMAP inbox fetching is not configured. Set MAIL_IMAP_* variables or reuse SMTP credentials with a reachable IMAP host.';

            $this->logFailure('mail.imap.not_configured', $message, [
                'enabled' => $config['enabled'],
                'host' => $config['host'],
                'username' => $config['username'],
            ]);

            return [
                'success' => false,
                'skipped' => true,
                'message' => $message,
                'fetched' => 0,
                'messages' => [],
            ];
        }

        $mailboxPath = $this->imapConfigResolver->buildMailboxPath($config);

        $this->logStep('mail.imap.connecting', [
            'mailbox_path' => $mailboxPath,
            'stage' => 'tcp_handshake',
        ]);

        $previousErrors = imap_errors();
        if (is_array($previousErrors)) {
            imap_errors();
        }

        // Writable connection so processed messages can be marked \\Seen.
        $connection = @imap_open(
            $mailboxPath,
            $config['username'],
            $config['password'],
            0,
            1,
            [
                'DISABLE_AUTHENTICATOR' => 'GSSAPI',
            ]
        );

        if ($connection === false) {
            $errors = imap_errors() ?: [];
            $alerts = imap_alerts() ?: [];
            $lastError = imap_last_error() ?: 'Unknown IMAP error';
            $message = 'IMAP authentication or connection failed: ' . $lastError;

            $this->logFailure('mail.imap.connection_failed', $message, [
                'stage' => 'imap_open',
                'mailbox_path' => $mailboxPath,
                'username' => $config['username'],
                'imap_errors' => $errors,
                'imap_alerts' => $alerts,
                'last_error' => $lastError,
            ]);

            return [
                'success' => false,
                'skipped' => false,
                'message' => $message,
                'fetched' => 0,
                'messages' => [],
            ];
        }

        $this->activeConnection = $connection;

        $this->logStep('mail.imap.connected', [
            'stage' => 'mailbox_selected',
            'mailbox_path' => $mailboxPath,
        ]);

        try {
            $this->logStep('mail.imap.searching', [
                'stage' => 'search_unseen',
                'criteria' => 'UNSEEN',
            ]);

            $messageNumbers = imap_search($connection, 'UNSEEN', SE_UID);

            if ($messageNumbers === false) {
                $this->logStep('mail.imap.search_complete', [
                    'stage' => 'search_unseen',
                    'matched' => 0,
                ]);

                $this->closeActiveConnection();

                return [
                    'success' => true,
                    'skipped' => false,
                    'message' => 'IMAP inbox connected successfully. No unread messages found.',
                    'fetched' => 0,
                    'messages' => [],
                ];
            }

            $messages = [];

            foreach ($messageNumbers as $uid) {
                $uid = (int) $uid;
                $overviewRows = imap_fetch_overview($connection, (string) $uid, FT_UID);
                $overview = is_array($overviewRows) ? ($overviewRows[0] ?? null) : null;

                if ($overview === null) {
                    $this->logFailure('mail.imap.message_header_failed', 'Unable to read IMAP message overview.', [
                        'uid' => $uid,
                        'last_error' => imap_last_error() ?: null,
                    ]);
                    continue;
                }

                $rawHeaders = imap_fetchheader($connection, $uid, FT_UID);
                $headerMap = is_string($rawHeaders) ? $this->parseRawHeaders($rawHeaders) : [];

                $from = $this->parseEmailAddress(isset($overview->from) ? (string) $overview->from : '');
                $subject = $this->decodeMimeHeader(isset($overview->subject) ? (string) $overview->subject : '');
                $messageId = $this->normalizeMessageId(
                    $headerMap['message-id']
                        ?? (isset($overview->message_id) ? (string) $overview->message_id : '')
                );
                $inReplyTo = $this->normalizeMessageId($headerMap['in-reply-to'] ?? '');
                $references = $this->parseReferences($headerMap['references'] ?? '');
                $body = $this->extractMessageBody($connection, $uid);

                $messages[] = [
                    'uid' => $uid,
                    'message_id' => $messageId,
                    'in_reply_to' => $inReplyTo,
                    'references' => $references,
                    'from' => $from,
                    'subject' => $subject,
                    'body' => $body,
                ];

                $this->logStep('mail.imap.message_parsed', [
                    'uid' => $uid,
                    'from' => $from,
                    'subject' => $subject,
                    'message_id' => $messageId,
                    'in_reply_to' => $inReplyTo,
                ]);
            }

            $this->logStep('mail.imap.fetch_complete', [
                'fetched' => count($messages),
            ]);

            return [
                'success' => true,
                'skipped' => false,
                'message' => sprintf('Fetched %d unread message(s) from IMAP inbox.', count($messages)),
                'fetched' => count($messages),
                'messages' => $messages,
            ];
        } catch (\Throwable $exception) {
            $this->logFailure('mail.imap.fetch_failed', $exception->getMessage(), [
                'stage' => 'fetch_loop',
                'exception_class' => $exception::class,
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->closeActiveConnection();

            return [
                'success' => false,
                'skipped' => false,
                'message' => $exception->getMessage(),
                'fetched' => 0,
                'messages' => [],
            ];
        }
    }

    /**
     * @param list<int> $uids
     */
    public function markMessagesSeen(array $uids): void
    {
        if ($this->activeConnection === null || $uids === []) {
            return;
        }

        $uniqueUids = array_values(array_unique(array_filter(
            array_map(static fn ($uid): int => (int) $uid, $uids),
            static fn (int $uid): bool => $uid > 0
        )));

        if ($uniqueUids === []) {
            return;
        }

        $sequence = implode(',', $uniqueUids);
        $result = @imap_setflag_full($this->activeConnection, $sequence, '\\Seen', ST_UID);

        $this->logStep('mail.imap.mark_seen', [
            'uids' => $uniqueUids,
            'success' => $result === true,
            'last_error' => $result === true ? null : (imap_last_error() ?: null),
        ]);
    }

    public function closeActiveConnection(): void
    {
        if ($this->activeConnection === null) {
            return;
        }

        if (is_resource($this->activeConnection)) {
            imap_close($this->activeConnection);
        }

        $this->activeConnection = null;
    }

    /**
     * @param resource $connection
     */
    private function extractMessageBody($connection, int $uid): string
    {
        $structure = imap_fetchstructure($connection, $uid, FT_UID);

        if ($structure === false) {
            $raw = imap_body($connection, $uid, FT_UID);

            return is_string($raw) ? trim($raw) : '';
        }

        $body = $this->decodePartBody($connection, $uid, $structure);

        if ($body !== '') {
            return trim($body);
        }

        if (isset($structure->parts) && is_array($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                $partBody = $this->decodePartBody($connection, $uid, $part, (string) ($index + 1));

                if ($partBody !== '') {
                    return trim($partBody);
                }
            }
        }

        $fallback = imap_body($connection, $uid, FT_UID);

        return is_string($fallback) ? trim($fallback) : '';
    }

    /**
     * @param resource $connection
     */
    private function decodePartBody($connection, int $uid, object $part, string $section = '1'): string
    {
        $data = imap_fetchbody($connection, $uid, $section, FT_UID);

        if (!is_string($data) || $data === '') {
            return '';
        }

        $encoding = (int) ($part->encoding ?? 0);

        $decoded = match ($encoding) {
            ENCBASE64 => base64_decode($data, true),
            ENCQUOTEDPRINTABLE => quoted_printable_decode($data),
            default => $data,
        };

        if (!is_string($decoded)) {
            return '';
        }

        $subtype = strtolower((string) ($part->subtype ?? ''));

        if ($subtype === 'plain' || $subtype === 'html') {
            if ($subtype === 'html') {
                return trim(strip_tags($decoded));
            }

            return trim($decoded);
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    private function parseRawHeaders(string $rawHeaders): array
    {
        $normalized = preg_replace("/\r\n[ \t]+/", ' ', str_replace("\r\n", "\n", $rawHeaders)) ?? $rawHeaders;
        $map = [];

        foreach (explode("\n", $normalized) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $key = strtolower(trim($name));

            if ($key === '') {
                continue;
            }

            $map[$key] = trim($value);
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function parseReferences(string $raw): array
    {
        $parts = preg_split('/\s+/', trim($raw)) ?: [];
        $ids = [];

        foreach ($parts as $part) {
            $messageId = $this->normalizeMessageId($part);

            if ($messageId !== '') {
                $ids[$messageId] = true;
            }
        }

        return array_keys($ids);
    }

    private function normalizeMessageId(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        if ($trimmed[0] !== '<') {
            $trimmed = '<' . $trimmed;
        }

        if (!str_ends_with($trimmed, '>')) {
            $trimmed .= '>';
        }

        return $trimmed;
    }

    private function parseEmailAddress(string $raw): string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return '';
        }

        if (preg_match('/<([^>]+)>/', $raw, $matches) === 1) {
            return strtolower(trim($matches[1]));
        }

        if (filter_var($raw, FILTER_VALIDATE_EMAIL) !== false) {
            return strtolower($raw);
        }

        return '';
    }

    private function decodeMimeHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $decoded = imap_mime_header_decode($value);
        $parts = [];

        if (is_array($decoded)) {
            foreach ($decoded as $part) {
                $parts[] = (string) ($part->text ?? '');
            }
        }

        return trim(implode('', $parts));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logStep(string $event, array $context): void
    {
        $this->appLogger->log($event, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logFailure(string $event, string $message, array $context): void
    {
        $this->appLogger->error($event, array_merge($context, [
            'message' => $message,
        ]));
    }
}
