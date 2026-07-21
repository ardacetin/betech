<?php

declare(strict_types=1);

/**
 * @var string $pageTitle
 * @var string $appName
 * @var string $appSubtitle
 * @var string $locale
 * @var list<array<string, mixed>> $documents
 */
$locale = $locale ?? 'tr';
$documents = is_array($documents ?? null) ? $documents : [];
$pageTitle = __('landing_nav_documents');

require __DIR__ . '/partials/public_head.php';
?>
</head>
<body x-data="{ mobileOpen: false }">
    <div class="page">
        <?php require __DIR__ . '/partials/public_topbar.php'; ?>
        <main class="section">
            <div class="section-inner">
                <h1 class="section-title"><?= htmlspecialchars(__('landing_nav_documents'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="section-subtitle"><?= htmlspecialchars(__('landing_documents_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>

                <?php if ($documents === []): ?>
                    <p class="empty" style="margin-top:1.5rem;"><?= htmlspecialchars(__('landing_documents_empty'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <div class="cards" style="margin-top:1.5rem;">
                        <?php foreach ($documents as $doc): ?>
                            <a class="card" href="/api/quality-documents/<?= (int) ($doc['id'] ?? 0) ?>/public-download">
                                <h3><?= htmlspecialchars((string) ($doc['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars((string) ($doc['filename'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($doc['file_size'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <span class="card-link"><?= htmlspecialchars(__('quality_documents_action_download'), ENT_QUOTES, 'UTF-8') ?> →</span>
                            </a>
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
