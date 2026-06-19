<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'dashboard'" x-cloak>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

        <div x-show="dashboardLoading && !dashboardStats" class="flex min-h-[320px] items-center justify-center rounded-2xl border border-gray-200 bg-white">
            <div class="flex items-center gap-3 text-sm text-gray-500">
                <svg class="h-5 w-5 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <?= htmlspecialchars(__('dashboard_loading'), ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>

        <div x-show="dashboardError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="dashboardError"></div>

        <div x-show="dashboardStats" x-cloak class="grid grid-cols-1 gap-8 lg:grid-cols-3">

            <!-- LEFT: Dynamic data & system volume (span 2) -->
            <div class="space-y-8 lg:col-span-2">

                <!-- Header -->
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold tracking-tight text-gray-900"><?= htmlspecialchars(__('dashboard_overview_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="mt-1 text-sm text-gray-500"><?= htmlspecialchars(__('dashboard_overview_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-green-200/70 bg-green-50 px-3 py-1.5 text-xs font-medium text-green-700">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                        </span>
                        <?= htmlspecialchars(__('dashboard_status_all_active'), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <!-- Top row: 3 unified stat cards -->
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <article class="rounded-2xl border border-gray-200 bg-white p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-500"><?= htmlspecialchars(__('dashboard_ticket_open'), ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-3 text-3xl font-bold tabular-nums tracking-tight text-gray-900" x-text="dashboardStats.help_desk?.open ?? 0"></p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-50 text-gray-400">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859M12 3v8.25m0 0l-3-3m3 3l3-3" />
                                </svg>
                            </span>
                        </div>
                    </article>
                    <article class="rounded-2xl border border-gray-200 bg-white p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-500"><?= htmlspecialchars(__('dashboard_ticket_in_progress'), ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-3 text-3xl font-bold tabular-nums tracking-tight text-gray-900" x-text="dashboardStats.help_desk?.in_progress ?? 0"></p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-50 text-gray-400">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                            </span>
                        </div>
                    </article>
                    <article class="rounded-2xl border border-gray-200 bg-white p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-500"><?= htmlspecialchars(__('dashboard_ticket_critical'), ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-3 text-3xl font-bold tabular-nums tracking-tight text-gray-900" x-text="dashboardStats.help_desk?.critical ?? 0"></p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-50" :class="(dashboardStats.help_desk?.critical ?? 0) > 0 ? 'text-red-500' : 'text-gray-400'">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </span>
                        </div>
                    </article>
                </div>

                <!-- Bottom row: Asset & infrastructure health -->
                <article class="rounded-2xl border border-gray-200 bg-white p-6 sm:p-8">
                    <div class="mb-6">
                        <h3 class="text-base font-semibold tracking-tight text-gray-900"><?= htmlspecialchars(__('dashboard_distribution_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="mt-1 text-sm text-gray-500"><?= htmlspecialchars(__('dashboard_distribution_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>

                    <div class="space-y-7">
                        <!-- IP Address Allocation -->
                        <div>
                            <div class="flex items-end justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                                    </svg>
                                    <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars(__('dashboard_ip_allocation'), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <span class="text-xs tabular-nums text-gray-500">
                                    <span class="font-semibold text-gray-900" x-text="dashboardStats.infrastructure?.ip_used ?? 0"></span>
                                    / <span x-text="dashboardStats.infrastructure?.ip_capacity ?? 0"></span>
                                </span>
                            </div>
                            <div class="mt-2.5 h-2 overflow-hidden rounded-full bg-gray-100">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="(dashboardStats.infrastructure?.ip_utilization ?? 0) >= 90 ? 'bg-red-500' : ((dashboardStats.infrastructure?.ip_utilization ?? 0) >= 70 ? 'bg-amber-500' : 'bg-gray-900')"
                                    :style="'width:' + Math.min(100, Number(dashboardStats.infrastructure?.ip_utilization || 0)) + '%'"
                                ></div>
                            </div>
                            <p class="mt-1.5 text-xs text-gray-400">
                                <span x-text="dashboardStats.infrastructure?.ip_utilization ?? 0"></span>% · <span x-text="dashboardStats.infrastructure?.network_count ?? 0"></span> <?= htmlspecialchars(__('dashboard_ip_networks_label'), ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>

                        <!-- Active license capacity / countdown -->
                        <div>
                            <div class="flex items-end justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.904c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                                    </svg>
                                    <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars(__('dashboard_license_countdown'), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <span class="text-xs tabular-nums text-gray-500">
                                    <span class="font-semibold" :class="(dashboardStats.licenses?.expiring_soon ?? 0) > 0 ? 'text-amber-600' : 'text-gray-900'" x-text="dashboardStats.licenses?.expiring_soon ?? 0"></span>
                                    / <span x-text="dashboardStats.licenses?.total ?? 0"></span>
                                </span>
                            </div>
                            <div class="mt-2.5 h-2 overflow-hidden rounded-full bg-gray-100">
                                <div
                                    class="h-full rounded-full bg-amber-500 transition-all duration-500"
                                    :style="'width:' + Math.min(100, (Number(dashboardStats.licenses?.total || 0) > 0 ? (Number(dashboardStats.licenses?.expiring_soon || 0) / Number(dashboardStats.licenses?.total || 1)) * 100 : 0)) + '%'"
                                ></div>
                            </div>
                            <p class="mt-1.5 text-xs text-gray-400"><?= htmlspecialchars(__('dashboard_license_countdown_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>

                        <!-- Consumable stock warning -->
                        <div>
                            <div class="flex items-end justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                    </svg>
                                    <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars(__('dashboard_consumable_warning'), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <span class="text-xs tabular-nums text-gray-500">
                                    <span class="font-semibold" :class="(dashboardStats.consumables?.low_stock ?? 0) > 0 ? 'text-red-600' : 'text-gray-900'" x-text="dashboardStats.consumables?.low_stock ?? 0"></span>
                                    / <span x-text="dashboardStats.consumables?.total ?? 0"></span>
                                </span>
                            </div>
                            <div class="mt-2.5 h-2 overflow-hidden rounded-full bg-gray-100">
                                <div
                                    class="h-full rounded-full bg-red-500 transition-all duration-500"
                                    :style="'width:' + Math.min(100, (Number(dashboardStats.consumables?.total || 0) > 0 ? (Number(dashboardStats.consumables?.low_stock || 0) / Number(dashboardStats.consumables?.total || 1)) * 100 : 0)) + '%'"
                                ></div>
                            </div>
                            <p class="mt-1.5 text-xs text-gray-400"><?= htmlspecialchars(__('dashboard_consumable_warning_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>

                        <!-- Total asset footprint -->
                        <div class="grid grid-cols-3 gap-4 border-t border-gray-100 pt-6">
                            <div>
                                <p class="text-xs font-medium text-gray-400"><?= htmlspecialchars(__('metric_total_assets'), ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-1.5 text-2xl font-bold tabular-nums tracking-tight text-gray-900" x-text="dashboardStats.summary_cards?.total ?? 0"></p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-400"><?= htmlspecialchars(__('metric_deployed'), ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-1.5 text-2xl font-bold tabular-nums tracking-tight text-gray-900" x-text="dashboardStats.summary_cards?.deployed ?? 0"></p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-400"><?= htmlspecialchars(__('metric_broken'), ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-1.5 text-2xl font-bold tabular-nums tracking-tight" :class="(dashboardStats.summary_cards?.broken ?? 0) > 0 ? 'text-red-600' : 'text-gray-900'" x-text="dashboardStats.summary_cards?.broken ?? 0"></p>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <!-- RIGHT: Operational interaction & logging (span 1) -->
            <div class="space-y-8">

                <!-- Quick Actions -->
                <article class="rounded-2xl border border-gray-200 bg-white p-6">
                    <h3 class="text-base font-semibold tracking-tight text-gray-900"><?= htmlspecialchars(__('dashboard_quick_actions'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <div class="mt-4 space-y-2.5">
                        <button
                            type="button"
                            @click="openAddModal()"
                            class="group flex w-full items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-left text-sm font-medium text-gray-700 transition-all hover:bg-gray-50"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-400 transition-colors group-hover:text-gray-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </span>
                            <?= htmlspecialchars(__('add_asset'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button
                            type="button"
                            @click="openTicketModal()"
                            class="group flex w-full items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-left text-sm font-medium text-gray-700 transition-all hover:bg-gray-50"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-400 transition-colors group-hover:text-gray-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" />
                                </svg>
                            </span>
                            <?= htmlspecialchars(__('dashboard_action_new_ticket'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button
                            type="button"
                            @click="activeView = 'assets'; $nextTick(() => openImportModal())"
                            class="group flex w-full items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-left text-sm font-medium text-gray-700 transition-all hover:bg-gray-50"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-400 transition-colors group-hover:text-gray-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                </svg>
                            </span>
                            <?= htmlspecialchars(__('dashboard_action_import_export'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </article>

                <!-- Live Activity Feed -->
                <article class="rounded-2xl border border-gray-200 bg-white p-6">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-semibold tracking-tight text-gray-900"><?= htmlspecialchars(__('dashboard_live_logs'), ENT_QUOTES, 'UTF-8') ?></h3>
                        <span class="relative flex h-2 w-2" aria-hidden="true">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                        </span>
                    </div>

                    <p
                        x-show="(dashboardStats.recent_logs || []).length === 0"
                        x-cloak
                        class="mt-5 rounded-xl border border-dashed border-gray-200 bg-gray-50/60 px-4 py-8 text-center text-sm text-gray-500"
                    >
                        <?= htmlspecialchars(__('dashboard_live_logs_empty'), ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <ol x-show="(dashboardStats.recent_logs || []).length > 0" class="mt-5">
                        <template x-for="(log, index) in (dashboardStats.recent_logs || [])" :key="'log-' + log.id">
                            <li class="relative flex gap-3.5 pb-5 last:pb-0">
                                <div class="relative flex flex-col items-center">
                                    <span class="relative z-10 mt-1 h-2 w-2 shrink-0 rounded-full ring-4 ring-white" :class="auditFeedDotClass(log.action_type)"></span>
                                    <span
                                        x-show="index < (dashboardStats.recent_logs.length - 1)"
                                        class="absolute top-2.5 h-full w-px bg-gray-200"
                                        aria-hidden="true"
                                    ></span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm leading-snug text-gray-900" x-html="formatAuditFeed(log)"></p>
                                    <p class="mt-0.5 font-mono text-xs text-gray-400" x-text="formatAuditFeedTime(log.created_at)"></p>
                                </div>
                            </li>
                        </template>
                    </ol>
                </article>
            </div>
        </div>
    </div>
</section>
