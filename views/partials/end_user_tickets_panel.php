<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'my_tickets'" x-cloak class="space-y-6">
    <p x-show="portalTicketsLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('portal_tickets_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <p x-show="portalTicketsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="portalTicketsError"></p>

    <section
        x-show="!portalTicketsLoading && !portalTicketsError && portalTickets.length === 0"
        x-cloak
        class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft"
    >
        <div class="px-6 py-12 text-center">
            <svg class="mx-auto h-16 w-16 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
            </svg>
            <p class="mt-6 text-base font-medium text-zinc-700"><?= htmlspecialchars(__('portal_tickets_empty'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mx-auto mt-2 max-w-md text-sm text-zinc-500"><?= htmlspecialchars(__('portal_tickets_empty_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            <button
                type="button"
                @click="openPortalTicketModal()"
                class="mt-8 inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
            >
                <span class="text-lg leading-none">+</span>
                <?= htmlspecialchars(__('portal_create_ticket'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </section>

    <section x-show="!portalTicketsLoading && portalTickets.length > 0" x-cloak class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <div class="border-b border-zinc-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('portal_my_tickets_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('portal_my_tickets_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="space-y-3 p-4">
            <template x-for="ticket in portalTickets" :key="ticket.id">
                <button
                    type="button"
                    @click="openPortalTicketDetail(ticket)"
                    class="w-full rounded-xl border border-zinc-200 bg-white p-4 text-left shadow-soft transition hover:border-zinc-300"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-medium tabular-nums text-zinc-500" x-text="ticket.ticket_number"></p>
                        <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="portalTicketStatusClass(ticket.status)" x-text="portalTicketStatusLabel(ticket.status)"></span>
                    </div>
                    <p class="mt-2 text-sm font-medium text-zinc-900" x-text="ticket.subject"></p>
                    <p class="mt-1 line-clamp-2 text-xs text-zinc-500" x-text="ticket.description"></p>
                    <p class="mt-3 text-xs text-zinc-500" x-text="formatPortalDate(ticket.created_at)"></p>
                </button>
            </template>
        </div>
        <?php
        $listPagination = [
            'pagination' => 'portalTicketsPagination',
            'loading' => 'portalTicketsLoading',
            'goToPage' => 'goToPortalTicketsPage',
            'pageNumbers' => 'portalTicketsPageNumbers',
            'label' => 'resolvePortalTicketsPaginationLabel',
        ];
        require __DIR__ . '/list_pagination.php';
        ?>
    </section>
</section>
