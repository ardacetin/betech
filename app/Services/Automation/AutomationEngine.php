<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\Asset;
use App\Models\AutomationRule;
use App\Models\Consumable;
use App\Models\License;
use App\Models\Personnel;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AppLogger;
use App\Services\Mail\MailConfigResolver;
use App\Services\Mail\MailService;
use App\Services\ViewRenderer;

class AutomationEngine
{
    public function __construct(
        private readonly AutomationRule $automationRuleModel,
        private readonly License $licenseModel,
        private readonly Consumable $consumableModel,
        private readonly Asset $assetModel,
        private readonly Setting $settingModel,
        private readonly Personnel $personnelModel,
        private readonly User $userModel,
        private readonly MailService $mailService,
        private readonly MailConfigResolver $mailConfigResolver,
        private readonly ViewRenderer $viewRenderer,
        private readonly AppLogger $appLogger,
        private readonly string $appUrl
    ) {
    }

    /**
     * @return array{
     *     success: bool,
     *     skipped: bool,
     *     message: string,
     *     rules_evaluated: int,
     *     emails_sent: int,
     *     firings: int
     * }
     */
    public function runScheduled(): array
    {
        if (!$this->automationRuleModel->tableReady()) {
            return [
                'success' => false,
                'skipped' => true,
                'message' => 'Automation tables are not ready.',
                'rules_evaluated' => 0,
                'emails_sent' => 0,
                'firings' => 0,
            ];
        }

        $this->automationRuleModel->seedDefaultsIfEmpty();

        if (!$this->mailService->isConfigured()) {
            return [
                'success' => false,
                'skipped' => false,
                'message' => 'SMTP is not configured; automation emails were not sent.',
                'rules_evaluated' => 0,
                'emails_sent' => 0,
                'firings' => 0,
            ];
        }

        $rules = $this->automationRuleModel->findEnabledByTypes(AutomationRule::SCHEDULED_TYPES);
        $emailsSent = 0;
        $firings = 0;

        foreach ($rules as $rule) {
            $result = match ((string) $rule['rule_type']) {
                AutomationRule::TYPE_LICENSE_EXPIRING => $this->evaluateLicenseRule($rule),
                AutomationRule::TYPE_WARRANTY_EXPIRING => $this->evaluateWarrantyRule($rule),
                AutomationRule::TYPE_CONSUMABLE_LOW_STOCK => $this->evaluateConsumableRule($rule),
                default => ['emails_sent' => 0, 'firings' => 0],
            };

            $emailsSent += (int) ($result['emails_sent'] ?? 0);
            $firings += (int) ($result['firings'] ?? 0);
            $this->automationRuleModel->markRun((int) $rule['id']);
        }

        $message = sprintf(
            'Automation run complete. Evaluated %d rule(s), recorded %d firing(s), sent %d email(s).',
            count($rules),
            $firings,
            $emailsSent
        );

        $this->appLogger->log('automation.scheduled.complete', [
            'rules_evaluated' => count($rules),
            'firings' => $firings,
            'emails_sent' => $emailsSent,
        ]);

        return [
            'success' => true,
            'skipped' => $rules === [],
            'message' => $message,
            'rules_evaluated' => count($rules),
            'emails_sent' => $emailsSent,
            'firings' => $firings,
        ];
    }

    /**
     * @param array<string, mixed> $ticket
     */
    public function handleTicketCreated(array $ticket): void
    {
        if (!$this->automationRuleModel->tableReady()) {
            return;
        }

        $this->automationRuleModel->seedDefaultsIfEmpty();

        $priority = strtolower(trim((string) ($ticket['priority'] ?? '')));
        $ticketId = (int) ($ticket['id'] ?? 0);

        if ($ticketId <= 0 || $priority === '') {
            return;
        }

        $rules = $this->automationRuleModel->findEnabledByTypes([AutomationRule::TYPE_TICKET_PRIORITY]);

        foreach ($rules as $rule) {
            $expected = strtolower(trim((string) ($rule['config']['priority'] ?? Ticket::PRIORITY_CRITICAL)));

            if ($expected !== $priority) {
                continue;
            }

            $dedupeKey = sprintf('ticket:%d', $ticketId);

            if ($this->automationRuleModel->hasFired((int) $rule['id'], $dedupeKey)) {
                continue;
            }

            $recipients = $this->resolveRecipients($rule);

            if ($recipients === [] || !$this->mailService->isConfigured()) {
                $this->appLogger->log('automation.ticket.skipped', [
                    'rule_id' => $rule['id'],
                    'ticket_id' => $ticketId,
                    'reason' => $recipients === [] ? 'no_recipients' : 'mail_not_configured',
                ]);
                continue;
            }

            $subject = __('automation_mail_ticket_subject', [
                'ticket_number' => (string) ($ticket['ticket_number'] ?? ''),
                'priority' => $priority,
            ]);

            $html = $this->renderAlertEmail(
                __('automation_mail_ticket_heading'),
                __('automation_mail_ticket_intro', [
                    'priority' => $priority,
                    'ticket_number' => (string) ($ticket['ticket_number'] ?? ''),
                ]),
                [
                    ['label' => __('mail_ticket_id_label'), 'value' => (string) ($ticket['ticket_number'] ?? '')],
                    ['label' => __('ticket_subject_label'), 'value' => (string) ($ticket['subject'] ?? '')],
                    ['label' => __('ticket_priority_label'), 'value' => $priority],
                    ['label' => __('col_ticket_requester'), 'value' => (string) ($ticket['personnel_name'] ?? $ticket['personnel_email'] ?? '')],
                ],
                $this->appUrl . '/?ticket=' . $ticketId
            );

            if ($this->mailService->sendHtml($recipients, $subject, $html)) {
                $this->automationRuleModel->recordFiring(
                    (int) $rule['id'],
                    $dedupeKey,
                    'ticket',
                    $ticketId,
                    $subject
                );
                $this->automationRuleModel->markRun((int) $rule['id']);
            }
        }
    }

    /**
     * @param array<string, mixed> $rule
     *
     * @return array{emails_sent: int, firings: int}
     */
    private function evaluateLicenseRule(array $rule): array
    {
        $days = max(1, (int) ($rule['config']['days'] ?? 30));
        $licenses = $this->licenseModel->findExpiringWithinDays($days);
        $recipients = $this->resolveRecipients($rule);
        $emailsSent = 0;
        $firings = 0;

        if ($licenses === [] || $recipients === []) {
            return compact('emailsSent', 'firings');
        }

        $pending = [];

        foreach ($licenses as $license) {
            $licenseId = (int) ($license['id'] ?? 0);
            $dedupeKey = sprintf('license:%d:%s', $licenseId, date('Y-m-d'));

            if ($licenseId <= 0 || $this->automationRuleModel->hasFired((int) $rule['id'], $dedupeKey)) {
                continue;
            }

            $pending[] = ['license' => $license, 'dedupe_key' => $dedupeKey, 'id' => $licenseId];
        }

        if ($pending === []) {
            return compact('emailsSent', 'firings');
        }

        $rows = array_map(static function (array $item): array {
            $license = $item['license'];

            return [
                'label' => (string) ($license['name'] ?? ''),
                'value' => trim((string) ($license['vendor'] ?? '') . ' · ' . (string) ($license['expiration_date'] ?? '')),
            ];
        }, $pending);

        $subject = __('automation_mail_license_subject', ['days' => (string) $days, 'count' => (string) count($pending)]);
        $html = $this->renderAlertEmail(
            __('automation_mail_license_heading'),
            __('automation_mail_license_intro', ['days' => (string) $days]),
            $rows,
            rtrim($this->appUrl, '/') . '/'
        );

        if ($this->mailService->sendHtml($recipients, $subject, $html)) {
            $emailsSent = 1;

            foreach ($pending as $item) {
                $this->automationRuleModel->recordFiring(
                    (int) $rule['id'],
                    $item['dedupe_key'],
                    'license',
                    $item['id'],
                    (string) ($item['license']['name'] ?? '')
                );
                $firings++;
            }
        }

        return compact('emailsSent', 'firings');
    }

    /**
     * @param array<string, mixed> $rule
     *
     * @return array{emails_sent: int, firings: int}
     */
    private function evaluateWarrantyRule(array $rule): array
    {
        $days = max(1, (int) ($rule['config']['days'] ?? 60));
        $assets = $this->assetModel->findWarrantyExpiringWithinDays($days);
        $recipients = $this->resolveRecipients($rule);
        $emailsSent = 0;
        $firings = 0;

        if ($assets === [] || $recipients === []) {
            return compact('emailsSent', 'firings');
        }

        $pending = [];

        foreach ($assets as $asset) {
            $assetId = (int) ($asset['id'] ?? 0);
            $dedupeKey = sprintf('warranty:%d:%s', $assetId, date('Y-m-d'));

            if ($assetId <= 0 || $this->automationRuleModel->hasFired((int) $rule['id'], $dedupeKey)) {
                continue;
            }

            $pending[] = ['asset' => $asset, 'dedupe_key' => $dedupeKey, 'id' => $assetId];
        }

        if ($pending === []) {
            return compact('emailsSent', 'firings');
        }

        $rows = array_map(static function (array $item): array {
            $asset = $item['asset'];

            return [
                'label' => (string) ($asset['asset_tag'] ?? '') . ' · ' . (string) ($asset['name'] ?? ''),
                'value' => (string) ($asset['warranty_expires_at'] ?? ''),
            ];
        }, $pending);

        $subject = __('automation_mail_warranty_subject', ['days' => (string) $days, 'count' => (string) count($pending)]);
        $html = $this->renderAlertEmail(
            __('automation_mail_warranty_heading'),
            __('automation_mail_warranty_intro', ['days' => (string) $days]),
            $rows,
            rtrim($this->appUrl, '/') . '/'
        );

        if ($this->mailService->sendHtml($recipients, $subject, $html)) {
            $emailsSent = 1;

            foreach ($pending as $item) {
                $this->automationRuleModel->recordFiring(
                    (int) $rule['id'],
                    $item['dedupe_key'],
                    'asset',
                    $item['id'],
                    (string) ($item['asset']['asset_tag'] ?? '')
                );
                $firings++;
            }
        }

        return compact('emailsSent', 'firings');
    }

    /**
     * @param array<string, mixed> $rule
     *
     * @return array{emails_sent: int, firings: int}
     */
    private function evaluateConsumableRule(array $rule): array
    {
        $items = $this->consumableModel->findLowStock();
        $recipients = $this->resolveRecipients($rule);
        $emailsSent = 0;
        $firings = 0;

        if ($items === [] || $recipients === []) {
            return compact('emailsSent', 'firings');
        }

        $pending = [];

        foreach ($items as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            $dedupeKey = sprintf('consumable:%d:%s', $itemId, date('Y-m-d'));

            if ($itemId <= 0 || $this->automationRuleModel->hasFired((int) $rule['id'], $dedupeKey)) {
                continue;
            }

            $pending[] = ['item' => $item, 'dedupe_key' => $dedupeKey, 'id' => $itemId];
        }

        if ($pending === []) {
            return compact('emailsSent', 'firings');
        }

        $rows = array_map(static function (array $entry): array {
            $item = $entry['item'];

            return [
                'label' => (string) ($item['name'] ?? ''),
                'value' => sprintf(
                    '%s / %s',
                    (string) ($item['quantity'] ?? '0'),
                    (string) ($item['min_stock_level'] ?? '0')
                ),
            ];
        }, $pending);

        $subject = __('automation_mail_consumable_subject', ['count' => (string) count($pending)]);
        $html = $this->renderAlertEmail(
            __('automation_mail_consumable_heading'),
            __('automation_mail_consumable_intro'),
            $rows,
            rtrim($this->appUrl, '/') . '/'
        );

        if ($this->mailService->sendHtml($recipients, $subject, $html)) {
            $emailsSent = 1;

            foreach ($pending as $entry) {
                $this->automationRuleModel->recordFiring(
                    (int) $rule['id'],
                    $entry['dedupe_key'],
                    'consumable',
                    $entry['id'],
                    (string) ($entry['item']['name'] ?? '')
                );
                $firings++;
            }
        }

        return compact('emailsSent', 'firings');
    }

    /**
     * @param array<string, mixed> $rule
     *
     * @return list<string>
     */
    private function resolveRecipients(array $rule): array
    {
        $mode = (string) ($rule['recipient_mode'] ?? AutomationRule::RECIPIENT_ADMINS);
        $emails = [];

        if ($mode === AutomationRule::RECIPIENT_CUSTOM) {
            foreach (preg_split('/[\s,;]+/', (string) ($rule['custom_recipients'] ?? '')) ?: [] as $entry) {
                $email = strtolower(trim($entry));

                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                    $emails[$email] = true;
                }
            }

            return array_keys($emails);
        }

        if ($mode === AutomationRule::RECIPIENT_SUPPORT) {
            $config = $this->mailConfigResolver->resolve();

            foreach ($config['support_addresses'] as $address) {
                $email = strtolower(trim((string) $address));

                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                    $emails[$email] = true;
                }
            }

            foreach ($this->userModel->findOperationalEmails() as $email) {
                $emails[strtolower(trim($email))] = true;
            }

            return array_keys($emails);
        }

        foreach ($this->settingModel->getAdminNotificationEmails() as $email) {
            $emails[strtolower(trim($email))] = true;
        }

        foreach ($this->personnelModel->findAdminEmails() as $email) {
            $emails[strtolower(trim($email))] = true;
        }

        return array_keys($emails);
    }

    /**
     * @param list<array{label: string, value: string}> $rows
     */
    private function renderAlertEmail(string $heading, string $intro, array $rows, string $ctaUrl): string
    {
        return $this->viewRenderer->render('emails/automation_alert', [
            'heading' => $heading,
            'intro' => $intro,
            'rows' => $rows,
            'ctaUrl' => $ctaUrl,
            'ctaLabel' => __('mail_ticket_view_cta'),
            'footer' => __('mail_ticket_footer'),
        ], 'emails/layout');
    }
}
