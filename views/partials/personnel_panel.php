<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'personnel'" x-cloak class="space-y-6">
    <div>
        <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('personnel_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('personnel_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div x-show="offboardSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="offboardSuccessMessage"></div>
    <div x-show="offboardErrorMessage" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="offboardErrorMessage"></div>

    <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <div class="border-b border-zinc-200 px-6 py-4">
            <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('personnel_list_title'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('personnel_list_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200">
                <thead class="bg-zinc-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('personnel_col_name'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('personnel_col_email'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('personnel_col_department'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('personnel_col_assets'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('personnel_col_status'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 bg-white">
                    <template x-for="person in personnel" :key="person.id">
                        <tr class="hover:bg-zinc-50/80">
                            <td class="px-6 py-4 text-sm font-medium text-zinc-900" x-text="person.name"></td>
                            <td class="px-6 py-4 text-sm text-zinc-600" x-text="person.email"></td>
                            <td class="px-6 py-4 text-sm text-zinc-600" x-text="person.department || '—'"></td>
                            <td class="px-6 py-4 text-sm tabular-nums text-zinc-600" x-text="person.assigned_asset_count || 0"></td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                    :class="person.status === 'offboarded' ? 'bg-zinc-100 text-zinc-600 ring-zinc-500/20' : 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'"
                                    x-text="resolvePersonnelStatus(person.status)"
                                ></span>
                            </td>
                            <td class="px-6 py-4">
                                <button
                                    type="button"
                                    x-show="person.status !== 'offboarded'"
                                    @click="startOffboarding(person)"
                                    :disabled="isOffboarding"
                                    class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <?= htmlspecialchars(__('action_start_offboarding'), ENT_QUOTES, 'UTF-8') ?>
                                </button>
                                <span x-show="person.status === 'offboarded'" class="text-xs text-zinc-400"><?= htmlspecialchars(__('personnel_offboarded_label'), ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>
</section>
