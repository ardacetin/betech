<?php

declare(strict_types=1);

/** @var string $replyLabel */
/** @var string $authorName */
/** @var string $commentBody */
?>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px 0;border:1px solid #dbeafe;border-radius:12px;background-color:#eff6ff;">
    <tr>
        <td style="padding:16px 18px;">
            <p style="margin:0 0 8px 0;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:#1d4ed8;"><?= htmlspecialchars($replyLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <p style="margin:0 0 10px 0;font-size:14px;font-weight:600;color:#18181b;"><?= htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') ?></p>
            <p style="margin:0;font-size:14px;line-height:1.6;color:#3f3f46;white-space:pre-wrap;"><?= htmlspecialchars($commentBody, ENT_QUOTES, 'UTF-8') ?></p>
        </td>
    </tr>
</table>
