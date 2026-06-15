<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'personnel'" x-cloak class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('personnel_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('personnel_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <button
            type="button"
            @click="syncPersonnelDirectory()"
            :disabled="personnelSyncing"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
        >
            <svg x-show="personnelSyncing" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span x-text="personnelSyncing ? window.__i18n.personnel_syncing : window.__i18n.personnel_sync_button"></span>
        </button>
    </div>

    <div x-show="personnelSyncMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="personnelSyncMessage"></div>
    <div x-show="personnelSyncError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="personnelSyncError"></div>
    <div x-show="offboardSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="offboardSuccessMessage"></div>
    <div x-show="offboardErrorMessage" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="offboardErrorMessage"></div>
    <div x-show="personnelError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="personnelError"></div>

    <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <div class="border-b border-zinc-200 px-6 py-4">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('personnel_list_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('personnel_list_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="relative w-full sm:max-w-xs">
                    <input
                        type="search"
                        x-model="personnelSearch"
                        @input.debounce.350ms="onPersonnelSearchInput()"
                        :placeholder="window.__i18n.personnel_search_placeholder"
                        class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-200"
                    >
                </div>
            </div>
        </div>

        <div x-show="personnelLoading" x-cloak class="px-6 py-10 text-center text-sm text-zinc-500">
            <?= htmlspecialchars(__('personnel_loading'), ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div x-show="!personnelLoading && personnel.length === 0" x-cloak class="px-6 py-10 text-center text-sm text-zinc-500">
            <?= htmlspecialchars(__('personnel_empty'), ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div x-show="!personnelLoading && personnel.length > 0" class="overflow-x-auto">
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

        <div
            x-show="!personnelLoading && personnelPagination.total_pages > 1"
            x-cloak
            class="flex flex-col gap-3 border-t border-zinc-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-xs text-zinc-500" x-text="resolvePersonnelPaginationLabel()"></p>
            <div class="flex flex-wrap items-center gap-1">
                <button
                    type="button"
                    @click="goToPersonnelPage(personnelPagination.page - 1)"
                    :disabled="personnelPagination.page <= 1 || personnelLoading"
                    class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <?= htmlspecialchars(__('personnel_pagination_prev'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <template x-for="pageNumber in personnelPageNumbers()" :key="'personnel-page-' + pageNumber">
                    <button
                        type="button"
                        @click="goToPersonnelPage(pageNumber)"
                        :disabled="personnelLoading"
                        class="min-w-[2rem] rounded-lg border px-3 py-1.5 text-xs font-medium transition disabled:cursor-not-allowed disabled:opacity-50"
                        :class="pageNumber === personnelPagination.page ? 'border-zinc-900 bg-zinc-900 text-white' : 'border-zinc-200 text-zinc-700 hover:bg-zinc-50'"
                        x-text="pageNumber"
                    ></button>
                </template>
                <button
                    type="button"
                    @click="goToPersonnelPage(personnelPagination.page + 1)"
                    :disabled="personnelPagination.page >= personnelPagination.total_pages || personnelLoading"
                    class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <?= htmlspecialchars(__('personnel_pagination_next'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </div>
    </section>
</section>
