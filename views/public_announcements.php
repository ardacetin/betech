<?php

declare(strict_types=1);

/**
 * @var string $pageTitle
 * @var string $appName
 * @var string $appSubtitle
 * @var string $locale
 * @var list<array<string, mixed>> $announcements
 */
$locale = $locale ?? 'tr';
$announcements = is_array($announcements ?? null) ? $announcements : [];
$pageTitle = __('landing_nav_announcements');

require __DIR__ . '/partials/public_head.php';
?>
</head>
<body x-data="{ mobileOpen: false }">
    <div class="page">
        <?php require __DIR__ . '/partials/public_topbar.php'; ?>
        <main class="section">
            <div class="section-inner">
                <h1 class="section-title"><?= htmlspecialchars(__('landing_nav_announcements'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="section-subtitle"><?= htmlspecialchars(__('landing_announcements_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>

                <?php if ($announcements === []): ?>
                    <p class="empty" style="margin-top:1.5rem;"><?= htmlspecialchars(__('landing_announcements_empty'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <div class="list" style="margin-top:1.5rem;">
                        <?php foreach ($announcements as $item): ?>
                            <?php
                            $dateRaw = (string) ($item['published_at'] ?? $item['created_at'] ?? '');
                            $dateLabel = format_display_date($dateRaw, $locale);
                            ?>
                            <article class="list-item">
                                <div class="list-meta">
                                    <span><?= htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if (!empty($item['category'])): ?>
                                        <span class="tag"><?= htmlspecialchars((string) $item['category'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </div>
                                <h2 class="list-title"><?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                                <?php if (!empty($item['summary'])): ?>
                                    <div class="kb-prose announcement-summary"><?= render_rich_content((string) $item['summary']) ?></div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        <footer class="footer">
            <div class="footer-inner">
                <p><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($appSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
                <a href="/"><?= htmlspecialchars(__('landing_nav_home'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </footer>
    </div>
</body>
</html>
