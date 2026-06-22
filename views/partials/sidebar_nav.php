<?php

declare(strict_types=1);

/**
 * @var bool $isEndUser
 * @var bool $canManageAssets
 * @var bool $canAccessPersonnel
 * @var bool $canAccessSettings
 */

$sectionHeaderClass = 'mt-6 mb-2 px-3 text-[11px] font-bold uppercase tracking-wider text-gray-400';

?>
<nav class="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-3 py-4">
    <?php if ($isEndUser): ?>
        <button
            type="button"
            @click="activeView = 'my_assets'; fetchPortalAssets()"
            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900"
            :class="activeView === 'my_assets' ? 'bg-gray-900 font-semibold text-white shadow-sm hover:bg-gray-900 hover:text-white' : ''"
        >
            <svg class="h-5 w-5 flex-shrink-0 text-gray-400 transition-colors group-hover:text-gray-500" :class="activeView === 'my_assets' ? 'text-white group-hover:text-white' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"></path>
            </svg>
            <span><?= htmlspecialchars(__('portal_tab_assets'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <button
            type="button"
            @click="activeView = 'my_tickets'; fetchPortalTickets()"
            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900"
            :class="activeView === 'my_tickets' ? 'bg-gray-900 font-semibold text-white shadow-sm hover:bg-gray-900 hover:text-white' : ''"
        >
            <svg class="h-5 w-5 flex-shrink-0 text-gray-400 transition-colors group-hover:text-gray-500" :class="activeView === 'my_tickets' ? 'text-white group-hover:text-white' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z"></path>
            </svg>
            <span><?= htmlspecialchars(__('portal_tab_tickets'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
    <?php else: ?>
        <?php if ($canManageAssets): ?>
        <button
            type="button"
            @click="activeView = 'dashboard'; fetchDashboardStats()"
            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900"
            :class="activeView === 'dashboard' ? 'bg-gray-900 font-semibold text-white shadow-sm hover:bg-gray-900 hover:text-white' : ''"
        >
            <svg class="h-5 w-5 flex-shrink-0 text-gray-400 transition-colors group-hover:text-gray-500" :class="activeView === 'dashboard' ? 'text-white group-hover:text-white' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"></path>
            </svg>
            <span><?= htmlspecialchars(__('nav_dashboard'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <?php endif; ?>

        <?php if ($canManageAssets): ?>
        <div class="<?= $sectionHeaderClass ?>"><?= htmlspecialchars(__('nav_section_operations'), ENT_QUOTES, 'UTF-8') ?></div>
        <button
            type="button"
            @click="activeView = 'helpdesk'; fetchTickets()"
            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900"
            :class="activeView === 'helpdesk' ? 'bg-gray-900 font-semibold text-white shadow-sm hover:bg-gray-900 hover:text-white' : ''"
        >
            <svg class="h-5 w-5 flex-shrink-0 text-gray-400 transition-colors group-hover:text-gray-500" :class="activeView === 'helpdesk' ? 'text-white group-hover:text-white' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z"></path>
            </svg>
            <span><?= htmlspecialchars(__('nav_helpdesk'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <?php endif; ?>

        <div class="<?= $sectionHeaderClass ?>"><?= htmlspecialchars(__('nav_section_asset_management'), ENT_QUOTES, 'UTF-8') ?></div>
        <button
            type="button"
            @click="activeView = 'assets'"
            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900"
            :class="activeView === 'assets' ? 'bg-gray-900 font-semibold text-white shadow-sm hover:bg-gray-900 hover:text-white' : ''"
        >
            <svg class="h-5 w-5 flex-shrink-0 text-gray-400 transition-colors group-hover:text-gray-500" :class="activeView === 'assets' ? 'text-white group-hover:text-white' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"></path>
            </svg>
            <span><?= htmlspecialchars(__('nav_assets'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <?php if ($canManageAssets): ?>
        <button
            type="button"
            @click="activeView = 'licenses'; fetchLicenses()"
            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900"
            :class="activeView === 'licenses' ? 'bg-gray-900 font-semibold text-white shadow-sm hover:bg-gray-900 hover:text-white' : ''"
        >
            <svg class="h-5 w-5 flex-shrink-0 text-gray-400 transition-colors group-hover:text-gray-500" :class="activeView === 'licenses' ? 'text-white group-hover:text-white' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.904c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"></path>
            </svg>
            <span><?= htmlspecialchars(__('nav_licenses'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <button
            type="button"
            @click="activeView = 'consumables'; fetchConsumables()"
            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900"
            :class="activeView === 'consumables' ? 'bg-gray-900 font-semibold text-white shadow-sm hover:bg-gray-900 hover:text-white' : ''"
        >
            <svg class="h-5 w-5 flex-shrink-0 text-gray-400 transition-colors group-hover:text-gray-500" :class="activeView === 'consumables' ? 'text-white group-hover:text-white' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"></path>
            </svg>
            <span><?= htmlspecialchars(__('nav_consumables'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <?php endif; ?>

        <?php if ($canManageAssets): ?>
        <div class="<?= $sectionHeaderClass ?>"><?= htmlspecialchars(__('nav_section_infrastructure'), ENT_QUOTES, 'UTF-8') ?></div>
        <button
            type="button"
            @click="activeView = 'ipam'; ipamSubView = 'networks'; fetchIpNetworks()"
            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900"
            :class="activeView === 'ipam' ? 'bg-gray-900 font-semibold text-white shadow-sm hover:bg-gray-900 hover:text-white' : ''"
        >
            <svg class="h-5 w-5 flex-shrink-0 text-gray-400 transition-colors group-hover:text-gray-500" :class="activeView === 'ipam' ? 'text-white group-hover:text-white' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"></path>
            </svg>
            <span><?= htmlspecialchars(__('nav_ipam'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <?php endif; ?>

        <?php if ($canAccessPersonnel || $canAccessSettings): ?>
        <div class="<?= $sectionHeaderClass ?>"><?= htmlspecialchars(__('nav_section_system'), ENT_QUOTES, 'UTF-8') ?></div>
        <?php if ($canAccessPersonnel): ?>
        <button
            type="button"
            @click="activeView = 'personnel'; fetchPersonnel()"
            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900"
            :class="activeView === 'personnel' ? 'bg-gray-900 font-semibold text-white shadow-sm hover:bg-gray-900 hover:text-white' : ''"
        >
            <svg class="h-5 w-5 flex-shrink-0 text-gray-400 transition-colors group-hover:text-gray-500" :class="activeView === 'personnel' ? 'text-white group-hover:text-white' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"></path>
            </svg>
            <span><?= htmlspecialchars(__('nav_personnel'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <?php endif; ?>
        <?php if ($canAccessSettings): ?>
        <button
            type="button"
            @click="activeView = 'audit_logs'; fetchAuditLogs()"
            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900"
            :class="activeView === 'audit_logs' ? 'bg-gray-900 font-semibold text-white shadow-sm hover:bg-gray-900 hover:text-white' : ''"
        >
            <svg class="h-5 w-5 flex-shrink-0 text-gray-400 transition-colors group-hover:text-gray-500" :class="activeView === 'audit_logs' ? 'text-white group-hover:text-white' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.072C9.877 3.768 9.048 4.733 9.048 6.108v8.892a3 3 0 003 3h4.5a3 3 0 003-3V9.75a3 3 0 00-3-3h-1.5a3 3 0 00-3 3v.5"></path>
            </svg>
            <span><?= htmlspecialchars(__('nav_audit_logs'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <button
            type="button"
            @click="activeView = 'settings'; settingsTab = 'general'; $nextTick(() => initQuillEditor())"
            class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900"
            :class="activeView === 'settings' ? 'bg-gray-900 font-semibold text-white shadow-sm hover:bg-gray-900 hover:text-white' : ''"
        >
            <svg class="h-5 w-5 flex-shrink-0 text-gray-400 transition-colors group-hover:text-gray-500" :class="activeView === 'settings' ? 'text-white group-hover:text-white' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.217.456c.355.133.75.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            <span><?= htmlspecialchars(__('nav_settings'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</nav>
