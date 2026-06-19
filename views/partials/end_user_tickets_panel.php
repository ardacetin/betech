<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'my_tickets'" x-cloak class="space-y-6">
    <div x-show="portalTicketsLoading" class="overflow-hidden rounded-xl border border-gray-100 bg-white p-12 text-center shadow-sm">
        <div class="mx-auto flex h-10 w-10 items-center justify-center">
            <svg class="h-5 w-5 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </div>
        <p class="mt-4 text-sm text-gray-500"><?= htmlspecialchars(__('portal_tickets_loading'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <p x-show="portalTicketsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm text-rose-700" x-text="portalTicketsError"></p>

    <div
        x-show="!portalTicketsLoading && !portalTicketsError && portalTickets.length === 0"
        x-cloak
        class="rounded-xl border border-gray-100 bg-white p-12 text-center shadow-sm"
    >
        <svg class="mx-auto h-16 w-16 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
        </svg>
        <p class="mt-6 text-base font-semibold tracking-tight text-gray-800"><?= htmlspecialchars(__('portal_tickets_empty'), ENT_QUOTES, 'UTF-8') ?></p>
        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-gray-500"><?= htmlspecialchars(__('portal_tickets_empty_hint'), ENT_QUOTES, 'UTF-8') ?></p>
        <button
            type="button"
            @click="openPortalTicketModal()"
            class="mt-8 inline-flex items-center justify-center rounded-md border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm transition hover:border-gray-300 hover:bg-gray-50"
        >
            <?= htmlspecialchars(__('portal_create_ticket'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>

    <div x-show="!portalTicketsLoading && portalTickets.length > 0" x-cloak class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400"><?= htmlspecialchars(__('portal_tab_tickets'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-1 text-sm text-gray-500"><?= htmlspecialchars(__('portal_my_tickets_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="divide-y divide-gray-100">
            <template x-for="ticket in portalTickets" :key="ticket.id">
                <button type="button" @click="openPortalTicketDetail(ticket)" class="block w-full px-6 py-5 text-left transition hover:bg-gray-50/60">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <p class="text-xs font-medium tabular-nums tracking-wide text-gray-400" x-text="ticket.ticket_number"></p>
                        <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="portalTicketStatusClass(ticket.status)" x-text="portalTicketStatusLabel(ticket.status)"></span>
                    </div>
                    <h2 class="mt-2 text-base font-semibold tracking-tight text-gray-900" x-text="ticket.subject"></h2>
                    <p class="mt-1 line-clamp-2 text-sm leading-relaxed text-gray-500" x-text="ticket.description"></p>
                    <p class="mt-3 text-xs text-gray-400" x-text="formatPortalDate(ticket.created_at)"></p>
                </button>
            </template>
        </div>
    </div>
</section>
