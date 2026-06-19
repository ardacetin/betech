<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $ticket
 * @var string $ticketUrl
 * @var string $heading
 * @var string $intro
 * @var string $ticketIdLabel
 * @var string $subjectLabel
 * @var string $statusLabel
 * @var string $ctaLabel
 * @var string $footer
 * @var string $detailHtml
 */

$statusKey = 'ticket_status_' . (string) ($ticket['status'] ?? 'open');
$statusLabelValue = function_exists('__') ? __($statusKey) : (string) ($ticket['status'] ?? '');
?>
<h1 style="margin:0 0 12px 0;font-size:22px;line-height:1.3;font-weight:700;color:#18181b;"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
<p style="margin:0 0 20px 0;font-size:15px;line-height:1.6;color:#52525b;"><?= htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') ?></p>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px 0;border:1px solid #e4e4e7;border-radius:12px;background-color:#fafafa;">
    <tr>
        <td style="padding:16px 18px;">
            <p style="margin:0 0 8px 0;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:#71717a;"><?= htmlspecialchars($ticketIdLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <p style="margin:0 0 16px 0;font-size:16px;font-weight:700;color:#18181b;"><?= htmlspecialchars((string) ($ticket['ticket_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

            <p style="margin:0 0 6px 0;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:#71717a;"><?= htmlspecialchars($subjectLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <p style="margin:0 0 16px 0;font-size:15px;line-height:1.5;color:#18181b;"><?= htmlspecialchars((string) ($ticket['subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

            <p style="margin:0 0 6px 0;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:#71717a;"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <p style="margin:0;font-size:15px;line-height:1.5;color:#18181b;"><?= htmlspecialchars($statusLabelValue, ENT_QUOTES, 'UTF-8') ?></p>
        </td>
    </tr>
</table>

<?= $detailHtml ?>

<p style="margin:0 0 20px 0;text-align:center;">
    <a href="<?= htmlspecialchars($ticketUrl, ENT_QUOTES, 'UTF-8') ?>" style="display:inline-block;padding:12px 20px;border-radius:12px;background-color:#18181b;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;"><?= htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') ?></a>
</p>

<p style="margin:0;font-size:12px;line-height:1.5;color:#a1a1aa;"><?= htmlspecialchars($footer, ENT_QUOTES, 'UTF-8') ?></p>
