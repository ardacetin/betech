<?php

declare(strict_types=1);

/**
 * @var string $heading
 * @var string $intro
 * @var list<array{label: string, value: string}> $rows
 * @var string $ctaUrl
 * @var string $ctaLabel
 * @var string $footer
 */
?>
<h1 style="margin:0 0 12px;font-size:20px;color:#18181b;"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
<p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#52525b;"><?= htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') ?></p>

<?php if ($rows !== []): ?>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #e4e4e7;border-radius:12px;overflow:hidden;">
    <?php foreach ($rows as $row): ?>
    <tr>
        <td style="padding:12px 14px;border-bottom:1px solid #f4f4f5;font-size:13px;color:#71717a;width:40%;">
            <?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?>
        </td>
        <td style="padding:12px 14px;border-bottom:1px solid #f4f4f5;font-size:13px;color:#18181b;font-weight:600;">
            <?= htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8') ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<p style="margin:24px 0 0;">
    <a href="<?= htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') ?>" style="display:inline-block;background:#18181b;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:10px;font-size:13px;font-weight:600;">
        <?= htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') ?>
    </a>
</p>
<p style="margin:20px 0 0;font-size:12px;color:#a1a1aa;"><?= htmlspecialchars($footer, ENT_QUOTES, 'UTF-8') ?></p>
