<?php

declare(strict_types=1);

/**
 * @var int|null $iconSize
 * @var string|null $wrapperClass
 */
$iconSize = max(12, (int) ($iconSize ?? 18));
$wrapperClass = trim((string) ($wrapperClass ?? 'brand-mark'));
?>
<span class="<?= htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="<?= $iconSize ?>" height="<?= $iconSize ?>" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="8.5" cy="8.5" r="2.25"/>
        <path d="M14.5 6.5l1.1 1.45a2.6 2.6 0 0 1 0 3.1L14.5 12.5l-1.45 1.1a2.6 2.6 0 0 1-3.1 0L8.5 12.5 7.4 11.05a2.6 2.6 0 0 1 0-3.1L8.5 6.5l1.45-1.1a2.6 2.6 0 0 1 3.1 0Z"/>
        <circle cx="15.5" cy="15.5" r="2.25"/>
        <path d="M9.5 13.5l1.1 1.45a2.6 2.6 0 0 1 0 3.1L9.5 19.5l-1.45 1.1a2.6 2.6 0 0 1-3.1 0L3.5 19.5 2.4 18.05a2.6 2.6 0 0 1 0-3.1L3.5 13.5l1.45-1.1a2.6 2.6 0 0 1 3.1 0Z"/>
    </svg>
</span>
