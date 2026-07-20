<?php

declare(strict_types=1);

/**
 * @var string $pageTitle
 * @var string $appName
 * @var string $locale
 * @var string $heroTitle
 * @var string $heroSubtitle
 * @var string $heroCtaLabel
 * @var list<array<string, mixed>> $articles
 */
$locale = $locale ?? 'tr';
$articles = is_array($articles ?? null) ? $articles : [];
$articlesJson = json_encode(array_map(static function (array $article): array {
    return [
        'id' => (int) ($article['id'] ?? 0),
        'title' => (string) ($article['title'] ?? ''),
        'content' => (string) ($article['content'] ?? ''),
        'updated_at' => (string) ($article['updated_at'] ?? ''),
    ];
}, $articles), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle . ' - ' . $appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/css/app.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            --landing-ink: #111827;
            --landing-muted: #4b5563;
            --landing-panel: #f3f4f6;
            --landing-accent: #0d9488;
            --landing-accent-deep: #0f766e;
            --landing-glow: #5eead4;
            --landing-moss: var(--landing-accent);
            --landing-moss-deep: var(--landing-accent-deep);
        }
        body {
            font-family: "Source Sans 3", ui-sans-serif, system-ui, sans-serif;
            color: var(--landing-ink);
            background: #f8fafc;
        }
        .landing-display {
            font-family: Outfit, ui-sans-serif, system-ui, sans-serif;
            letter-spacing: -0.03em;
            font-weight: 700;
        }
        .landing-hero {
            background:
                radial-gradient(ellipse 70% 55% at 12% 18%, rgba(13, 148, 136, 0.35), transparent 55%),
                radial-gradient(ellipse 45% 60% at 88% 12%, rgba(37, 99, 235, 0.18), transparent 50%),
                linear-gradient(155deg, #0b1220 0%, #111827 48%, #0f172a 100%);
        }
        .landing-grid {
            background-image:
                linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: linear-gradient(to bottom, black 30%, transparent 95%);
        }
        @keyframes landing-rise {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes landing-fade {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .anim-rise { animation: landing-rise 0.7s ease-out both; }
        .anim-rise-delay { animation: landing-rise 0.7s ease-out 0.12s both; }
        .anim-rise-delay-2 { animation: landing-rise 0.7s ease-out 0.24s both; }
        .anim-fade { animation: landing-fade 0.8s ease-out 0.2s both; }
        .kb-prose {
            white-space: pre-wrap;
            word-break: break-word;
        }
        .kb-prose p { margin: 0 0 0.75rem; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-full antialiased" x-data="landingPortal()">
    <header class="sticky top-0 z-40 border-b border-white/10 bg-[#0f1720]/90 backdrop-blur-md">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-3.5 sm:px-8">
            <a href="/" class="flex min-w-0 items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[var(--landing-moss)] text-sm font-semibold text-white"><?= htmlspecialchars(mb_substr($appName, 0, 1), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="truncate text-sm font-semibold tracking-tight text-white sm:text-base"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <div class="flex items-center gap-2 sm:gap-3">
                <a href="#bilgi-bankasi" class="hidden rounded-lg px-3 py-2 text-sm font-medium text-white/80 transition hover:bg-white/10 hover:text-white sm:inline-flex">
                    <?= htmlspecialchars(__('nav_knowledge_base'), ENT_QUOTES, 'UTF-8') ?>
                </a>
                <div class="inline-flex items-center rounded-lg border border-white/15 p-0.5 text-xs font-semibold">
                    <a href="<?= htmlspecialchars(lang_url('tr'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $locale === 'tr' ? 'bg-white text-[var(--landing-ink)]' : 'text-white/70 hover:text-white' ?> rounded-md px-2.5 py-1.5 transition">TR</a>
                    <a href="<?= htmlspecialchars(lang_url('en'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $locale === 'en' ? 'bg-white text-[var(--landing-ink)]' : 'text-white/70 hover:text-white' ?> rounded-md px-2.5 py-1.5 transition">EN</a>
                </div>
                <a
                    href="/login"
                    class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-[var(--landing-ink)] shadow-sm transition hover:bg-slate-100"
                >
                    <?= htmlspecialchars(__('landing_login'), ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
        </div>
    </header>

    <main>
        <section class="landing-hero relative overflow-hidden text-white">
            <div class="landing-grid pointer-events-none absolute inset-0" aria-hidden="true"></div>
            <div class="relative mx-auto flex min-h-[78vh] max-w-6xl flex-col justify-end px-5 pb-16 pt-24 sm:px-8 sm:pb-24 sm:pt-28">
                <p class="landing-display anim-fade mb-4 text-2xl text-[var(--landing-glow)] sm:text-3xl">
                    <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>
                </p>
                <h1 class="landing-display anim-rise max-w-3xl text-4xl leading-[1.08] text-white sm:text-5xl md:text-6xl">
                    <?= htmlspecialchars($heroTitle, ENT_QUOTES, 'UTF-8') ?>
                </h1>
                <p class="anim-rise-delay mt-6 max-w-xl text-base leading-relaxed text-white/75 sm:text-lg">
                    <?= htmlspecialchars($heroSubtitle, ENT_QUOTES, 'UTF-8') ?>
                </p>
                <div class="anim-rise-delay-2 mt-10 flex flex-wrap items-center gap-3">
                    <a
                        href="#bilgi-bankasi"
                        class="inline-flex items-center rounded-xl bg-[var(--landing-moss)] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[var(--landing-moss-deep)]"
                    >
                        <?= htmlspecialchars($heroCtaLabel, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <a
                        href="/login"
                        class="inline-flex items-center rounded-xl border border-white/25 px-5 py-3 text-sm font-semibold text-white/90 transition hover:bg-white/10"
                    >
                        <?= htmlspecialchars(__('landing_login'), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </div>
            </div>
        </section>

        <section id="bilgi-bankasi" class="border-t border-slate-200 bg-slate-50 px-5 py-16 sm:px-8 sm:py-20">
            <div class="mx-auto max-w-6xl">
                <div class="max-w-2xl">
                    <h2 class="landing-display text-4xl text-[var(--landing-ink)] sm:text-5xl">
                        <?= htmlspecialchars(__('nav_knowledge_base'), ENT_QUOTES, 'UTF-8') ?>
                    </h2>
                    <p class="mt-3 text-base text-[var(--landing-muted)]">
                        <?= htmlspecialchars(__('landing_kb_subtitle'), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>

                <?php if ($articles === []): ?>
                    <p class="mt-12 rounded-2xl border border-dashed border-[var(--landing-ink)]/15 bg-white/60 px-6 py-12 text-center text-sm text-[var(--landing-muted)]">
                        <?= htmlspecialchars(__('landing_kb_empty'), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php else: ?>
                    <div class="mt-10 grid gap-4 md:grid-cols-2">
                        <template x-for="article in articles" :key="article.id">
                            <button
                                type="button"
                                @click="openArticle(article)"
                                class="group rounded-2xl border border-[var(--landing-ink)]/10 bg-white p-6 text-left shadow-[0_1px_2px_rgba(15,23,32,0.04)] transition hover:-translate-y-0.5 hover:border-[var(--landing-moss)]/40 hover:shadow-[0_12px_32px_rgba(15,23,32,0.08)]"
                            >
                                <p class="text-xs font-medium uppercase tracking-wider text-[var(--landing-moss)]" x-text="formatDate(article.updated_at)"></p>
                                <h3 class="mt-2 text-lg font-semibold tracking-tight text-[var(--landing-ink)] group-hover:text-[var(--landing-moss-deep)]" x-text="article.title"></h3>
                                <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-[var(--landing-muted)]" x-text="excerpt(article.content)"></p>
                                <span class="mt-4 inline-flex text-sm font-semibold text-[var(--landing-moss)]">
                                    <?= htmlspecialchars(__('landing_kb_read'), ENT_QUOTES, 'UTF-8') ?> →
                                </span>
                            </button>
                        </template>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white px-5 py-8 sm:px-8">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 text-sm text-[var(--landing-muted)]">
            <p><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></p>
            <a href="/login" class="font-medium text-[var(--landing-ink)] hover:underline"><?= htmlspecialchars(__('landing_login'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </footer>

    <div
        x-show="selectedArticle"
        x-cloak
        class="fixed inset-0 z-50 flex items-end justify-center bg-[var(--landing-ink)]/50 px-4 py-6 sm:items-center"
        @keydown.escape.window="closeArticle()"
    >
        <div class="absolute inset-0" @click="closeArticle()"></div>
        <div class="relative max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl sm:p-8">
            <button type="button" @click="closeArticle()" class="absolute right-4 top-4 rounded-lg px-2 py-1 text-sm text-[var(--landing-muted)] hover:bg-zinc-100">✕</button>
            <p class="text-xs font-medium uppercase tracking-wider text-[var(--landing-moss)]" x-text="selectedArticle ? formatDate(selectedArticle.updated_at) : ''"></p>
            <h3 class="landing-display mt-2 pr-8 text-3xl text-[var(--landing-ink)]" x-text="selectedArticle?.title || ''"></h3>
            <div class="kb-prose mt-6 text-sm leading-relaxed text-[var(--landing-muted)]" x-html="selectedArticle ? renderContent(selectedArticle.content) : ''"></div>
        </div>
    </div>

    <script>
        function landingPortal() {
            return {
                articles: <?= $articlesJson ?>,
                selectedArticle: null,
                openArticle(article) {
                    this.selectedArticle = article;
                },
                closeArticle() {
                    this.selectedArticle = null;
                },
                excerpt(content) {
                    const text = String(content || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                    return text.length > 140 ? text.slice(0, 140) + '…' : text;
                },
                formatDate(value) {
                    if (!value) return '';
                    const date = new Date(String(value).replace(' ', 'T'));
                    if (Number.isNaN(date.getTime())) return String(value);
                    return date.toLocaleDateString(<?= json_encode($locale === 'en' ? 'en-GB' : 'tr-TR', JSON_THROW_ON_ERROR) ?>, {
                        year: 'numeric', month: 'short', day: 'numeric',
                    });
                },
                renderContent(content) {
                    const raw = String(content || '');
                    if (/<[a-z][\s\S]*>/i.test(raw)) {
                        return raw;
                    }
                    const escaped = raw
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;');
                    return escaped.replace(/\n/g, '<br>');
                },
            };
        }
    </script>
</body>
</html>
