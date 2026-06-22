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
            <div class="inline-flex rounded-xl border border-zinc-200 bg-white p-1 shadow-soft">
                <button
                    type="button"
                    @click="ticketLayout = 'table'"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                    :class="ticketLayout === 'table' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-50'"
                ><?= htmlspecialchars(__('helpdesk_view_table'), ENT_QUOTES, 'UTF-8') ?></button>
                <button
                    type="button"
                    @click="ticketLayout = 'board'"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                    :class="ticketLayout === 'board' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-50'"
                ><?= htmlspecialchars(__('helpdesk_view_board'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
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

    <div class="flex flex-wrap gap-2">
        <template x-for="filter in ticketStatusFilters" :key="filter.value">
            <button
                type="button"
                @click="setTicketStatusFilter(filter.value)"
                class="rounded-full px-3 py-1.5 text-xs font-medium ring-1 ring-inset transition"
                :class="ticketStatusFilter === filter.value ? 'bg-zinc-900 text-white ring-zinc-900' : 'bg-white text-zinc-600 ring-zinc-200 hover:bg-zinc-50'"
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
        x-show="!ticketsLoading && !ticketsError && filteredTickets.length === 0"
        x-cloak
        class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500"
    >
        <?= htmlspecialchars(__('helpdesk_empty'), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div x-show="!ticketsLoading && filteredTickets.length > 0 && ticketLayout === 'table'" x-cloak class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <table class="min-w-full divide-y divide-zinc-200">
            <thead class="bg-zinc-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_ticket_number'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_ticket_subject'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_ticket_category'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_ticket_requester'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_ticket_asset'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_ticket_priority'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_ticket_status'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_ticket_created'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200">
                <template x-for="ticket in filteredTickets" :key="ticket.id">
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
    </div>

    <div x-show="!ticketsLoading && filteredTickets.length > 0 && ticketLayout === 'board'" x-cloak class="grid gap-4 xl:grid-cols-4">
        <template x-for="column in ticketBoardColumns" :key="column.status">
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-4">
                <div class="mb-4 flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-zinc-900" x-text="column.label"></h3>
                    <span class="inline-flex rounded-full bg-white px-2 py-0.5 text-xs font-medium text-zinc-600 ring-1 ring-inset ring-zinc-200" x-text="ticketsForStatus(column.status).length"></span>
                </div>
                <div class="space-y-3">
                    <template x-for="ticket in ticketsForStatus(column.status)" :key="ticket.id">
                        <button
                            type="button"
                            @click="openTicketDetail(ticket)"
                            class="w-full rounded-xl border border-zinc-200 bg-white p-4 text-left shadow-soft transition hover:border-zinc-300"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-xs font-medium tabular-nums text-zinc-500" x-text="ticket.ticket_number"></p>
                                <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium ring-1 ring-inset" :class="ticketPriorityClass(ticket.priority)" x-text="resolveTicketPriority(ticket.priority)"></span>
                            </div>
                            <p class="mt-2 text-sm font-medium text-zinc-900" x-text="ticket.subject"></p>
                            <p class="mt-1 line-clamp-2 text-xs text-zinc-500" x-text="ticket.description"></p>
                            <div class="mt-3 flex items-center justify-between gap-2 text-xs text-zinc-500">
                                <span x-text="ticket.personnel_name"></span>
                                <span x-text="formatTicketDate(ticket.created_at)"></span>
                            </div>
                        </button>
                    </template>
                    <p x-show="ticketsForStatus(column.status).length === 0" class="rounded-xl border border-dashed border-zinc-200 bg-white px-3 py-6 text-center text-xs text-zinc-400">
                        <?= htmlspecialchars(__('helpdesk_board_empty_column'), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            </div>
        </template>
    </div>
</section>
