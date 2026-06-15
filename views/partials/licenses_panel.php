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

    <p x-show="licensesLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('licenses_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p x-show="licensesError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="licensesError"></p>
    <p x-show="licensesSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="licensesSuccessMessage"></p>

    <p
        x-show="!licensesLoading && !licensesError && licenses.length === 0"
        x-cloak
        class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500"
    >
        <?= htmlspecialchars(__('licenses_empty'), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div x-show="!licensesLoading && licenses.length > 0" x-cloak class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
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
                        <td class="px-6 py-4 text-sm text-zinc-600" x-text="formatLicenseExpiration(license.expiration_date)"></td>
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
    </div>
</section>
