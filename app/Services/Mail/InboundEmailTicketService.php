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
     *     replied: int,
     *     skipped_messages: int
     * }
     */
    public function run(): array
    {
        $this->appLogger->log('mail.inbound.start', []);

        try {
            $fetchResult = $this->imapInboxFetcher->fetchUnreadMessages();

            if ($fetchResult['skipped']) {
                return [
                    'success' => false,
                    'skipped' => true,
                    'message' => $fetchResult['message'],
                    'fetched' => 0,
                    'created' => 0,
                    'replied' => 0,
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
                    'replied' => 0,
                    'skipped_messages' => 0,
                ];
            }

            $created = 0;
            $replied = 0;
            $skippedMessages = 0;
            $seenUids = [];

            foreach ($fetchResult['messages'] as $message) {
                $uid = (int) ($message['uid'] ?? 0);
                $from = strtolower(trim((string) ($message['from'] ?? '')));
                $subject = trim((string) ($message['subject'] ?? ''));
                $body = trim((string) ($message['body'] ?? ''));
                $messageId = trim((string) ($message['message_id'] ?? ''));
                $inReplyTo = trim((string) ($message['in_reply_to'] ?? ''));
                /** @var list<string> $references */
                $references = is_array($message['references'] ?? null) ? $message['references'] : [];

                if ($messageId !== '' && $this->ticketModel->emailMessageExists($messageId)) {
                    $skippedMessages++;
                    $seenUids[] = $uid;
                    $this->appLogger->log('mail.inbound.message_skipped', [
                        'reason' => 'duplicate_message_id',
                        'from' => $from,
                        'uid' => $uid,
                        'message_id' => $messageId,
                    ]);
                    continue;
                }

                if ($from === '' || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
                    $skippedMessages++;
                    $seenUids[] = $uid;
                    $this->appLogger->error('mail.inbound.message_skipped', [
                        'reason' => 'invalid_sender',
                        'uid' => $uid,
                        'message_id' => $messageId,
                    ]);
                    continue;
                }

                if ($subject === '') {
                    $subject = __('ticket_email_default_subject');
                }

                if ($body === '') {
                    $body = $subject;
                }

                $matchedTicket = $this->resolveMatchedTicket($inReplyTo, $references, $subject);

                try {
                    if ($matchedTicket !== null) {
                        $ticketId = (int) ($matchedTicket['id'] ?? 0);
                        $comment = $this->ticketModel->addComment(
                            $ticketId,
                            $body,
                            null,
                            $from,
                            false,
                            $messageId !== '' ? $messageId : null,
                            $inReplyTo !== '' ? $inReplyTo : null
                        );

                        if ($messageId !== '') {
                            $this->ticketModel->recordEmailMessage(
                                $ticketId,
                                $messageId,
                                Ticket::EMAIL_DIRECTION_INBOUND,
                                $inReplyTo !== '' ? $inReplyTo : null,
                                (int) ($comment['id'] ?? 0),
                                $from,
                                $subject
                            );
                        }

                        $replied++;
                        $seenUids[] = $uid;
                        $this->appLogger->log('mail.inbound.ticket_replied', [
                            'ticket_id' => $ticketId,
                            'ticket_number' => $matchedTicket['ticket_number'] ?? null,
                            'from' => $from,
                            'uid' => $uid,
                            'message_id' => $messageId,
                        ]);
                        continue;
                    }

                    $person = $this->personnelModel->findByEmail($from);

                    if ($person === null) {
                        $skippedMessages++;
                        $seenUids[] = $uid;
                        $this->appLogger->error('mail.inbound.message_skipped', [
                            'reason' => 'unknown_sender',
                            'from' => $from,
                            'subject' => $subject,
                            'uid' => $uid,
                            'message_id' => $messageId,
                        ]);
                        continue;
                    }

                    $ticket = $this->ticketModel->create(
                        $subject,
                        $body,
                        (int) ($person['id'] ?? 0),
                        null,
                        Ticket::PRIORITY_MEDIUM,
                        null
                    );

                    $ticketId = (int) ($ticket['id'] ?? 0);

                    if ($messageId !== '' && $ticketId > 0) {
                        $referenceHeader = trim(implode(' ', $references));
                        $this->ticketModel->setEmailThreadRoot(
                            $ticketId,
                            $messageId,
                            $referenceHeader !== '' ? $referenceHeader : null
                        );
                        $this->ticketModel->recordEmailMessage(
                            $ticketId,
                            $messageId,
                            Ticket::EMAIL_DIRECTION_INBOUND,
                            $inReplyTo !== '' ? $inReplyTo : null,
                            null,
                            $from,
                            $subject
                        );
                    }

                    $created++;
                    $seenUids[] = $uid;
                    $this->appLogger->log('mail.inbound.ticket_created', [
                        'ticket_id' => $ticket['id'] ?? null,
                        'ticket_number' => $ticket['ticket_number'] ?? null,
                        'from' => $from,
                        'uid' => $uid,
                        'message_id' => $messageId,
                    ]);
                } catch (\Throwable $exception) {
                    $skippedMessages++;
                    $this->appLogger->error('mail.inbound.ticket_create_failed', [
                        'from' => $from,
                        'subject' => $subject,
                        'uid' => $uid,
                        'message_id' => $messageId,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            $this->imapInboxFetcher->markMessagesSeen($seenUids);

            $message = sprintf(
                'Inbound email fetch complete. Fetched %d message(s), created %d ticket(s), replied %d, skipped %d message(s).',
                $fetchResult['fetched'],
                $created,
                $replied,
                $skippedMessages
            );

            $this->appLogger->log('mail.inbound.complete', [
                'fetched' => $fetchResult['fetched'],
                'created' => $created,
                'replied' => $replied,
                'skipped_messages' => $skippedMessages,
            ]);

            return [
                'success' => true,
                'skipped' => false,
                'message' => $message,
                'fetched' => $fetchResult['fetched'],
                'created' => $created,
                'replied' => $replied,
                'skipped_messages' => $skippedMessages,
            ];
        } finally {
            $this->imapInboxFetcher->closeActiveConnection();
        }
    }

    /**
     * @param list<string> $references
     *
     * @return array<string, mixed>|null
     */
    private function resolveMatchedTicket(string $inReplyTo, array $references, string $subject): ?array
    {
        $candidateIds = [];

        if ($inReplyTo !== '') {
            $candidateIds[] = $inReplyTo;
        }

        foreach ($references as $reference) {
            $reference = trim((string) $reference);

            if ($reference !== '') {
                $candidateIds[] = $reference;
            }
        }

        foreach ($candidateIds as $candidateId) {
            $ticket = $this->ticketModel->findByEmailMessageId($candidateId);

            if ($ticket !== null) {
                return $ticket;
            }
        }

        if (preg_match('/HD-\d{4}-\d+/i', $subject, $matches) === 1) {
            return $this->ticketModel->findByTicketNumber(strtoupper($matches[0]));
        }

        return null;
    }
}
