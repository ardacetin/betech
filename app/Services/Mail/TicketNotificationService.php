<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\User;
use App\Services\AppLogger;
use App\Services\DeferredTaskRunner;
use App\Services\ViewRenderer;

class TicketNotificationService
{
    /**
     * All outbound ticket alerts are queued via DeferredTaskRunner so SMTP/Telegram
     * never block the JSON API response or hold the PHP session lock.
     */
    public function __construct(
        private readonly MailService $mailService,
        private readonly MailConfigResolver $mailConfigResolver,
        private readonly ViewRenderer $viewRenderer,
        private readonly User $userModel,
        private readonly AppLogger $appLogger,
        private readonly string $appUrl
    ) {
    }

    /**
     * @param array<string, mixed> $ticket
     */
    public function deferNewTicketAlert(array $ticket): void
    {
        DeferredTaskRunner::defer(function () use ($ticket): void {
            $this->sendNewTicketAlert($ticket);
        });
    }

    /**
     * @param array<string, mixed> $ticket
     */
    public function deferStatusChangeAlert(array $ticket, string $previousStatus): void
    {
        DeferredTaskRunner::defer(function () use ($ticket, $previousStatus): void {
            $this->sendStatusChangeAlert($ticket, $previousStatus);
        });
    }

    /**
     * @param array<string, mixed> $ticket
     * @param array<string, mixed> $comment
     */
    public function deferStaffReplyAlert(array $ticket, array $comment): void
    {
        DeferredTaskRunner::defer(function () use ($ticket, $comment): void {
            $this->sendStaffReplyAlert($ticket, $comment);
        });
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function sendNewTicketAlert(array $ticket): void
    {
        try {
            $recipients = $this->resolveSupportRecipients();

            if ($recipients === []) {
                $this->appLogger->log('mail.ticket_new.skipped', [
                    'ticket_id' => $ticket['id'] ?? null,
                    'reason' => 'no_support_recipients',
                ]);

                return;
            }

            $subject = __('mail_ticket_new_subject', [
                'ticket_number' => (string) ($ticket['ticket_number'] ?? ''),
            ]);

            $html = $this->viewRenderer->render('emails/ticket_new_support', [
                'ticket' => $ticket,
                'ticketUrl' => $this->ticketUrl((int) ($ticket['id'] ?? 0)),
                'heading' => __('mail_ticket_new_heading'),
                'intro' => __('mail_ticket_new_intro'),
                'ticketIdLabel' => __('mail_ticket_id_label'),
                'subjectLabel' => __('ticket_subject_label'),
                'requesterLabel' => __('col_ticket_requester'),
                'priorityLabel' => __('ticket_priority_label'),
                'statusLabel' => __('ticket_status_label'),
                'descriptionLabel' => __('ticket_description_label'),
                'ctaLabel' => __('mail_ticket_view_cta'),
                'footer' => __('mail_ticket_footer'),
            ], 'emails/layout');

            $this->mailService->sendHtml($recipients, $subject, $html);
        } catch (\Throwable $exception) {
            $this->appLogger->log('mail.ticket_new.failed', [
                'ticket_id' => $ticket['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function sendStatusChangeAlert(array $ticket, string $previousStatus): void
    {
        try {
            $recipient = strtolower(trim((string) ($ticket['personnel_email'] ?? '')));

            if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                $this->appLogger->log('mail.ticket_status.skipped', [
                    'ticket_id' => $ticket['id'] ?? null,
                    'reason' => 'missing_requester_email',
                ]);

                return;
            }

            $subject = __('mail_ticket_status_subject', [
                'ticket_number' => (string) ($ticket['ticket_number'] ?? ''),
            ]);

            $html = $this->viewRenderer->render('emails/ticket_update_requester', [
                'ticket' => $ticket,
                'ticketUrl' => $this->ticketUrl((int) ($ticket['id'] ?? 0)),
                'heading' => __('mail_ticket_status_heading'),
                'intro' => __('mail_ticket_status_intro', [
                    'previous_status' => $this->statusLabel($previousStatus),
                    'new_status' => $this->statusLabel((string) ($ticket['status'] ?? '')),
                ]),
                'ticketIdLabel' => __('mail_ticket_id_label'),
                'subjectLabel' => __('ticket_subject_label'),
                'statusLabel' => __('ticket_status_label'),
                'ctaLabel' => __('mail_ticket_view_cta'),
                'footer' => __('mail_ticket_footer'),
                'detailHtml' => '',
            ], 'emails/layout');

            $this->mailService->sendHtml([$recipient], $subject, $html);
        } catch (\Throwable $exception) {
            $this->appLogger->log('mail.ticket_status.failed', [
                'ticket_id' => $ticket['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $ticket
     * @param array<string, mixed> $comment
     */
    private function sendStaffReplyAlert(array $ticket, array $comment): void
    {
        try {
            $recipient = strtolower(trim((string) ($ticket['personnel_email'] ?? '')));

            if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                $this->appLogger->log('mail.ticket_reply.skipped', [
                    'ticket_id' => $ticket['id'] ?? null,
                    'reason' => 'missing_requester_email',
                ]);

                return;
            }

            $subject = __('mail_ticket_reply_subject', [
                'ticket_number' => (string) ($ticket['ticket_number'] ?? ''),
            ]);

            $authorName = trim((string) ($comment['author_name'] ?? __('ticket_comment_system_author')));
            $commentBody = trim((string) ($comment['body'] ?? ''));

            $html = $this->viewRenderer->render('emails/ticket_update_requester', [
                'ticket' => $ticket,
                'ticketUrl' => $this->ticketUrl((int) ($ticket['id'] ?? 0)),
                'heading' => __('mail_ticket_reply_heading'),
                'intro' => __('mail_ticket_reply_intro', [
                    'author' => $authorName,
                ]),
                'ticketIdLabel' => __('mail_ticket_id_label'),
                'subjectLabel' => __('ticket_subject_label'),
                'statusLabel' => __('ticket_status_label'),
                'ctaLabel' => __('mail_ticket_view_cta'),
                'footer' => __('mail_ticket_footer'),
                'detailHtml' => $this->viewRenderer->render('emails/partials/comment_block', [
                    'replyLabel' => __('mail_ticket_reply_label'),
                    'authorName' => $authorName,
                    'commentBody' => $commentBody,
                ], null),
            ], 'emails/layout');

            $this->mailService->sendHtml([$recipient], $subject, $html);
        } catch (\Throwable $exception) {
            $this->appLogger->log('mail.ticket_reply.failed', [
                'ticket_id' => $ticket['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function resolveSupportRecipients(): array
    {
        $config = $this->mailConfigResolver->resolve();
        $recipients = [];

        foreach ($config['support_addresses'] as $address) {
            $email = strtolower(trim($address));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $recipients[$email] = true;
            }
        }

        foreach ($this->userModel->findOperationalEmails() as $email) {
            $recipients[$email] = true;
        }

        return array_keys($recipients);
    }

    private function ticketUrl(int $ticketId): string
    {
        $baseUrl = rtrim($this->appUrl, '/');

        if ($ticketId <= 0) {
            return $baseUrl . '/';
        }

        return $baseUrl . '/?ticket=' . $ticketId;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'in_progress' => __('ticket_status_in_progress'),
            'resolved' => __('ticket_status_resolved'),
            'closed' => __('ticket_status_closed'),
            default => __('ticket_status_open'),
        };
    }
}
