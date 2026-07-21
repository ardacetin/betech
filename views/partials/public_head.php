<?php

declare(strict_types=1);

/**
 * @var string $pageTitle
 * @var string $appName
 * @var string $locale
 */
$locale = $locale ?? 'tr';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            --bg: #f7f7f5;
            --surface: #ffffff;
            --ink: #171717;
            --muted: #667085;
            --border: #e5e7eb;
            --brand: #7a242c;
            --brand-hover: #641c23;
            --radius-card: 16px;
            --radius-btn: 12px;
            --content: 1200px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100%;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--ink);
            background: var(--bg);
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }
        .page { min-height: 100%; display: flex; flex-direction: column; }
        .topbar {
            position: sticky; top: 0; z-index: 40;
            background: rgba(247, 247, 245, 0.92);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(12px);
        }
        .topbar-inner, .section-inner, .footer-inner {
            width: min(100% - 2rem, var(--content));
            margin: 0 auto;
        }
        .topbar-inner {
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; min-height: 64px;
        }
        .brand { display: inline-flex; align-items: center; gap: 0.7rem; min-width: 0; }
        .brand-mark {
            display: inline-flex; width: 34px; height: 34px; align-items: center; justify-content: center;
            border-radius: 10px; background: var(--brand); color: #fff; font-size: 0.8rem; font-weight: 700; flex-shrink: 0;
        }
        .brand-text { display: flex; flex-direction: column; min-width: 0; line-height: 1.15; }
        .brand-name { font-weight: 700; letter-spacing: -0.02em; font-size: 1.05rem; }
        .brand-sub { font-size: 0.7rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 16rem; }
        .nav-links { display: none; align-items: center; gap: 0.25rem; }
        .nav-link {
            padding: 0.55rem 0.8rem; border-radius: 10px; font-size: 0.9375rem; font-weight: 500; color: var(--muted);
            transition: color 160ms ease, background-color 160ms ease;
        }
        .nav-link:hover, .nav-link:focus-visible { color: var(--ink); background: rgba(23,23,23,0.04); outline: none; }
        .top-actions { display: flex; align-items: center; gap: 0.5rem; }
        .lang-switch {
            display: inline-flex; align-items: center; border: 1px solid var(--border);
            border-radius: 10px; overflow: hidden; background: var(--surface);
        }
        .lang-switch a { padding: 0.4rem 0.65rem; font-size: 0.75rem; font-weight: 600; color: var(--muted); }
        .lang-switch a.is-active { background: var(--ink); color: #fff; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
            border-radius: var(--radius-btn); border: 1px solid transparent;
            padding: 0.75rem 1.15rem; font-size: 0.9375rem; font-weight: 600; cursor: pointer;
            transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease;
        }
        .btn:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-hover); }
        .btn-secondary { background: var(--surface); border-color: var(--border); color: var(--ink); }
        .btn-secondary:hover { background: #f3f3f1; }
        .menu-toggle {
            display: inline-flex; width: 40px; height: 40px; align-items: center; justify-content: center;
            border: 1px solid var(--border); border-radius: 10px; background: var(--surface); color: var(--ink); cursor: pointer;
        }
        .hero { padding: 3.25rem 0 2rem; }
        .hero-inner { width: min(100% - 2rem, 760px); margin: 0 auto; text-align: center; }
        .hero h1 { margin: 0; font-size: clamp(2.1rem, 4vw, 3rem); line-height: 1.12; letter-spacing: -0.035em; font-weight: 650; }
        .hero p { margin: 1rem auto 0; max-width: 38rem; font-size: 1.0625rem; line-height: 1.6; color: var(--muted); }
        .hero-actions { margin-top: 1.75rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 0.75rem; }
        .section { padding: 1.25rem 0 2.75rem; }
        .section-title { margin: 0; font-size: clamp(1.45rem, 2.4vw, 1.85rem); letter-spacing: -0.03em; font-weight: 650; }
        .section-subtitle { margin: 0.45rem 0 0; color: var(--muted); font-size: 1rem; line-height: 1.55; }
        .cards { display: grid; grid-template-columns: 1fr; gap: 1.25rem; margin-top: 1.5rem; }
        .card {
            display: flex; flex-direction: column; gap: 0.65rem; min-height: 100%;
            padding: 1.5rem; border: 1px solid var(--border); border-radius: var(--radius-card); background: var(--surface);
            transition: border-color 160ms ease, background-color 160ms ease;
        }
        a.card:hover, a.card:focus-visible { border-color: #d1d5db; background: #fffcfc; outline: none; }
        .card-icon {
            width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center;
            border-radius: 12px; background: rgba(122, 36, 44, 0.08); color: var(--brand);
        }
        .card h3 { margin: 0; font-size: 1.125rem; letter-spacing: -0.02em; font-weight: 600; }
        .card p { margin: 0; color: var(--muted); font-size: 0.975rem; line-height: 1.55; flex: 1; }
        .card-link { margin-top: 0.35rem; font-size: 0.925rem; font-weight: 600; color: var(--brand); }
        .split { display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-top: 0.5rem; }
        .panel { border: 1px solid var(--border); border-radius: var(--radius-card); background: var(--surface); padding: 1.5rem; }
        .panel-head { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; margin-bottom: 1.1rem; }
        .panel-head h2 { margin: 0; font-size: 1.25rem; letter-spacing: -0.02em; font-weight: 650; }
        .panel-head a { font-size: 0.875rem; font-weight: 600; color: var(--brand); }
        .list { display: grid; gap: 0.85rem; }
        .list-item { display: grid; gap: 0.35rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border); }
        .list-item:last-child { padding-bottom: 0; border-bottom: 0; }
        .list-item button { all: unset; cursor: pointer; display: grid; gap: 0.35rem; }
        .list-item button:hover .list-title, .list-item button:focus-visible .list-title { color: var(--brand); }
        .list-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 0.45rem; font-size: 0.8125rem; color: var(--muted); }
        .tag {
            display: inline-flex; align-items: center; border-radius: 999px; border: 1px solid var(--border);
            background: #fafafa; padding: 0.15rem 0.55rem; font-size: 0.75rem; font-weight: 600; color: var(--muted);
        }
        .list-title { font-size: 1rem; font-weight: 600; letter-spacing: -0.015em; transition: color 160ms ease; }
        .empty { color: var(--muted); font-size: 0.95rem; line-height: 1.5; }
        .footer { margin-top: auto; border-top: 1px solid var(--border); padding: 1.5rem 0 2rem; }
        .footer-inner {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 0.75rem; color: var(--muted); font-size: 0.875rem;
        }
        .footer-links { display: flex; flex-wrap: wrap; gap: 0.9rem; }
        .footer-links a:hover { color: var(--ink); }
        .modal-backdrop {
            position: fixed; inset: 0; z-index: 60; display: flex; align-items: flex-end; justify-content: center;
            padding: 1rem; background: rgba(23, 23, 23, 0.4);
        }
        .modal {
            width: min(100%, 40rem); max-height: 85vh; overflow: auto;
            border: 1px solid var(--border); border-radius: 16px; background: var(--surface); padding: 1.5rem;
        }
        .modal-close {
            float: right; border: 0; background: transparent; color: var(--muted); font-size: 1.1rem;
            cursor: pointer; border-radius: 8px; padding: 0.2rem 0.45rem;
        }
        .kb-prose { margin-top: 1.25rem; color: var(--muted); font-size: 0.975rem; line-height: 1.65; white-space: pre-wrap; word-break: break-word; }
        [x-cloak] { display: none !important; }
        @media (min-width: 640px) {
            .modal-backdrop { align-items: center; }
            .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (min-width: 960px) {
            .nav-links { display: flex; }
            .menu-toggle { display: none; }
            .cards.cards-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .split { grid-template-columns: 1.1fr 0.9fr; }
            .hero { padding: 4rem 0 2.5rem; }
            .brand-sub { max-width: 22rem; }
        }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
