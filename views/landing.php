<?php

declare(strict_types=1);

/**
 * @var string $pageTitle
 * @var string $appName
 * @var string $appSubtitle
 * @var string $locale
 * @var string $heroTitle
 * @var string $heroSubtitle
 * @var string $heroCtaLabel
 * @var string $csrfToken
 * @var bool $turnstileEnabled
 * @var string $turnstileSiteKey
 * @var list<array<string, mixed>> $featuredArticles
 * @var list<array<string, mixed>> $announcements
 * @var list<array<string, mixed>> $documents
 */
$locale = $locale ?? 'tr';
$featuredArticles = is_array($featuredArticles ?? null) ? $featuredArticles : [];
$announcements = is_array($announcements ?? null) ? $announcements : [];
$documents = is_array($documents ?? null) ? $documents : [];
$csrfToken = (string) ($csrfToken ?? '');
$turnstileEnabled = (bool) ($turnstileEnabled ?? false);
$turnstileSiteKey = (string) ($turnstileSiteKey ?? '');

$articlesJson = json_encode(array_map(static function (array $article): array {
    return [
        'id' => (int) ($article['id'] ?? 0),
        'title' => (string) ($article['title'] ?? ''),
        'content' => (string) ($article['content'] ?? ''),
        'updated_at' => (string) ($article['updated_at'] ?? ''),
    ];
}, $featuredArticles), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

require __DIR__ . '/partials/public_head.php';
?>
</head>
<body x-data="publicLanding()">
    <div class="page">
        <?php require __DIR__ . '/partials/public_topbar.php'; ?>

        <main>
            <section class="hero" aria-labelledby="hero-title">
                <div class="hero-inner">
                    <h1 id="hero-title"><?= htmlspecialchars($heroTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                    <p><?= htmlspecialchars($heroSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="hero-actions">
                        <button type="button" class="btn btn-primary" @click="openLogin()"><?= htmlspecialchars(__('landing_login'), ENT_QUOTES, 'UTF-8') ?></button>
                        <a href="/knowledge-base" class="btn btn-secondary"><?= htmlspecialchars($heroCtaLabel, ENT_QUOTES, 'UTF-8') ?></a>
                    </div>
                </div>
            </section>

            <section class="section" id="talep">
                <div class="section-inner">
                    <div class="panel" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1.25rem;">
                        <div style="max-width:36rem;">
                            <h2 class="section-title" style="font-size:1.35rem;"><?= htmlspecialchars(__('landing_requests_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="section-subtitle"><?= htmlspecialchars(__('landing_requests_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <button type="button" class="btn btn-primary" @click="openLogin()"><?= htmlspecialchars(__('landing_requests_cta'), ENT_QUOTES, 'UTF-8') ?></button>
                    </div>
                </div>
            </section>

            <section class="section" id="duyurular">
                <div class="section-inner split">
                    <div class="panel">
                        <div class="panel-head">
                            <h2><?= htmlspecialchars(__('landing_recent_announcements'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <a href="/public-announcements"><?= htmlspecialchars(__('landing_all_announcements'), ENT_QUOTES, 'UTF-8') ?> →</a>
                        </div>
                        <?php if ($announcements === []): ?>
                            <p class="empty"><?= htmlspecialchars(__('landing_announcements_empty'), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php else: ?>
                            <div class="list">
                                <?php foreach ($announcements as $item): ?>
                                    <article class="list-item">
                                        <div class="list-meta">
                                            <?php
                                            $dateRaw = (string) ($item['published_at'] ?? $item['created_at'] ?? '');
                                            $dateLabel = format_display_date($dateRaw, $locale);
                                            ?>
                                            <span><?= htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if (!empty($item['category'])): ?>
                                                <span class="tag"><?= htmlspecialchars((string) $item['category'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <h3 class="list-title"><?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                                        <?php if (!empty($item['summary'])): ?>
                                            <div class="kb-prose announcement-summary"><?= render_rich_content((string) $item['summary']) ?></div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                            <p style="margin:1rem 0 0;">
                                <a class="card-link" href="/public-announcements"><?= htmlspecialchars(__('landing_all_announcements'), ENT_QUOTES, 'UTF-8') ?> →</a>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="panel" id="dokumanlar">
                        <div class="panel-head">
                            <h2><?= htmlspecialchars(__('landing_recent_documents'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <a href="/public-documents"><?= htmlspecialchars(__('landing_all_documents'), ENT_QUOTES, 'UTF-8') ?> →</a>
                        </div>
                        <?php if ($documents === []): ?>
                            <p class="empty"><?= htmlspecialchars(__('landing_documents_empty'), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php else: ?>
                            <div class="list">
                                <?php foreach ($documents as $doc): ?>
                                    <div class="list-item">
                                        <div class="list-meta">
                                            <span><?= htmlspecialchars(format_display_date((string) ($doc['created_at'] ?? ''), $locale), ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if (!empty($doc['file_size'])): ?>
                                                <span><?= htmlspecialchars((string) $doc['file_size'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <a class="list-title" href="/api/quality-documents/<?= (int) ($doc['id'] ?? 0) ?>/public-download">
                                            <?= htmlspecialchars((string) ($doc['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p style="margin:1rem 0 0;">
                                <a class="card-link" href="/public-documents"><?= htmlspecialchars(__('landing_all_documents'), ENT_QUOTES, 'UTF-8') ?> →</a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="section" aria-label="<?= htmlspecialchars(__('landing_quick_access'), ENT_QUOTES, 'UTF-8') ?>">
                <div class="section-inner">
                    <div class="cards cards-3">
                        <a class="card" href="/knowledge-base">
                            <span class="card-icon" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5V5.5Zm4 2h8M8 12h8M8 16h5"/></svg>
                            </span>
                            <h3><?= htmlspecialchars(__('nav_knowledge_base'), ENT_QUOTES, 'UTF-8') ?></h3>
                            <p><?= htmlspecialchars(__('landing_card_kb_desc'), ENT_QUOTES, 'UTF-8') ?></p>
                            <span class="card-link"><?= htmlspecialchars(__('landing_explore'), ENT_QUOTES, 'UTF-8') ?> →</span>
                        </a>
                        <a class="card" href="/public-documents">
                            <span class="card-icon" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3h7l5 5v13a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm7 0v5h5"/></svg>
                            </span>
                            <h3><?= htmlspecialchars(__('landing_nav_documents'), ENT_QUOTES, 'UTF-8') ?></h3>
                            <p><?= htmlspecialchars(__('landing_card_docs_desc'), ENT_QUOTES, 'UTF-8') ?></p>
                            <span class="card-link"><?= htmlspecialchars(__('landing_explore'), ENT_QUOTES, 'UTF-8') ?> →</span>
                        </a>
                        <a class="card" href="/public-announcements">
                            <span class="card-icon" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h13"/></svg>
                            </span>
                            <h3><?= htmlspecialchars(__('landing_nav_announcements'), ENT_QUOTES, 'UTF-8') ?></h3>
                            <p><?= htmlspecialchars(__('landing_card_announcements_desc'), ENT_QUOTES, 'UTF-8') ?></p>
                            <span class="card-link"><?= htmlspecialchars(__('landing_explore'), ENT_QUOTES, 'UTF-8') ?> →</span>
                        </a>
                    </div>
                </div>
            </section>

            <section class="section" id="bilgi-info">
                <div class="section-inner">
                    <div class="panel">
                        <div class="panel-head">
                            <h2><?= htmlspecialchars(__('landing_popular_kb'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <a href="/knowledge-base"><?= htmlspecialchars(__('landing_all_kb'), ENT_QUOTES, 'UTF-8') ?> →</a>
                        </div>
                        <?php if ($featuredArticles === []): ?>
                            <p class="empty"><?= htmlspecialchars(__('landing_kb_empty'), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php else: ?>
                            <div class="list">
                                <?php foreach ($featuredArticles as $article): ?>
                                    <div class="list-item">
                                        <button type="button" @click="openArticleById(<?= (int) ($article['id'] ?? 0) ?>)">
                                            <span class="list-title"><?= htmlspecialchars((string) ($article['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="section" id="hakkinda">
                <div class="section-inner">
                    <div class="panel">
                        <h2 class="section-title" style="font-size:1.35rem;"><?= htmlspecialchars(__('landing_nav_about'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="section-subtitle" style="max-width:48rem;"><?= htmlspecialchars(__('landing_about_body'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="footer">
            <div class="footer-inner">
                <p><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($appSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
                <div class="footer-links">
                    <a href="#hakkinda"><?= htmlspecialchars(__('landing_nav_about'), ENT_QUOTES, 'UTF-8') ?></a>
                    <button type="button" class="card-link" style="border:0;background:transparent;padding:0;cursor:pointer;font:inherit;" @click="openLogin()"><?= htmlspecialchars(__('landing_login'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
            </div>
        </footer>
    </div>

    <div x-show="selectedArticle" x-cloak class="modal-backdrop" @keydown.escape.window="closeArticle()">
        <div class="absolute inset-0" style="position:absolute;inset:0;" @click="closeArticle()"></div>
        <div class="modal relative" role="dialog" aria-modal="true" @click.stop>
            <button type="button" class="modal-close" @click="closeArticle()" aria-label="<?= htmlspecialchars(__('portal_close'), ENT_QUOTES, 'UTF-8') ?>">✕</button>
            <h3 class="list-title" style="font-size:1.5rem;margin-top:0.35rem;" x-text="selectedArticle?.title || ''"></h3>
            <div class="kb-prose" x-html="selectedArticle ? renderContent(selectedArticle.content) : ''"></div>
        </div>
    </div>

    <?php require __DIR__ . '/partials/public_login_modal.php'; ?>

    <script>
        function publicLanding() {
            return {
                mobileOpen: false,
                loginOpen: false,
                selectedArticle: null,
                articles: <?= $articlesJson ?>,
                openLogin() {
                    this.mobileOpen = false;
                    this.loginOpen = true;
                    this.$nextTick(() => {
                        const input = document.querySelector('.login-modal input[name="identifier"]');
                        if (input) input.focus();
                    });
                },
                closeLogin() { this.loginOpen = false; },
                openArticleById(id) {
                    this.selectedArticle = this.articles.find((item) => Number(item.id) === Number(id)) || null;
                },
                closeArticle() { this.selectedArticle = null; },
                renderContent(content) {
                    const raw = String(content || '');
                    if (/<[a-z][\s\S]*>/i.test(raw)) return raw;
                    return raw.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
                },
            };
        }
    </script>
</body>
</html>
