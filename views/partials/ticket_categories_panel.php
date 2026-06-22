<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'settings' && settingsTab === 'ticket_categories'" x-cloak class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('ticket_categories_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('ticket_categories_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <button
            type="button"
            @click="openTicketCategoryModal()"
            class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
        >
            <span class="text-lg leading-none">+</span>
            <?= htmlspecialchars(__('add_ticket_category'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>

    <p x-show="ticketCategoriesLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('ticket_categories_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p x-show="ticketCategoriesError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="ticketCategoriesError"></p>
    <p x-show="ticketCategoriesSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="ticketCategoriesSuccessMessage"></p>

    <p
        x-show="!ticketCategoriesLoading && !ticketCategoriesError && ticketCategories.length === 0"
        x-cloak
        class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500"
    >
        <?= htmlspecialchars(__('ticket_categories_empty'), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div x-show="!ticketCategoriesLoading && ticketCategories.length > 0" x-cloak class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <table class="min-w-full divide-y divide-zinc-200">
            <thead class="bg-zinc-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_ticket_category_name'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_ticket_category_color'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_ticket_category_tickets'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200">
                <template x-for="category in ticketCategories" :key="category.id">
                    <tr class="hover:bg-zinc-50/80">
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-zinc-900" x-text="category.name"></p>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-2 text-sm text-zinc-600">
                                <span class="h-4 w-4 rounded-full ring-1 ring-inset ring-zinc-200" :style="`background-color: ${category.color_code}`"></span>
                                <span x-text="category.color_code"></span>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm tabular-nums text-zinc-700" x-text="category.ticket_count ?? 0"></td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    @click="openTicketCategoryModal(category)"
                                    class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                                >
                                    <?= htmlspecialchars(__('action_edit_ticket_category'), ENT_QUOTES, 'UTF-8') ?>
                                </button>
                                <button
                                    type="button"
                                    @click="deleteTicketCategory(category)"
                                    class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-50"
                                >
                                    <?= htmlspecialchars(__('action_delete_ticket_category'), ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</section>
