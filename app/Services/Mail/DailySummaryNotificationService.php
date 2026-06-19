<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Consumable;
use App\Models\License;
use App\Models\Personnel;
use App\Models\Setting;
use App\Models\Ticket;
use App\Services\AppLogger;
use App\Services\ViewRenderer;
use Throwable;

class DailySummaryNotificationService
{
    private const LICENSE_WINDOW_DAYS = 30;
    private const STALE_TICKET_HOURS = 48;

    public function __construct(
        private readonly License $licenseModel,
        private readonly Consumable $consumableModel,
        private readonly Ticket $ticketModel,
        private readonly Setting $settingModel,
        private readonly Personnel $personnelModel,
        private readonly MailService $mailService,
        private readonly ViewRenderer $viewRenderer,
        private readonly AppLogger $appLogger,
        private readonly string $appUrl
    ) {
    }

    /**
     * @return array{success: bool, skipped: bool, message: string}
     */
    public function run(): array
    {
        try {
            $expiringLicenses = $this->licenseModel->findExpiringWithinDays(self::LICENSE_WINDOW_DAYS);
            $lowStockConsumables = $this->consumableModel->findLowStock();
            $staleTickets = $this->ticketModel->findStaleOpenTickets(self::STALE_TICKET_HOURS);

            if ($expiringLicenses === [] && $lowStockConsumables === [] && $staleTickets === []) {
                $this->appLogger->log('notify.daily_summary.skipped', [
                    'reason' => 'no_alerts',
                ]);

                return [
                    'success' => true,
                    'skipped' => true,
                    'message' => 'No critical thresholds detected; email not sent.',
                ];
            }

            if (!$this->mailService->isConfigured()) {
                $this->appLogger->log('notify.daily_summary.failed', [
                    'reason' => 'mail_not_configured',
                    'license_count' => count($expiringLicenses),
                    'consumable_count' => count($lowStockConsumables),
                    'ticket_count' => count($staleTickets),
                ]);

                return [
                    'success' => false,
                    'skipped' => false,
                    'message' => 'SMTP is not configured; unable to send daily summary.',
                ];
            }

            $recipients = $this->resolveRecipients();

            if ($recipients === []) {
                $this->appLogger->log('notify.daily_summary.failed', [
                    'reason' => 'no_recipients',
                    'license_count' => count($expiringLicenses),
                    'consumable_count' => count($lowStockConsumables),
                    'ticket_count' => count($staleTickets),
                ]);

                return [
                    'success' => false,
                    'skipped' => false,
                    'message' => 'No IT admin notification recipients configured.',
                ];
            }

            $subject = __('mail_daily_summary_subject', [
                'date' => date('Y-m-d'),
            ]);

            $html = $this->viewRenderer->render('emails/daily_summary', [
                'heading' => __('mail_daily_summary_heading'),
                'intro' => __('mail_daily_summary_intro'),
                'generatedAtLabel' => __('mail_daily_summary_generated_at'),
                'generatedAt' => date('Y-m-d H:i'),
                'licensesHeading' => __('mail_daily_summary_licenses_heading', [
                    'count' => (string) count($expiringLicenses),
                ]),
                'licenseNameLabel' => __('col_license_name'),
                'licenseVendorLabel' => __('col_license_vendor'),
                'licenseExpirationLabel' => __('col_license_expiration'),
                'licenseSeatsLabel' => __('col_license_seats'),
                'licenses' => $expiringLicenses,
                'consumablesHeading' => __('mail_daily_summary_consumables_heading', [
                    'count' => (string) count($lowStockConsumables),
                ]),
                'consumableNameLabel' => __('col_consumable_name'),
                'consumableQuantityLabel' => __('col_consumable_quantity'),
                'consumableMinStockLabel' => __('col_consumable_min_stock'),
                'consumableLocationLabel' => __('col_consumable_location'),
                'consumables' => $lowStockConsumables,
                'ticketsHeading' => __('mail_daily_summary_tickets_heading', [
                    'count' => (string) count($staleTickets),
                ]),
                'ticketIdLabel' => __('mail_ticket_id_label'),
                'ticketSubjectLabel' => __('ticket_subject_label'),
                'ticketRequesterLabel' => __('col_ticket_requester'),
                'ticketUpdatedLabel' => __('mail_daily_summary_ticket_updated_label'),
                'tickets' => $staleTickets,
                'dashboardUrl' => rtrim($this->appUrl, '/') . '/',
                'ctaLabel' => __('mail_daily_summary_cta'),
                'footer' => __('mail_daily_summary_footer'),
            ], 'emails/layout');

            $sent = $this->mailService->sendHtml($recipients, $subject, $html);

            if (!$sent) {
                $this->appLogger->log('notify.daily_summary.failed', [
                    'reason' => 'mail_send_failed',
                    'recipient_count' => count($recipients),
                    'license_count' => count($expiringLicenses),
                    'consumable_count' => count($lowStockConsumables),
                    'ticket_count' => count($staleTickets),
                ]);

                return [
                    'success' => false,
                    'skipped' => false,
                    'message' => 'Failed to send daily summary email.',
                ];
            }

            $this->appLogger->log('notify.daily_summary.success', [
                'recipient_count' => count($recipients),
                'license_count' => count($expiringLicenses),
                'consumable_count' => count($lowStockConsumables),
                'ticket_count' => count($staleTickets),
            ]);

            return [
                'success' => true,
                'skipped' => false,
                'message' => sprintf(
                    'Daily summary sent to %d recipient(s). Alerts: %d license(s), %d consumable(s), %d ticket(s).',
                    count($recipients),
                    count($expiringLicenses),
                    count($lowStockConsumables),
                    count($staleTickets)
                ),
            ];
        } catch (Throwable $exception) {
            $this->appLogger->log('notify.daily_summary.failed', [
                'reason' => 'exception',
                'error' => $exception->getMessage(),
                'exception_class' => $exception::class,
            ]);

            return [
                'success' => false,
                'skipped' => false,
                'message' => 'Daily summary task failed: ' . $exception->getMessage(),
            ];
        }
    }

    /**
     * @return list<string>
     */
    private function resolveRecipients(): array
    {
        $recipients = [];

        foreach ($this->settingModel->getAdminNotificationEmails() as $email) {
            $recipients[$email] = true;
        }

        foreach ($this->personnelModel->findAdminEmails() as $email) {
            $recipients[$email] = true;
        }

        return array_keys($recipients);
    }
}
