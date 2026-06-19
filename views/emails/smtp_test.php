<?php

declare(strict_types=1);

/** @var string $heading */
/** @var string $intro */
/** @var string $footer */
/** @var string $appUrl */
?>
<h1 style="margin:0 0 12px 0;font-size:22px;line-height:1.3;font-weight:700;color:#18181b;"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h1>
<p style="margin:0 0 20px 0;font-size:15px;line-height:1.6;color:#52525b;"><?= htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') ?></p>
<p style="margin:0 0 20px 0;font-size:14px;line-height:1.6;color:#3f3f46;"><?= htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8') ?></p>
<p style="margin:0;font-size:12px;line-height:1.5;color:#a1a1aa;"><?= htmlspecialchars($footer, ENT_QUOTES, 'UTF-8') ?></p>
