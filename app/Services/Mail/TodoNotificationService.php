<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\User;
use App\Services\AppLogger;
use App\Services\DeferredTaskRunner;
use App\Services\ViewRenderer;

class TodoNotificationService
{
    public function __construct(
        private readonly MailService $mailService,
        private readonly ViewRenderer $viewRenderer,
        private readonly User $userModel,
        private readonly AppLogger $appLogger,
        private readonly string $appUrl
    ) {
    }

    /**
     * @param array<string, mixed> $card
     */
    public function deferCreatedAlert(array $card): void
    {
        DeferredTaskRunner::defer(function () use ($card): void {
            $this->sendAlert($card, false);
        });
    }

    /**
     * @param array<string, mixed> $card
     */
    public function deferAssignmentAlert(array $card): void
    {
        DeferredTaskRunner::defer(function () use ($card): void {
            $this->sendAlert($card, true);
        });
    }

    /**
     * @param array<string, mixed> $card
     */
    private function sendAlert(array $card, bool $assignmentOnly): void
    {
        try {
            $recipients = $this->resolveRecipients($card, $assignmentOnly);

            if ($recipients === []) {
                $this->appLogger->log('mail.todo.skipped', [
                    'todo_id' => $card['id'] ?? null,
                    'reason' => 'no_recipients',
                ]);
                return;
            }

            $subject = $assignmentOnly
                ? __('todo_mail_assigned_subject', ['title' => (string) ($card['title'] ?? '')])
                : __('todo_mail_created_subject', ['title' => (string) ($card['title'] ?? '')]);

            $html = $this->viewRenderer->render('emails/todo_assignment', [
                'card' => $card,
                'cardUrl' => rtrim($this->appUrl, '/') . '/todo?card=' . (int) ($card['id'] ?? 0),
                'heading' => $assignmentOnly ? __('todo_mail_assigned_heading') : __('todo_mail_created_heading'),
                'intro' => $assignmentOnly ? __('todo_mail_assigned_intro') : __('todo_mail_created_intro'),
            ], 'emails/layout');

            if (!$this->mailService->sendHtml($recipients, $subject, $html)) {
                $this->appLogger->error('mail.todo.failed', [
                    'todo_id' => $card['id'] ?? null,
                    'reason' => 'smtp_send_returned_false',
                    'recipients' => $recipients,
                ]);
            }
        } catch (\Throwable $exception) {
            $this->appLogger->error('mail.todo.failed', [
                'todo_id' => $card['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $card
     *
     * @return list<string>
     */
    private function resolveRecipients(array $card, bool $assignmentOnly): array
    {
        $assignedEmail = strtolower(trim((string) ($card['assigned_user_email'] ?? '')));

        if ($assignedEmail !== '' && filter_var($assignedEmail, FILTER_VALIDATE_EMAIL) !== false) {
            return [$assignedEmail];
        }

        return $assignmentOnly ? [] : $this->userModel->findOperationalEmails();
    }
}
