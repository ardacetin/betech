<?php

declare(strict_types=1);

/**
 * @var string $heading
 * @var string $intro
 * @var string $generatedAtLabel
 * @var string $generatedAt
 * @var string $licensesHeading
 * @var string $licenseNameLabel
 * @var string $licenseVendorLabel
 * @var string $licenseExpirationLabel
 * @var string $licenseSeatsLabel
 * @var list<array<string, mixed>> $licenses
 * @var string $consumablesHeading
 * @var string $consumableNameLabel
 * @var string $consumableQuantityLabel
 * @var string $consumableMinStockLabel
 * @var string $consumableLocationLabel
 * @var list<array<string, mixed>> $consumables
 * @var string $ticketsHeading
 * @var string $ticketIdLabel
 * @var string $ticketSubjectLabel
 * @var string $ticketRequesterLabel
 * @var string $ticketUpdatedLabel
 * @var list<array<string, mixed>> $tickets
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

$formatDateTime = static function (?string $value): string {
    $value = trim((string) $value);

    if ($value === '') {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false ? date('Y-m-d H:i', $timestamp) : $value;
};

$formatLocation = static function (array $consumable): string {
    $locationName = trim((string) ($consumable['location_name'] ?? ''));
    $building = trim((string) ($consumable['location_building'] ?? ''));

    if ($locationName === '' && $building === '') {
        return '-';
    }

    if ($building !== '' && $locationName !== '') {
        return $building . ' · ' . $locationName;
    }

    return $locationName !== '' ? $locationName : $building;
};

$formatSeats = static function (array $license): string {
    $used = (int) ($license['seats_used'] ?? 0);
    $total = (int) ($license['seats'] ?? 0);

    if ($total <= 0) {
        return (string) $used;
    }

    return sprintf('%d / %d', $used, $total);
};
?>
<h1 style="margin:0 0 12px 0;font-size:22px;line-height:1.3;font-weight:700;color:#18181b;"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
<p style="margin:0 0 8px 0;font-size:15px;line-height:1.6;color:#52525b;"><?= htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') ?></p>
<p style="margin:0 0 24px 0;font-size:12px;line-height:1.5;color:#71717a;"><?= htmlspecialchars($generatedAtLabel, ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') ?></p>

<?php if ($licenses !== []): ?>
    <h2 style="margin:0 0 12px 0;font-size:16px;line-height:1.4;font-weight:700;color:#18181b;"><?= htmlspecialchars($licensesHeading, ENT_QUOTES, 'UTF-8') ?></h2>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px 0;border:1px solid #e4e4e7;border-radius:12px;border-collapse:separate;overflow:hidden;">
        <tr style="background-color:#fafafa;">
            <th align="left" style="padding:10px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#71717a;border-bottom:1px solid #e4e4e7;"><?= htmlspecialchars($licenseNameLabel, ENT_QUOTES, 'UTF-8') ?></th>
            <th align="left" style="padding:10px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#71717a;border-bottom:1px solid #e4e4e7;"><?= htmlspecialchars($licenseVendorLabel, ENT_QUOTES, 'UTF-8') ?></th>
            <th align="left" style="padding:10px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#71717a;border-bottom:1px solid #e4e4e7;"><?= htmlspecialchars($licenseExpirationLabel, ENT_QUOTES, 'UTF-8') ?></th>
            <th align="left" style="padding:10px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#71717a;border-bottom:1px solid #e4e4e7;"><?= htmlspecialchars($licenseSeatsLabel, ENT_QUOTES, 'UTF-8') ?></th>
        </tr>
        <?php foreach ($licenses as $license): ?>
            <tr>
                <td style="padding:10px 12px;font-size:14px;line-height:1.5;color:#18181b;border-bottom:1px solid #f4f4f5;"><?= htmlspecialchars((string) ($license['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="padding:10px 12px;font-size:14px;line-height:1.5;color:#3f3f46;border-bottom:1px solid #f4f4f5;"><?= htmlspecialchars((string) ($license['vendor'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="padding:10px 12px;font-size:14px;line-height:1.5;color:#b45309;border-bottom:1px solid #f4f4f5;"><?= htmlspecialchars($formatDate((string) ($license['expiration_date'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="padding:10px 12px;font-size:14px;line-height:1.5;color:#3f3f46;border-bottom:1px solid #f4f4f5;"><?= htmlspecialchars($formatSeats($license), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php if ($consumables !== []): ?>
    <h2 style="margin:0 0 12px 0;font-size:16px;line-height:1.4;font-weight:700;color:#18181b;"><?= htmlspecialchars($consumablesHeading, ENT_QUOTES, 'UTF-8') ?></h2>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px 0;border:1px solid #e4e4e7;border-radius:12px;border-collapse:separate;overflow:hidden;">
        <tr style="background-color:#fafafa;">
            <th align="left" style="padding:10px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#71717a;border-bottom:1px solid #e4e4e7;"><?= htmlspecialchars($consumableNameLabel, ENT_QUOTES, 'UTF-8') ?></th>
            <th align="left" style="padding:10px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#71717a;border-bottom:1px solid #e4e4e7;"><?= htmlspecialchars($consumableQuantityLabel, ENT_QUOTES, 'UTF-8') ?></th>
            <th align="left" style="padding:10px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#71717a;border-bottom:1px solid #e4e4e7;"><?= htmlspecialchars($consumableMinStockLabel, ENT_QUOTES, 'UTF-8') ?></th>
            <th align="left" style="padding:10px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#71717a;border-bottom:1px solid #e4e4e7;"><?= htmlspecialchars($consumableLocationLabel, ENT_QUOTES, 'UTF-8') ?></th>
        </tr>
        <?php foreach ($consumables as $consumable): ?>
            <tr>
                <td style="padding:10px 12px;font-size:14px;line-height:1.5;color:#18181b;border-bottom:1px solid #f4f4f5;"><?= htmlspecialchars((string) ($consumable['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="padding:10px 12px;font-size:14px;line-height:1.5;color:#b91c1c;border-bottom:1px solid #f4f4f5;"><?= htmlspecialchars((string) ($consumable['quantity'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="padding:10px 12px;font-size:14px;line-height:1.5;color:#3f3f46;border-bottom:1px solid #f4f4f5;"><?= htmlspecialchars((string) ($consumable['min_stock_level'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="padding:10px 12px;font-size:14px;line-height:1.5;color:#3f3f46;border-bottom:1px solid #f4f4f5;"><?= htmlspecialchars($formatLocation($consumable), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php if ($tickets !== []): ?>
    <h2 style="margin:0 0 12px 0;font-size:16px;line-height:1.4;font-weight:700;color:#18181b;"><?= htmlspecialchars($ticketsHeading, ENT_QUOTES, 'UTF-8') ?></h2>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px 0;border:1px solid #e4e4e7;border-radius:12px;border-collapse:separate;overflow:hidden;">
        <tr style="background-color:#fafafa;">
            <th align="left" style="padding:10px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#71717a;border-bottom:1px solid #e4e4e7;"><?= htmlspecialchars($ticketIdLabel, ENT_QUOTES, 'UTF-8') ?></th>
            <th align="left" style="padding:10px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#71717a;border-bottom:1px solid #e4e4e7;"><?= htmlspecialchars($ticketSubjectLabel, ENT_QUOTES, 'UTF-8') ?></th>
            <th align="left" style="padding:10px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#71717a;border-bottom:1px solid #e4e4e7;"><?= htmlspecialchars($ticketRequesterLabel, ENT_QUOTES, 'UTF-8') ?></th>
            <th align="left" style="padding:10px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#71717a;border-bottom:1px solid #e4e4e7;"><?= htmlspecialchars($ticketUpdatedLabel, ENT_QUOTES, 'UTF-8') ?></th>
        </tr>
        <?php foreach ($tickets as $ticket): ?>
            <tr>
                <td style="padding:10px 12px;font-size:14px;line-height:1.5;color:#18181b;border-bottom:1px solid #f4f4f5;"><?= htmlspecialchars((string) ($ticket['ticket_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="padding:10px 12px;font-size:14px;line-height:1.5;color:#3f3f46;border-bottom:1px solid #f4f4f5;"><?= htmlspecialchars((string) ($ticket['subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="padding:10px 12px;font-size:14px;line-height:1.5;color:#3f3f46;border-bottom:1px solid #f4f4f5;"><?= htmlspecialchars((string) ($ticket['personnel_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="padding:10px 12px;font-size:14px;line-height:1.5;color:#b45309;border-bottom:1px solid #f4f4f5;"><?= htmlspecialchars($formatDateTime((string) ($ticket['updated_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<p style="margin:0 0 20px 0;text-align:center;">
    <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>" style="display:inline-block;padding:12px 20px;border-radius:12px;background-color:#18181b;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;"><?= htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') ?></a>
</p>

<p style="margin:0;font-size:12px;line-height:1.5;color:#a1a1aa;"><?= htmlspecialchars($footer, ENT_QUOTES, 'UTF-8') ?></p>
