<?php

declare(strict_types=1);

/**
 * @var string $heading
 * @var string $intro
 * @var string $generatedAtLabel
 * @var string $generatedAt
 * @var string $ipHeading
 * @var string $ipNameLabel
 * @var string $ipCidrLabel
 * @var string $ipUtilizationLabel
 * @var list<array<string, mixed>> $networks
 * @var string $licensesHeading
 * @var string $licenseNameLabel
 * @var string $licenseVendorLabel
 * @var string $licenseExpirationLabel
 * @var list<array<string, mixed>> $licenses
 * @var string $consumablesHeading
 * @var string $consumableNameLabel
 * @var string $consumableQuantityLabel
 * @var string $consumableMinStockLabel
 * @var list<array<string, mixed>> $consumables
 * @var string $dashboardUrl
 * @var string $ctaLabel
 * @var string $footer
 */

$formatDate = static function (?string $value): string {
    $value = trim((string) $value);

    if ($value === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false ? date('Y-m-d', $timestamp) : $value;
};
?>
<h1 style="margin:0 0 12px 0;font-size:22px;line-height:1.3;color:#18181b;">🚨 <?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
<p style="margin:0 0 8px 0;font-size:14px;line-height:1.6;color:#52525b;"><?= htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') ?></p>
<p style="margin:0 0 24px 0;font-size:12px;line-height:1.5;color:#71717a;"><?= htmlspecialchars($generatedAtLabel, ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') ?></p>

<?php if ($networks !== []): ?>
<h2 style="margin:0 0 10px 0;font-size:16px;color:#b45309;"><?= htmlspecialchars($ipHeading, ENT_QUOTES, 'UTF-8') ?></h2>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px 0;border-collapse:collapse;">
    <tr>
        <th align="left" style="padding:8px 10px;border-bottom:1px solid #e4e4e7;font-size:12px;color:#71717a;"><?= htmlspecialchars($ipNameLabel, ENT_QUOTES, 'UTF-8') ?></th>
        <th align="left" style="padding:8px 10px;border-bottom:1px solid #e4e4e7;font-size:12px;color:#71717a;"><?= htmlspecialchars($ipCidrLabel, ENT_QUOTES, 'UTF-8') ?></th>
        <th align="left" style="padding:8px 10px;border-bottom:1px solid #e4e4e7;font-size:12px;color:#71717a;"><?= htmlspecialchars($ipUtilizationLabel, ENT_QUOTES, 'UTF-8') ?></th>
    </tr>
    <?php foreach ($networks as $network): ?>
    <tr>
        <td style="padding:8px 10px;border-bottom:1px solid #f4f4f5;font-size:13px;"><?= htmlspecialchars((string) ($network['name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
        <td style="padding:8px 10px;border-bottom:1px solid #f4f4f5;font-size:13px;"><?= htmlspecialchars((string) ($network['cidr_notation'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
        <td style="padding:8px 10px;border-bottom:1px solid #f4f4f5;font-size:13px;color:#b45309;font-weight:600;"><?= (int) ($network['utilization_percent'] ?? 0) ?>% (<?= (int) ($network['used_ips'] ?? 0) ?>/<?= (int) ($network['capacity_ips'] ?? 0) ?>)</td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if ($licenses !== []): ?>
<h2 style="margin:0 0 10px 0;font-size:16px;color:#b45309;"><?= htmlspecialchars($licensesHeading, ENT_QUOTES, 'UTF-8') ?></h2>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px 0;border-collapse:collapse;">
    <tr>
        <th align="left" style="padding:8px 10px;border-bottom:1px solid #e4e4e7;font-size:12px;color:#71717a;"><?= htmlspecialchars($licenseNameLabel, ENT_QUOTES, 'UTF-8') ?></th>
        <th align="left" style="padding:8px 10px;border-bottom:1px solid #e4e4e7;font-size:12px;color:#71717a;"><?= htmlspecialchars($licenseVendorLabel, ENT_QUOTES, 'UTF-8') ?></th>
        <th align="left" style="padding:8px 10px;border-bottom:1px solid #e4e4e7;font-size:12px;color:#71717a;"><?= htmlspecialchars($licenseExpirationLabel, ENT_QUOTES, 'UTF-8') ?></th>
    </tr>
    <?php foreach ($licenses as $license): ?>
    <tr>
        <td style="padding:8px 10px;border-bottom:1px solid #f4f4f5;font-size:13px;"><?= htmlspecialchars((string) ($license['name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
        <td style="padding:8px 10px;border-bottom:1px solid #f4f4f5;font-size:13px;"><?= htmlspecialchars((string) ($license['vendor'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
        <td style="padding:8px 10px;border-bottom:1px solid #f4f4f5;font-size:13px;"><?= htmlspecialchars($formatDate((string) ($license['expiration_date'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if ($consumables !== []): ?>
<h2 style="margin:0 0 10px 0;font-size:16px;color:#b45309;"><?= htmlspecialchars($consumablesHeading, ENT_QUOTES, 'UTF-8') ?></h2>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px 0;border-collapse:collapse;">
    <tr>
        <th align="left" style="padding:8px 10px;border-bottom:1px solid #e4e4e7;font-size:12px;color:#71717a;"><?= htmlspecialchars($consumableNameLabel, ENT_QUOTES, 'UTF-8') ?></th>
        <th align="left" style="padding:8px 10px;border-bottom:1px solid #e4e4e7;font-size:12px;color:#71717a;"><?= htmlspecialchars($consumableQuantityLabel, ENT_QUOTES, 'UTF-8') ?></th>
        <th align="left" style="padding:8px 10px;border-bottom:1px solid #e4e4e7;font-size:12px;color:#71717a;"><?= htmlspecialchars($consumableMinStockLabel, ENT_QUOTES, 'UTF-8') ?></th>
    </tr>
    <?php foreach ($consumables as $consumable): ?>
    <tr>
        <td style="padding:8px 10px;border-bottom:1px solid #f4f4f5;font-size:13px;"><?= htmlspecialchars((string) ($consumable['name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
        <td style="padding:8px 10px;border-bottom:1px solid #f4f4f5;font-size:13px;color:#b45309;font-weight:600;"><?= (int) ($consumable['quantity'] ?? 0) ?></td>
        <td style="padding:8px 10px;border-bottom:1px solid #f4f4f5;font-size:13px;"><?= (int) ($consumable['min_stock_level'] ?? 0) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<p style="margin:0 0 16px 0;">
    <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>" style="display:inline-block;padding:10px 16px;background:#18181b;color:#ffffff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:600;"><?= htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') ?></a>
</p>
<p style="margin:0;font-size:12px;line-height:1.5;color:#71717a;"><?= htmlspecialchars($footer, ENT_QUOTES, 'UTF-8') ?></p>
