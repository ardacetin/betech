<?php

declare(strict_types=1);

/**
 * @var string $pageTitle
 * @var string $appName
 * @var string $appSubtitle
 * @var string $locale
 * @var list<array<string, mixed>> $articles
 */
$locale = $locale ?? 'tr';
$articles = is_array($articles ?? null) ? $articles : [];
$pageTitle = __('nav_knowledge_base');

$articlesJson = json_encode(array_map(static function (array $article): array {
    return [
        'id' => (int) ($article['id'] ?? 0),
        'title' => (string) ($article['title'] ?? ''),
        'content' => (string) ($article['content'] ?? ''),
        'updated_at' => (string) ($article['updated_at'] ?? ''),
    ];
}, $articles), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

require __DIR__ . '/partials/public_head.php';
?>
</head>
<body x-data="publicKbPage()">
    <div class="page">
        <?php require __DIR__ . '/partials/public_topbar.php'; ?>
        <main class="section">
            <div class="section-inner">
                <h1 class="section-title"><?= htmlspecialchars(__('nav_knowledge_base'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="section-subtitle"><?= htmlspecialchars(__('landing_kb_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>

                <?php if ($articles === []): ?>
                    <p class="empty" style="margin-top:1.5rem;"><?= htmlspecialchars(__('landing_kb_empty'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <div class="cards" style="margin-top:1.5rem;">
                        <template x-for="article in articles" :key="article.id">
                            <button type="button" class="card" style="text-align:left;cursor:pointer;font:inherit;" @click="openArticle(article)">
                                <h3 x-text="article.title"></h3>
                                <p x-text="excerpt(article.content)"></p>
                                <span class="card-link"><?= htmlspecialchars(__('landing_kb_read'), ENT_QUOTES, 'UTF-8') ?> →</span>
                            </button>
                        </template>
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

    <div x-show="selectedArticle" x-cloak class="modal-backdrop" @keydown.escape.window="closeArticle()">
        <div style="position:absolute;inset:0;" @click="closeArticle()"></div>
        <div class="modal relative" role="dialog" aria-modal="true" @click.stop>
            <button type="button" class="modal-close" @click="closeArticle()">✕</button>
            <h3 class="list-title" style="font-size:1.5rem;" x-text="selectedArticle?.title || ''"></h3>
            <div class="kb-prose" x-html="selectedArticle ? renderContent(selectedArticle.content) : ''"></div>
        </div>
    </div>
    <script>
        function publicKbPage() {
            return {
                mobileOpen: false,
                articles: <?= $articlesJson ?>,
                selectedArticle: null,
                openArticle(article) { this.selectedArticle = article; },
                closeArticle() { this.selectedArticle = null; },
                excerpt(content) {
                    const text = String(content || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                    return text.length > 140 ? text.slice(0, 140) + '…' : text;
                },
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
