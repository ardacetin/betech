<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Consumable;
use App\Models\IpNetwork;
use App\Models\License;
use App\Models\Personnel;
use App\Models\Setting;
use App\Services\AppLogger;
use App\Services\IpAddressGenerator;
use App\Services\Mail\MailService;
use App\Services\ViewRenderer;
use Throwable;

class HealthScannerNotificationService
{
    private const IP_UTILIZATION_THRESHOLD = 90;
    private const LICENSE_EXPIRY_DAYS = 15;

    public function __construct(
        private readonly IpNetwork $ipNetworkModel,
        private readonly License $licenseModel,
        private readonly Consumable $consumableModel,
        private readonly Setting $settingModel,
        private readonly Personnel $personnelModel,
        private readonly MailService $mailService,
        private readonly TelegramNotifier $telegramNotifier,
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
            $highUtilizationNetworks = $this->ipNetworkModel->findHighUtilization(self::IP_UTILIZATION_THRESHOLD);
            $expiringLicenses = $this->licenseModel->findExpiringWithinDays(self::LICENSE_EXPIRY_DAYS);
            $lowStockConsumables = $this->consumableModel->findLowStock();

            if ($highUtilizationNetworks === [] && $expiringLicenses === [] && $lowStockConsumables === []) {
                $this->appLogger->log('notify.health_scan.skipped', [
                    'reason' => 'no_alerts',
                ]);

                return [
                    'success' => true,
                    'skipped' => true,
                    'message' => 'All systems healthy, no alerts sent',
                ];
            }

            $telegramHtml = $this->buildTelegramHtml(
                $highUtilizationNetworks,
                $expiringLicenses,
                $lowStockConsumables
            );
            $emailHtml = $this->buildEmailHtml(
                $highUtilizationNetworks,
                $expiringLicenses,
                $lowStockConsumables
            );
            $subject = __('mail_health_scan_subject', [
                'date' => date('Y-m-d'),
            ]);

            $telegramConfigured = $this->telegramNotifier->isConfigured();
            $mailConfigured = $this->mailService->isConfigured();
            $recipients = $this->resolveRecipients();

            if (!$telegramConfigured && (!$mailConfigured || $recipients === [])) {
                $this->appLogger->log('notify.health_scan.failed', [
                    'reason' => 'channels_not_configured',
                    'ip_count' => count($highUtilizationNetworks),
                    'license_count' => count($expiringLicenses),
                    'consumable_count' => count($lowStockConsumables),
                ]);

                return [
                    'success' => false,
                    'skipped' => false,
                    'message' => 'Alert thresholds exceeded but neither Telegram nor SMTP admin delivery is configured.',
                ];
            }

            $telegramSent = false;
            $mailSent = false;

            if ($telegramConfigured) {
                $telegramSent = $this->telegramNotifier->sendHtml($telegramHtml);
            }

            if ($mailConfigured && $recipients !== []) {
                $mailSent = $this->mailService->sendHtml($recipients, $subject, $emailHtml);
            }

            if (!$telegramSent && !$mailSent) {
                $this->appLogger->log('notify.health_scan.failed', [
                    'reason' => 'delivery_failed',
                    'ip_count' => count($highUtilizationNetworks),
                    'license_count' => count($expiringLicenses),
                    'consumable_count' => count($lowStockConsumables),
                ]);

                return [
                    'success' => false,
                    'skipped' => false,
                    'message' => 'Health scan alerts detected but delivery failed on all configured channels.',
                ];
            }

            $this->appLogger->log('notify.health_scan.success', [
                'telegram_sent' => $telegramSent,
                'mail_sent' => $mailSent,
                'recipient_count' => count($recipients),
                'ip_count' => count($highUtilizationNetworks),
                'license_count' => count($expiringLicenses),
                'consumable_count' => count($lowStockConsumables),
            ]);

            return [
                'success' => true,
                'skipped' => false,
                'message' => sprintf(
                    'Health scan alerts sent. Telegram: %s, SMTP: %s. Thresholds: %d IP pool(s), %d license(s), %d consumable(s).',
                    $telegramSent ? 'yes' : 'no',
                    $mailSent ? 'yes' : 'no',
                    count($highUtilizationNetworks),
                    count($expiringLicenses),
                    count($lowStockConsumables)
                ),
            ];
        } catch (Throwable $exception) {
            $this->appLogger->log('notify.health_scan.failed', [
                'reason' => 'exception',
                'error' => $exception->getMessage(),
                'exception_class' => $exception::class,
            ]);

            return [
                'success' => false,
                'skipped' => false,
                'message' => 'Health scan task failed: ' . $exception->getMessage(),
            ];
        }
    }

    /**
     * @param list<array<string, mixed>> $networks
     * @param list<array<string, mixed>> $licenses
     * @param list<array<string, mixed>> $consumables
     */
    private function buildTelegramHtml(array $networks, array $licenses, array $consumables): string
    {
        $lines = [
            '🚨 <b>' . TelegramNotifier::escapeHtml(__('mail_health_scan_heading')) . '</b>',
            TelegramNotifier::escapeHtml(__('mail_health_scan_intro')),
            '',
        ];

        if ($networks !== []) {
            $lines[] = '🚨 <b>' . TelegramNotifier::escapeHtml(__('mail_health_scan_ip_heading', [
                'count' => (string) count($networks),
            ])) . '</b>';

            foreach ($networks as $network) {
                $lines[] = '• ' . TelegramNotifier::escapeHtml(sprintf(
                    '%s (%s) — %d%% (%d/%d)',
                    (string) ($network['name'] ?? '-'),
                    (string) ($network['cidr_notation'] ?? '-'),
                    (int) ($network['utilization_percent'] ?? 0),
                    (int) ($network['used_ips'] ?? 0),
                    (int) ($network['capacity_ips'] ?? 0)
                ));
            }

            $lines[] = '';
        }

        if ($licenses !== []) {
            $lines[] = '🚨 <b>' . TelegramNotifier::escapeHtml(__('mail_health_scan_licenses_heading', [
                'count' => (string) count($licenses),
            ])) . '</b>';

            foreach ($licenses as $license) {
                $lines[] = '• ' . TelegramNotifier::escapeHtml(sprintf(
                    '%s — %s (%s)',
                    (string) ($license['name'] ?? '-'),
                    (string) ($license['vendor'] ?? '-'),
                    (string) ($license['expiration_date'] ?? '-')
                ));
            }

            $lines[] = '';
        }

        if ($consumables !== []) {
            $lines[] = '🚨 <b>' . TelegramNotifier::escapeHtml(__('mail_health_scan_consumables_heading', [
                'count' => (string) count($consumables),
            ])) . '</b>';

            foreach ($consumables as $consumable) {
                $lines[] = '• ' . TelegramNotifier::escapeHtml(sprintf(
                    '%s — %d / min %d',
                    (string) ($consumable['name'] ?? '-'),
                    (int) ($consumable['quantity'] ?? 0),
                    (int) ($consumable['min_stock_level'] ?? 0)
                ));
            }

            $lines[] = '';
        }

        $lines[] = '🔗 <a href="' . TelegramNotifier::escapeHtml(rtrim($this->appUrl, '/') . '/') . '">'
            . TelegramNotifier::escapeHtml(__('mail_health_scan_cta')) . '</a>';

        return implode("\n", $lines);
    }

    /**
     * @param list<array<string, mixed>> $networks
     * @param list<array<string, mixed>> $licenses
     * @param list<array<string, mixed>> $consumables
     */
    private function buildEmailHtml(array $networks, array $licenses, array $consumables): string
    {
        return $this->viewRenderer->render('emails/health_scan', [
            'heading' => __('mail_health_scan_heading'),
            'intro' => __('mail_health_scan_intro'),
            'generatedAtLabel' => __('mail_daily_summary_generated_at'),
            'generatedAt' => date('Y-m-d H:i'),
            'ipHeading' => __('mail_health_scan_ip_heading', [
                'count' => (string) count($networks),
            ]),
            'ipNameLabel' => __('col_ip_network_name'),
            'ipCidrLabel' => __('col_ip_network_cidr'),
            'ipUtilizationLabel' => __('col_ip_utilization'),
            'networks' => $networks,
            'licensesHeading' => __('mail_health_scan_licenses_heading', [
                'count' => (string) count($licenses),
            ]),
            'licenseNameLabel' => __('col_license_name'),
            'licenseVendorLabel' => __('col_license_vendor'),
            'licenseExpirationLabel' => __('col_license_expiration'),
            'licenses' => $licenses,
            'consumablesHeading' => __('mail_health_scan_consumables_heading', [
                'count' => (string) count($consumables),
            ]),
            'consumableNameLabel' => __('col_consumable_name'),
            'consumableQuantityLabel' => __('col_consumable_quantity'),
            'consumableMinStockLabel' => __('col_consumable_min_stock'),
            'consumables' => $consumables,
            'dashboardUrl' => rtrim($this->appUrl, '/') . '/',
            'ctaLabel' => __('mail_health_scan_cta'),
            'footer' => __('mail_health_scan_footer'),
        ], 'emails/layout');
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
