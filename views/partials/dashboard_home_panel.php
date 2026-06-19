<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'dashboard'" x-cloak class="space-y-8">
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

    <div x-show="dashboardStats" x-cloak class="space-y-8">
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

            <div class="grid gap-6 lg:grid-cols-5">
                <article class="rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-sm lg:col-span-3">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('dashboard_category_chart_title'), ENT_QUOTES, 'UTF-8') ?></h2>
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
                        <h2 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('dashboard_activity_title'), ENT_QUOTES, 'UTF-8') ?></h2>
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
    </div>
</section>
