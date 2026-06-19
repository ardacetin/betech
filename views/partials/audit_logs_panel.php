<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'audit_logs'" x-cloak class="space-y-4">
    <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-soft">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('audit_filter_user'), ENT_QUOTES, 'UTF-8') ?></label>
                <select
                    x-model="auditLogFilters.user_id"
                    @change="auditLogPage = 1; fetchAuditLogs()"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-400 focus:outline-none"
                >
                    <option value=""><?= htmlspecialchars(__('audit_filter_all_users'), ENT_QUOTES, 'UTF-8') ?></option>
                    <template x-for="user in auditLogFilterUsers" :key="user.id">
                        <option :value="String(user.id)" x-text="user.name + (user.email ? ' (' + user.email + ')' : '')"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('audit_filter_action'), ENT_QUOTES, 'UTF-8') ?></label>
                <select
                    x-model="auditLogFilters.action_type"
                    @change="auditLogPage = 1; fetchAuditLogs()"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-400 focus:outline-none"
                >
                    <option value=""><?= htmlspecialchars(__('audit_filter_all_actions'), ENT_QUOTES, 'UTF-8') ?></option>
                    <template x-for="action in auditLogFilterActions" :key="action">
                        <option :value="action" x-text="auditActionLabel(action)"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('audit_filter_entity'), ENT_QUOTES, 'UTF-8') ?></label>
                <select
                    x-model="auditLogFilters.entity_type"
                    @change="auditLogPage = 1; fetchAuditLogs()"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-400 focus:outline-none"
                >
                    <option value=""><?= htmlspecialchars(__('audit_filter_all_entities'), ENT_QUOTES, 'UTF-8') ?></option>
                    <template x-for="entity in auditLogFilterEntities" :key="entity">
                        <option :value="entity" x-text="auditEntityLabel(entity)"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('audit_filter_date_from'), ENT_QUOTES, 'UTF-8') ?></label>
                <input
                    type="date"
                    x-model="auditLogFilters.date_from"
                    @change="auditLogPage = 1; fetchAuditLogs()"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-400 focus:outline-none"
                >
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('audit_filter_date_to'), ENT_QUOTES, 'UTF-8') ?></label>
                <input
                    type="date"
                    x-model="auditLogFilters.date_to"
                    @change="auditLogPage = 1; fetchAuditLogs()"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-400 focus:outline-none"
                >
            </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <button
                type="button"
                @click="resetAuditLogFilters()"
                class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-600 transition hover:bg-zinc-50"
            ><?= htmlspecialchars(__('audit_filter_reset'), ENT_QUOTES, 'UTF-8') ?></button>
            <button
                type="button"
                @click="fetchAuditLogs()"
                class="rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-zinc-800"
            ><?= htmlspecialchars(__('audit_refresh'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>
    </div>

    <p x-show="auditLogsLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('audit_logs_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p x-show="auditLogsError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="auditLogsError"></p>
    <p
        x-show="!auditLogsLoading && !auditLogsError && auditLogs.length === 0"
        x-cloak
        class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500"
    >
        <?= htmlspecialchars(__('audit_logs_empty'), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div x-show="!auditLogsLoading && auditLogs.length > 0" x-cloak class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm">
                <thead class="bg-zinc-50">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('audit_col_timestamp'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('audit_col_user'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('audit_col_action'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('audit_col_entity'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('audit_col_summary'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('audit_col_ip'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <template x-for="log in auditLogs" :key="log.id">
                        <tr class="align-top hover:bg-zinc-50/80">
                            <td class="whitespace-nowrap px-4 py-2.5 tabular-nums text-zinc-600" x-text="formatAuditTimestamp(log.created_at)"></td>
                            <td class="px-4 py-2.5 text-zinc-900">
                                <div class="font-medium" x-text="log.user_name || '—'"></div>
                                <div class="text-xs text-zinc-500" x-text="log.user_email || ''"></div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="auditActionBadgeClass(log.action_type)" x-text="auditActionLabel(log.action_type)"></span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-zinc-700">
                                <span x-text="auditEntityLabel(log.entity_type)"></span>
                                <span class="text-zinc-400" x-show="log.entity_id" x-text="' #' + log.entity_id"></span>
                            </td>
                            <td class="max-w-xl px-4 py-2.5 text-zinc-800" x-text="log.summary"></td>
                            <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-zinc-500" x-text="log.ip_address || '—'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 px-4 py-3">
            <p class="text-xs text-zinc-500" x-text="auditLogPaginationLabel()"></p>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="changeAuditLogPage(auditLogPagination.page - 1)"
                    :disabled="auditLogPagination.page <= 1"
                    class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-600 transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-40"
                ><?= htmlspecialchars(__('audit_pagination_prev'), ENT_QUOTES, 'UTF-8') ?></button>
                <button
                    type="button"
                    @click="changeAuditLogPage(auditLogPagination.page + 1)"
                    :disabled="auditLogPagination.page >= auditLogPagination.total_pages"
                    class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-600 transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-40"
                ><?= htmlspecialchars(__('audit_pagination_next'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </div>
</section>
