<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'reports'" x-cloak class="space-y-8">
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-gradient-to-br from-zinc-900 via-zinc-800 to-zinc-900 p-8 text-white shadow-soft">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-zinc-400"><?= htmlspecialchars(__('reports_executive_badge'), ENT_QUOTES, 'UTF-8') ?></p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight"><?= htmlspecialchars(__('reports_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-zinc-300"><?= htmlspecialchars(__('reports_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <button
                type="button"
                @click="fetchReports()"
                :disabled="reportsLoading"
                class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-white/15 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <svg class="h-4 w-4" :class="reportsLoading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"></path>
                </svg>
                <?= htmlspecialchars(__('reports_refresh'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>

    <p x-show="reportsLoading && !reportsStats" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('reports_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p x-show="reportsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="reportsError"></p>

    <div x-show="reportsStats" x-cloak class="space-y-8">
        <div>
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('reports_volume_heading'), ENT_QUOTES, 'UTF-8') ?></h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-sky-100 bg-gradient-to-br from-sky-50 to-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-sky-700"><?= htmlspecialchars(__('reports_volume_total'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-3 text-3xl font-bold tabular-nums tracking-tight text-sky-950" x-text="reportsStats.volume?.total ?? 0"></p>
                </article>
                <article class="rounded-2xl border border-amber-100 bg-gradient-to-br from-amber-50 to-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-amber-700"><?= htmlspecialchars(__('reports_volume_open'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-3 text-3xl font-bold tabular-nums tracking-tight text-amber-950" x-text="reportsStats.volume?.open ?? 0"></p>
                </article>
                <article class="rounded-2xl border border-violet-100 bg-gradient-to-br from-violet-50 to-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-violet-700"><?= htmlspecialchars(__('reports_volume_pending'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-3 text-3xl font-bold tabular-nums tracking-tight text-violet-950" x-text="reportsStats.volume?.pending ?? 0"></p>
                </article>
                <article class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white p-6 shadow-sm">
                    <p class="text-sm font-medium text-emerald-700"><?= htmlspecialchars(__('reports_volume_closed'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-3 text-3xl font-bold tabular-nums tracking-tight text-emerald-950" x-text="reportsStats.volume?.closed ?? 0"></p>
                </article>
            </div>
        </div>

        <div>
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('reports_performance_heading'), ENT_QUOTES, 'UTF-8') ?></h3>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <article class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('reports_avg_first_response'), ENT_QUOTES, 'UTF-8') ?></h4>
                            <p class="mt-3 text-3xl font-bold tabular-nums text-zinc-900" x-text="reportsStats.performance?.avg_first_response_label ?? '—'"></p>
                            <p class="mt-2 text-sm text-zinc-500"><?= htmlspecialchars(__('reports_avg_first_response_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                </article>
                <article class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('reports_avg_resolution'), ENT_QUOTES, 'UTF-8') ?></h4>
                            <p class="mt-3 text-3xl font-bold tabular-nums text-zinc-900" x-text="reportsStats.performance?.avg_resolution_label ?? '—'"></p>
                            <p class="mt-2 text-sm text-zinc-500"><?= htmlspecialchars(__('reports_avg_resolution_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                </article>
            </div>
        </div>

        <article class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('reports_category_distribution'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('reports_category_distribution_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <p x-show="!(reportsStats.by_category?.length)" class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500">
                <?= htmlspecialchars(__('reports_category_empty'), ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div x-show="reportsStats.by_category?.length" class="space-y-5">
                <template x-for="row in reportsStats.by_category" :key="`${row.category_id ?? 'none'}-${row.category_name}`">
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="h-3 w-3 shrink-0 rounded-full" :style="`background-color: ${row.color_code}`"></span>
                                <span class="truncate font-medium text-zinc-800" x-text="`${row.category_name}: ${row.percentage}%`"></span>
                            </div>
                            <span class="shrink-0 tabular-nums text-zinc-500" x-text="row.count"></span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-zinc-100">
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :style="`width: ${row.percentage}%; background-color: ${row.color_code}`"
                            ></div>
                        </div>
                    </div>
                </template>
            </div>
        </article>

        <article class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="border-b border-zinc-200 bg-zinc-50/80 px-6 py-4">
                <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('reports_staff_performance'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('reports_staff_performance_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <p x-show="!(reportsStats.staff_performance?.length)" class="px-6 py-8 text-sm text-zinc-500">
                <?= htmlspecialchars(__('reports_staff_empty'), ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div x-show="reportsStats.staff_performance?.length" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_staff_name'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_staff_assigned'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_staff_resolved'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_staff_active_load'), ENT_QUOTES, 'UTF-8') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white">
                        <template x-for="staff in reportsStats.staff_performance" :key="staff.user_id">
                            <tr class="hover:bg-zinc-50/80">
                                <td class="px-6 py-4 text-sm font-medium text-zinc-900" x-text="staff.user_name"></td>
                                <td class="px-6 py-4 text-sm tabular-nums text-zinc-700" x-text="staff.assigned_count"></td>
                                <td class="px-6 py-4 text-sm tabular-nums text-zinc-700" x-text="staff.resolved_count"></td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                        :class="staff.active_load > 0 ? 'bg-amber-50 text-amber-800 ring-amber-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200'"
                                        x-text="staff.active_load"
                                    ></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </article>
    </div>
</section>
