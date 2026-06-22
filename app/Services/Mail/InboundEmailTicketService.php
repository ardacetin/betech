<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Personnel;
use App\Models\Ticket;
use App\Services\AppLogger;

class InboundEmailTicketService
{
    public function __construct(
        private readonly ImapInboxFetcher $imapInboxFetcher,
        private readonly Personnel $personnelModel,
        private readonly Ticket $ticketModel,
        private readonly AppLogger $appLogger
    ) {
    }

    /**
     * @return array{
     *     success: bool,
     *     skipped: bool,
     *     message: string,
     *     fetched: int,
     *     created: int,
     *     skipped_messages: int
     * }
     */
    public function run(): array
    {
        $this->appLogger->log('mail.inbound.start', []);

        $fetchResult = $this->imapInboxFetcher->fetchUnreadMessages();

        if ($fetchResult['skipped']) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => $fetchResult['message'],
                'fetched' => 0,
                'created' => 0,
                'skipped_messages' => 0,
            ];
        }

        if (!$fetchResult['success']) {
            return [
                'success' => false,
                'skipped' => false,
                'message' => $fetchResult['message'],
                'fetched' => 0,
                'created' => 0,
                'skipped_messages' => 0,
            ];
        }

        $created = 0;
        $skippedMessages = 0;

        foreach ($fetchResult['messages'] as $message) {
            $from = strtolower(trim((string) ($message['from'] ?? '')));
            $subject = trim((string) ($message['subject'] ?? ''));
            $body = trim((string) ($message['body'] ?? ''));

            if ($from === '' || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
                $skippedMessages++;
                $this->appLogger->error('mail.inbound.message_skipped', [
                    'reason' => 'invalid_sender',
                    'uid' => $message['uid'] ?? null,
                    'message_id' => $message['message_id'] ?? null,
                ]);
                continue;
            }

            $person = $this->personnelModel->findByEmail($from);

            if ($person === null) {
                $skippedMessages++;
                $this->appLogger->error('mail.inbound.message_skipped', [
                    'reason' => 'unknown_sender',
                    'from' => $from,
                    'subject' => $subject,
                    'uid' => $message['uid'] ?? null,
                    'message_id' => $message['message_id'] ?? null,
                ]);
                continue;
            }

            if ($subject === '') {
                $subject = __('ticket_email_default_subject');
            }

            if ($body === '') {
                $body = $subject;
            }

            try {
                $ticket = $this->ticketModel->create(
                    $subject,
                    $body,
                    (int) ($person['id'] ?? 0),
                    null,
                    Ticket::PRIORITY_MEDIUM,
                    null
                );

                $created++;
                $this->appLogger->log('mail.inbound.ticket_created', [
                    'ticket_id' => $ticket['id'] ?? null,
                    'ticket_number' => $ticket['ticket_number'] ?? null,
                    'from' => $from,
                    'uid' => $message['uid'] ?? null,
                    'message_id' => $message['message_id'] ?? null,
                ]);
            } catch (\Throwable $exception) {
                $skippedMessages++;
                $this->appLogger->error('mail.inbound.ticket_create_failed', [
                    'from' => $from,
                    'subject' => $subject,
                    'uid' => $message['uid'] ?? null,
                    'message_id' => $message['message_id'] ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $message = sprintf(
            'Inbound email fetch complete. Fetched %d message(s), created %d ticket(s), skipped %d message(s).',
            $fetchResult['fetched'],
            $created,
            $skippedMessages
        );

        $this->appLogger->log('mail.inbound.complete', [
            'fetched' => $fetchResult['fetched'],
            'created' => $created,
            'skipped_messages' => $skippedMessages,
        ]);

        return [
            'success' => true,
            'skipped' => false,
            'message' => $message,
            'fetched' => $fetchResult['fetched'],
            'created' => $created,
            'skipped_messages' => $skippedMessages,
        ];
    }
}
