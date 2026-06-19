<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'dashboard'" x-cloak class="space-y-10">
    <div x-show="dashboardLoading && !dashboardStats" class="flex min-h-[320px] items-center justify-center rounded-2xl border border-zinc-200 bg-white">
        <div class="flex items-center gap-3 text-sm text-zinc-500">
            <svg class="h-5 w-5 animate-spin text-zinc-400" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <?= htmlspecialchars(__('dashboard_loading'), ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>

    <div x-show="dashboardError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="dashboardError"></div>

    <div x-show="dashboardStats" x-cloak class="space-y-10">
        <!-- Priority 1: Help Desk -->
        <section aria-labelledby="dashboard-helpdesk-heading">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                    <h2 id="dashboard-helpdesk-heading" class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('dashboard_section_helpdesk'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('dashboard_section_helpdesk_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold uppercase tracking-wider text-gray-500"><?= htmlspecialchars(__('dashboard_ticket_open'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-3 text-4xl font-bold tabular-nums tracking-tight text-gray-900" x-text="dashboardStats.help_desk?.open ?? 0"></p>
                            <p class="mt-2 text-xs text-gray-500"><?= htmlspecialchars(__('dashboard_ticket_open_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859M12 3v8.25m0 0l-3-3m3 3l3-3" />
                            </svg>
                        </span>
                    </div>
                </article>
                <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold uppercase tracking-wider text-gray-500"><?= htmlspecialchars(__('dashboard_ticket_in_progress'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-3 text-4xl font-bold tabular-nums tracking-tight text-gray-900" x-text="dashboardStats.help_desk?.in_progress ?? 0"></p>
                            <p class="mt-2 text-xs text-gray-500"><?= htmlspecialchars(__('dashboard_ticket_in_progress_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                        </span>
                    </div>
                </article>
                <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold uppercase tracking-wider text-gray-500"><?= htmlspecialchars(__('dashboard_ticket_critical'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-3 text-4xl font-bold tabular-nums tracking-tight text-gray-900" x-text="dashboardStats.help_desk?.critical ?? 0"></p>
                            <p class="mt-2 text-xs text-gray-500"><?= htmlspecialchars(__('dashboard_ticket_critical_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </span>
                    </div>
                </article>
            </div>
        </section>

        <!-- Priority 2: Assets & Inventory -->
        <section aria-labelledby="dashboard-assets-heading">
            <div class="mb-4">
                <h2 id="dashboard-assets-heading" class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('dashboard_section_assets'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('dashboard_section_assets_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-sm transition hover:border-zinc-300">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('metric_total_assets'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-3 text-3xl font-semibold tabular-nums tracking-tight text-zinc-900" x-text="dashboardStats.summary_cards.total"></p>
                    <p class="mt-2 text-xs text-zinc-500"><?= htmlspecialchars(__('metric_total_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                </article>
                <article class="rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-sm transition hover:border-zinc-300">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('metric_deployed'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-3 text-3xl font-semibold tabular-nums tracking-tight text-zinc-900" x-text="dashboardStats.summary_cards.deployed"></p>
                    <p class="mt-2 text-xs text-zinc-500"><?= htmlspecialchars(__('metric_deployed_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                </article>
                <article class="rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-sm transition hover:border-zinc-300">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('metric_in_storage'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-3 text-3xl font-semibold tabular-nums tracking-tight text-zinc-900" x-text="dashboardStats.summary_cards.in_storage"></p>
                    <p class="mt-2 text-xs text-zinc-500"><?= htmlspecialchars(__('metric_in_storage_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                </article>
                <article class="rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-sm transition hover:border-zinc-300">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('metric_broken'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-3 text-3xl font-semibold tabular-nums tracking-tight text-zinc-900" x-text="dashboardStats.summary_cards.broken"></p>
                    <p class="mt-2 text-xs text-zinc-500"><?= htmlspecialchars(__('metric_broken_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                </article>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-5">
                <article class="rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-sm lg:col-span-3">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('dashboard_category_chart_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                            <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('dashboard_category_chart_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                    <div class="relative mt-6 h-72" x-show="(dashboardStats.by_category || []).length > 0">
                        <canvas id="dashboardCategoryChart" aria-label="<?= htmlspecialchars(__('dashboard_category_chart_title'), ENT_QUOTES, 'UTF-8') ?>"></canvas>
                    </div>
                    <p
                        x-show="(dashboardStats.by_category || []).length === 0"
                        x-cloak
                        class="mt-6 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-10 text-center text-sm text-zinc-500"
                    >
                        <?= htmlspecialchars(__('analytics_no_category_data'), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </article>

                <article class="rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-sm lg:col-span-2">
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('dashboard_activity_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('dashboard_activity_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="mt-6">
                        <p
                            x-show="(dashboardStats.recent_activities || []).length === 0"
                            x-cloak
                            class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-center text-sm text-zinc-500"
                        >
                            <?= htmlspecialchars(__('dashboard_activity_empty'), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <ol
                            x-show="(dashboardStats.recent_activities || []).length > 0"
                            class="relative space-y-0"
                        >
                            <template x-for="(activity, index) in dashboardStats.recent_activities" :key="'activity-' + activity.id">
                                <li class="relative flex gap-4 pb-6 last:pb-0">
                                    <div class="relative flex flex-col items-center">
                                        <span class="relative z-10 mt-1.5 h-2 w-2 shrink-0 rounded-full bg-zinc-900 ring-4 ring-white"></span>
                                        <span
                                            x-show="index < dashboardStats.recent_activities.length - 1"
                                            class="absolute top-3 h-full w-px bg-zinc-200"
                                            aria-hidden="true"
                                        ></span>
                                    </div>
                                    <div class="min-w-0 flex-1 border-b border-zinc-100 pb-6 last:border-b-0 last:pb-0">
                                        <p class="text-sm font-medium leading-snug text-zinc-900" x-text="formatDashboardActivity(activity)"></p>
                                        <p class="mt-1 text-xs text-zinc-400" x-text="formatDashboardActivityTime(activity.created_at)"></p>
                                    </div>
                                </li>
                            </template>
                        </ol>
                    </div>
                </article>
            </div>
        </section>

        <!-- Priority 3: Licenses & Consumables -->
        <section aria-labelledby="dashboard-ops-heading">
            <div class="mb-4">
                <h2 id="dashboard-ops-heading" class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('dashboard_section_ops'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('dashboard_section_ops_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <article class="rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('nav_licenses'), ENT_QUOTES, 'UTF-8') ?></h3>
                            <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('dashboard_licenses_widget_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-zinc-100 bg-zinc-50/60 px-4 py-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('dashboard_license_total'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900" x-text="dashboardStats.licenses?.total ?? 0"></p>
                        </div>
                        <div class="rounded-xl border border-amber-100 bg-amber-50/60 px-4 py-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-amber-700"><?= htmlspecialchars(__('dashboard_license_expiring'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-2 text-2xl font-semibold tabular-nums text-amber-950" x-text="dashboardStats.licenses?.expiring_soon ?? 0"></p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('dashboard_license_seat_usage'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p
                            x-show="(dashboardStats.licenses?.seat_usage || []).length === 0"
                            x-cloak
                            class="mt-3 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-6 text-center text-sm text-zinc-500"
                        >
                            <?= htmlspecialchars(__('dashboard_license_seat_usage_empty'), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <ul x-show="(dashboardStats.licenses?.seat_usage || []).length > 0" class="mt-4 space-y-4">
                            <template x-for="license in dashboardStats.licenses.seat_usage" :key="'license-usage-' + license.id">
                                <li>
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-zinc-900" x-text="license.name"></p>
                                            <p class="truncate text-xs text-zinc-500" x-text="license.vendor"></p>
                                        </div>
                                        <p class="shrink-0 text-xs tabular-nums text-zinc-500">
                                            <span x-text="license.assigned_seats"></span>/<span x-text="license.seats"></span>
                                        </p>
                                    </div>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100">
                                        <div
                                            class="h-full rounded-full transition-all"
                                            :class="license.usage_percentage >= 90 ? 'bg-rose-500' : (license.usage_percentage >= 70 ? 'bg-amber-500' : 'bg-zinc-900')"
                                            :style="'width:' + Math.min(100, Number(license.usage_percentage || 0)) + '%'"
                                        ></div>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>
                </article>

                <article class="rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('nav_consumables'), ENT_QUOTES, 'UTF-8') ?></h3>
                            <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('dashboard_consumables_widget_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-zinc-100 bg-zinc-50/60 px-4 py-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('dashboard_consumable_total'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-900" x-text="dashboardStats.consumables?.total ?? 0"></p>
                        </div>
                        <div class="rounded-xl border border-rose-100 bg-rose-50/60 px-4 py-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-rose-700"><?= htmlspecialchars(__('dashboard_consumable_low_stock'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-2 text-2xl font-semibold tabular-nums text-rose-950" x-text="dashboardStats.consumables?.low_stock ?? 0"></p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('dashboard_consumable_stock_levels'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p
                            x-show="(dashboardStats.consumables?.low_stock_items || []).length === 0"
                            x-cloak
                            class="mt-3 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-6 text-center text-sm text-zinc-500"
                        >
                            <?= htmlspecialchars(__('dashboard_consumable_stock_empty'), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <ul x-show="(dashboardStats.consumables?.low_stock_items || []).length > 0" class="mt-4 space-y-4">
                            <template x-for="item in dashboardStats.consumables.low_stock_items" :key="'consumable-stock-' + item.id">
                                <li>
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <p class="min-w-0 truncate font-medium text-zinc-900" x-text="item.name"></p>
                                        <p class="shrink-0 text-xs tabular-nums text-zinc-500">
                                            <span x-text="item.quantity"></span> / <?= htmlspecialchars(__('dashboard_consumable_min_label'), ENT_QUOTES, 'UTF-8') ?> <span x-text="item.min_stock_level"></span>
                                        </p>
                                    </div>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100">
                                        <div
                                            class="h-full rounded-full bg-rose-500 transition-all"
                                            :style="'width:' + Math.min(100, Number(item.stock_percentage || 0)) + '%'"
                                        ></div>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>
                </article>
            </div>
        </section>
    </div>
</section>
