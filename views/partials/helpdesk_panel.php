<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'helpdesk'" x-cloak class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('helpdesk_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('helpdesk_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                @click="openTicketModal()"
                class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
            >
                <span class="text-lg leading-none">+</span>
                <?= htmlspecialchars(__('add_ticket'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>

    <div class="inline-flex rounded-2xl border border-zinc-200 bg-white p-1 shadow-soft">
        <template x-for="filter in ticketStatusFilters" :key="filter.value">
            <button
                type="button"
                @click="setTicketStatusFilter(filter.value)"
                class="rounded-xl px-4 py-2 text-sm font-medium transition"
                :class="ticketStatusFilter === filter.value ? 'bg-zinc-900 text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-50'"
                x-text="filter.label"
            ></button>
        </template>
    </div>

    <p x-show="ticketsLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('helpdesk_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p x-show="ticketsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="ticketsError"></p>
    <p x-show="ticketsSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="ticketsSuccessMessage"></p>

    <p
        x-show="!ticketsLoading && !ticketsError && tickets.length === 0"
        x-cloak
        class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500"
    >
        <?= htmlspecialchars(__('helpdesk_empty'), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div x-show="!ticketsLoading && tickets.length > 0" x-cloak class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <table class="min-w-full divide-y divide-zinc-200">
            <thead class="bg-zinc-50">
                <tr>
                    <th class="whitespace-nowrap px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        <button type="button" @click="setTicketsSort('ticket_number')" class="inline-flex items-center gap-1.5 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400/50">
                            <span><?= htmlspecialchars(__('col_ticket_number'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="inline-flex shrink-0 text-[10px] leading-none" :class="sortIndicatorClasses(ticketsSort, 'ticket_number')" x-text="sortIndicatorSymbol(ticketsSort, 'ticket_number')" aria-hidden="true"></span>
                        </button>
                    </th>
                    <th class="whitespace-nowrap px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        <button type="button" @click="setTicketsSort('subject')" class="inline-flex items-center gap-1.5 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400/50">
                            <span><?= htmlspecialchars(__('col_ticket_subject'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="inline-flex shrink-0 text-[10px] leading-none" :class="sortIndicatorClasses(ticketsSort, 'subject')" x-text="sortIndicatorSymbol(ticketsSort, 'subject')" aria-hidden="true"></span>
                        </button>
                    </th>
                    <th class="whitespace-nowrap px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_ticket_category'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="whitespace-nowrap px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_ticket_requester'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="whitespace-nowrap px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_ticket_asset'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="whitespace-nowrap px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        <button type="button" @click="setTicketsSort('priority')" class="inline-flex items-center gap-1.5 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400/50">
                            <span><?= htmlspecialchars(__('col_ticket_priority'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="inline-flex shrink-0 text-[10px] leading-none" :class="sortIndicatorClasses(ticketsSort, 'priority')" x-text="sortIndicatorSymbol(ticketsSort, 'priority')" aria-hidden="true"></span>
                        </button>
                    </th>
                    <th class="whitespace-nowrap px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        <button type="button" @click="setTicketsSort('status')" class="inline-flex items-center gap-1.5 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400/50">
                            <span><?= htmlspecialchars(__('col_ticket_status'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="inline-flex shrink-0 text-[10px] leading-none" :class="sortIndicatorClasses(ticketsSort, 'status')" x-text="sortIndicatorSymbol(ticketsSort, 'status')" aria-hidden="true"></span>
                        </button>
                    </th>
                    <th class="whitespace-nowrap px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        <button type="button" @click="setTicketsSort('created_at')" class="inline-flex items-center gap-1.5 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400/50">
                            <span><?= htmlspecialchars(__('col_ticket_created'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="inline-flex shrink-0 text-[10px] leading-none" :class="sortIndicatorClasses(ticketsSort, 'created_at')" x-text="sortIndicatorSymbol(ticketsSort, 'created_at')" aria-hidden="true"></span>
                        </button>
                    </th>
                    <th class="whitespace-nowrap px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200">
                <template x-for="ticket in tickets" :key="ticket.id">
                    <tr class="hover:bg-zinc-50/80">
                        <td class="px-6 py-4 text-sm font-medium tabular-nums text-zinc-900" x-text="ticket.ticket_number"></td>
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-zinc-900" x-text="ticket.subject"></p>
                            <p class="mt-1 line-clamp-1 text-xs text-zinc-500" x-text="ticket.description"></p>
                        </td>
                        <td class="px-6 py-4">
                            <span
                                x-show="ticket.category_name"
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium text-white"
                                :style="ticket.category_color ? `background-color: ${ticket.category_color}` : ''"
                                x-text="ticket.category_name"
                            ></span>
                            <span x-show="!ticket.category_name" class="text-sm text-zinc-400">—</span>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-sm text-zinc-700" x-text="ticket.personnel_name"></p>
                            <p class="text-xs text-zinc-500" x-text="ticket.personnel_department || ''"></p>
                        </td>
                        <td class="px-6 py-4 text-sm text-zinc-600" x-text="ticket.asset_label || '—'"></td>
                        <td class="px-6 py-4">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="ticketPriorityClass(ticket.priority)" x-text="resolveTicketPriority(ticket.priority)"></span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="ticketStatusClass(ticket.status)" x-text="resolveTicketStatus(ticket.status)"></span>
                        </td>
                        <td class="px-6 py-4 text-sm text-zinc-600" x-text="formatTicketDate(ticket.created_at)"></td>
                        <td class="px-6 py-4">
                            <button
                                type="button"
                                @click="openTicketDetail(ticket)"
                                class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                            ><?= htmlspecialchars(__('action_view_ticket'), ENT_QUOTES, 'UTF-8') ?></button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <?php
        $listPagination = [
            'pagination' => 'ticketsPagination',
            'loading' => 'ticketsLoading',
            'goToPage' => 'goToTicketsPage',
            'pageNumbers' => 'ticketsPageNumbers',
            'label' => 'resolveTicketsPaginationLabel',
        ];
        require __DIR__ . '/list_pagination.php';
        ?>
    </div>
</section>
