<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'reports'" x-cloak class="space-y-8">
    <div>
        <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('reports_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('reports_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <p x-show="reportsLoading && !reportsStats" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('reports_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p x-show="reportsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="reportsError"></p>

    <div x-show="reportsStats" x-cloak class="space-y-8">
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

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <article class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
                <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('reports_avg_first_response'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="mt-4 text-3xl font-bold tabular-nums text-zinc-900" x-text="reportsStats.performance?.avg_first_response_label ?? '—'"></p>
                <p class="mt-2 text-sm text-zinc-500"><?= htmlspecialchars(__('reports_avg_first_response_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            </article>
            <article class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
                <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('reports_avg_resolution'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="mt-4 text-3xl font-bold tabular-nums text-zinc-900" x-text="reportsStats.performance?.avg_resolution_label ?? '—'"></p>
                <p class="mt-2 text-sm text-zinc-500"><?= htmlspecialchars(__('reports_avg_resolution_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            </article>
        </div>

        <article class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('reports_category_distribution'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('reports_category_distribution_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>

            <p x-show="!(reportsStats.by_category?.length)" class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500">
                <?= htmlspecialchars(__('reports_category_empty'), ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div x-show="reportsStats.by_category?.length" class="space-y-4">
                <template x-for="row in reportsStats.by_category" :key="`${row.category_id ?? 'none'}-${row.category_name}`">
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="h-3 w-3 shrink-0 rounded-full" :style="`background-color: ${row.color_code}`"></span>
                                <span class="truncate font-medium text-zinc-800" x-text="row.category_name"></span>
                            </div>
                            <span class="shrink-0 tabular-nums text-zinc-500">
                                <span x-text="row.count"></span>
                                <span class="text-zinc-400">·</span>
                                <span x-text="`${row.percentage}%`"></span>
                            </span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-zinc-100">
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
            <div class="border-b border-zinc-200 px-6 py-4">
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
                    <tbody class="divide-y divide-zinc-200">
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
