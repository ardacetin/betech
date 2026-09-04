<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $card
 * @var string $cardUrl
 * @var string $heading
 * @var string $intro
 */

$assignedName = trim((string) ($card['assigned_user_name'] ?? ''));
$dueDate = trim((string) ($card['due_date'] ?? ''));
$ticketNumber = trim((string) ($card['ticket_number'] ?? ''));
?>
<h1 style="margin:0 0 12px 0;font-size:22px;line-height:1.3;font-weight:700;color:#18181b;"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
<p style="margin:0 0 20px 0;font-size:15px;line-height:1.6;color:#52525b;"><?= htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') ?></p>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px 0;border:1px solid #e4e4e7;border-radius:12px;background-color:#fafafa;">
    <tr>
        <td style="padding:16px 18px;">
            <p style="margin:0 0 6px 0;font-size:12px;font-weight:600;text-transform:uppercase;color:#71717a;"><?= htmlspecialchars(__('todo_title_label'), ENT_QUOTES, 'UTF-8') ?></p>
            <p style="margin:0 0 16px 0;font-size:16px;font-weight:700;color:#18181b;"><?= htmlspecialchars((string) ($card['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

            <?php if ($ticketNumber !== ''): ?>
                <p style="margin:0 0 6px 0;font-size:12px;font-weight:600;text-transform:uppercase;color:#71717a;"><?= htmlspecialchars(__('todo_linked_ticket'), ENT_QUOTES, 'UTF-8') ?></p>
                <p style="margin:0 0 16px 0;font-size:14px;color:#18181b;"><?= htmlspecialchars($ticketNumber, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <?php if ($assignedName !== ''): ?>
                <p style="margin:0 0 6px 0;font-size:12px;font-weight:600;text-transform:uppercase;color:#71717a;"><?= htmlspecialchars(__('todo_assignee'), ENT_QUOTES, 'UTF-8') ?></p>
                <p style="margin:0 0 16px 0;font-size:14px;color:#18181b;"><?= htmlspecialchars($assignedName, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <?php if ($dueDate !== ''): ?>
                <p style="margin:0 0 6px 0;font-size:12px;font-weight:600;text-transform:uppercase;color:#71717a;"><?= htmlspecialchars(__('todo_due_date'), ENT_QUOTES, 'UTF-8') ?></p>
                <p style="margin:0 0 16px 0;font-size:14px;color:#18181b;"><?= htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <p style="margin:0 0 6px 0;font-size:12px;font-weight:600;text-transform:uppercase;color:#71717a;"><?= htmlspecialchars(__('todo_description'), ENT_QUOTES, 'UTF-8') ?></p>
            <p style="margin:0;font-size:14px;line-height:1.6;color:#3f3f46;white-space:pre-wrap;"><?= htmlspecialchars((string) ($card['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        </td>
    </tr>
</table>

<p style="margin:0;text-align:center;">
    <a href="<?= htmlspecialchars($cardUrl, ENT_QUOTES, 'UTF-8') ?>" style="display:inline-block;padding:12px 20px;border-radius:12px;background-color:#7a242c;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;"><?= htmlspecialchars(__('todo_mail_view_cta'), ENT_QUOTES, 'UTF-8') ?></a>
</p>
