<?php

declare(strict_types=1);

/**
 * @var string $appName
 * @var string $appSubtitle
 * @var string $locale
 */
$locale = $locale ?? 'tr';
$appSubtitle = $appSubtitle ?? __('app_subtitle');
?>
<header class="topbar">
    <div class="topbar-inner">
        <a href="/" class="brand" aria-label="<?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>">
            <span class="brand-mark">B</span>
            <span class="brand-text">
                <span class="brand-name"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="brand-sub"><?= htmlspecialchars($appSubtitle, ENT_QUOTES, 'UTF-8') ?></span>
            </span>
        </a>

        <nav class="nav-links" aria-label="<?= htmlspecialchars(__('landing_nav_label'), ENT_QUOTES, 'UTF-8') ?>">
            <a class="nav-link" href="/knowledge-base"><?= htmlspecialchars(__('nav_knowledge_base'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="nav-link" href="/public-documents"><?= htmlspecialchars(__('landing_nav_documents'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="nav-link" href="/#duyurular"><?= htmlspecialchars(__('landing_nav_announcements'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="nav-link" href="/login?redirect=/"><?= htmlspecialchars(__('landing_nav_requests'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="nav-link" href="/#hakkinda"><?= htmlspecialchars(__('landing_nav_about'), ENT_QUOTES, 'UTF-8') ?></a>
        </nav>

        <div class="top-actions">
            <div class="lang-switch" aria-label="Language">
                <a href="<?= htmlspecialchars(lang_url('tr'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $locale === 'tr' ? 'is-active' : '' ?>">TR</a>
                <a href="<?= htmlspecialchars(lang_url('en'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $locale === 'en' ? 'is-active' : '' ?>">EN</a>
            </div>
            <a href="/login" class="btn btn-primary"><?= htmlspecialchars(__('landing_login'), ENT_QUOTES, 'UTF-8') ?></a>
            <button
                type="button"
                class="menu-toggle"
                @click="mobileOpen = !mobileOpen"
                :aria-expanded="mobileOpen.toString()"
                aria-label="<?= htmlspecialchars(__('landing_nav_toggle'), ENT_QUOTES, 'UTF-8') ?>"
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
    </div>
    <div class="topbar-inner" x-show="mobileOpen" x-cloak>
        <div style="display:grid;gap:0.25rem;padding:0.75rem 0 1rem;width:100%;border-top:1px solid var(--border);">
            <a class="nav-link" href="/knowledge-base" @click="mobileOpen = false"><?= htmlspecialchars(__('nav_knowledge_base'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="nav-link" href="/public-documents" @click="mobileOpen = false"><?= htmlspecialchars(__('landing_nav_documents'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="nav-link" href="/#duyurular" @click="mobileOpen = false"><?= htmlspecialchars(__('landing_nav_announcements'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="nav-link" href="/login?redirect=/" @click="mobileOpen = false"><?= htmlspecialchars(__('landing_nav_requests'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="nav-link" href="/#hakkinda" @click="mobileOpen = false"><?= htmlspecialchars(__('landing_nav_about'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </div>
</header>
