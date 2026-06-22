<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'licenses'" x-cloak class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('licenses_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('licenses_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                @click="openAssignLicenseModal()"
                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 shadow-soft transition hover:bg-zinc-50"
            >
                <?= htmlspecialchars(__('assign_license'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button
                type="button"
                @click="openLicenseModal()"
                class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
            >
                <span class="text-lg leading-none">+</span>
                <?= htmlspecialchars(__('add_license'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>

    <p x-show="licensesSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="licensesSuccessMessage"></p>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <div class="border-b border-zinc-200 px-6 py-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('licenses_filter_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                </div>
                <p x-show="licensesPagination.total > 0" x-cloak class="text-sm text-zinc-500">
                    <span x-text="licensesPagination.total"></span>
                    <?= htmlspecialchars(__('licenses_filter_result_count_suffix'), ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
        </div>

        <div class="border-b border-zinc-200 bg-zinc-50/70 px-6 py-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <span class="text-xs font-medium uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('licenses_filter_title'), ENT_QUOTES, 'UTF-8') ?></span>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="resetLicenseFilters()"
                        class="rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-600 transition hover:bg-zinc-100"
                    >
                        <?= htmlspecialchars(__('licenses_filter_reset'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button
                        type="button"
                        @click="applyLicenseFilters()"
                        :disabled="licensesLoading"
                        class="inline-flex items-center gap-2 rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg x-show="licensesLoading" x-cloak class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <?= htmlspecialchars(__('licenses_filter_apply'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <template x-for="field in licenseFilterFields" :key="field.name">
                    <label class="block">
                        <span class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-zinc-500" x-text="resolveLicenseFilterLabel(field)"></span>
                        <input
                            x-show="field.input === 'text' && field.type !== 'textarea'"
                            type="text"
                            x-model="licenseFilters[field.name]"
                            @keydown.enter.prevent="applyLicenseFilters()"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                        >
                        <textarea
                            x-show="field.type === 'textarea'"
                            x-model="licenseFilters[field.name]"
                            @keydown.enter.prevent="applyLicenseFilters()"
                            rows="2"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                        ></textarea>
                        <select
                            x-show="field.input === 'select'"
                            x-model="licenseFilters[field.name]"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                        >
                            <option value=""><?= htmlspecialchars(__('licenses_filter_all'), ENT_QUOTES, 'UTF-8') ?></option>
                            <template x-for="option in (field.options || [])" :key="`${field.name}-${option.value}`">
                                <option :value="option.value" x-text="resolveLicenseFilterOptionLabel(option)"></option>
                            </template>
                        </select>
                    </label>
                </template>
            </div>

            <p x-show="licensesError" x-cloak class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="licensesError"></p>
        </div>

        <p x-show="licensesLoading" x-cloak class="px-6 py-6 text-sm text-zinc-500">
            <?= htmlspecialchars(__('licenses_loading'), ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p
            x-show="!licensesLoading && !licensesError && licenses.length === 0 && !hasActiveLicenseFilters()"
            x-cloak
            class="px-6 py-8 text-sm text-zinc-500"
        >
            <?= htmlspecialchars(__('licenses_empty'), ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p
            x-show="!licensesLoading && !licensesError && licenses.length === 0 && hasActiveLicenseFilters()"
            x-cloak
            class="px-6 py-8 text-sm text-zinc-500"
        >
            <?= htmlspecialchars(__('licenses_filter_empty'), ENT_QUOTES, 'UTF-8') ?>
        </p>

        <div x-show="!licensesLoading && licenses.length > 0" x-cloak>
            <table class="min-w-full divide-y divide-zinc-200">
                <thead class="bg-zinc-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_license_vendor'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_license_name'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_license_expiration'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_license_seats'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    <template x-for="license in licenses" :key="license.id">
                        <tr class="hover:bg-zinc-50/80">
                            <td class="px-6 py-4 text-sm text-zinc-700" x-text="license.vendor"></td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-zinc-900" x-text="license.name"></p>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm text-zinc-600" x-text="formatLicenseExpiration(license.expiration_date)"></span>
                                    <span
                                        x-show="license.is_expiring_soon"
                                        x-cloak
                                        class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20"
                                    ><?= htmlspecialchars(__('license_expiring_soon'), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="min-w-[180px] space-y-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                            :class="license.remaining_seats === 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'"
                                            x-text="formatLicenseSeatUsage(license)"
                                        ></span>
                                        <span
                                            x-show="license.remaining_seats === 0"
                                            x-cloak
                                            class="text-xs font-medium text-rose-600"
                                        ><?= htmlspecialchars(__('license_seats_full'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-zinc-100">
                                        <div
                                            class="h-2 rounded-full transition-all duration-500"
                                            :class="licenseSeatBarColor(license)"
                                            :style="`width: ${licenseSeatUsagePercent(license)}%`"
                                        ></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <button
                                    type="button"
                                    @click="openAssignLicenseModal(license)"
                                    :disabled="license.remaining_seats === 0"
                                    class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <?= htmlspecialchars(__('assign_license'), ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <?php
            $listPagination = [
                'pagination' => 'licensesPagination',
                'loading' => 'licensesLoading',
                'goToPage' => 'goToLicensesPage',
                'pageNumbers' => 'licensesPageNumbers',
                'label' => 'resolveLicensesPaginationLabel',
            ];
            require __DIR__ . '/list_pagination.php';
            ?>
        </div>
    </div>
</section>
