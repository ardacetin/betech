<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'settings' && settingsTab === 'automation'" x-cloak class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('automation_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('automation_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                @click="runAutomationRulesNow()"
                :disabled="automationRunning"
                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 shadow-soft transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span x-show="!automationRunning"><?= htmlspecialchars(__('automation_run_now'), ENT_QUOTES, 'UTF-8') ?></span>
                <span x-show="automationRunning" x-cloak><?= htmlspecialchars(__('automation_running'), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
            <button
                type="button"
                @click="openAutomationRuleModal()"
                class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
            >
                <span class="text-lg leading-none">+</span>
                <?= htmlspecialchars(__('automation_add_rule'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>

    <p x-show="automationLoading" x-cloak class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-sm text-zinc-500">
        <?= htmlspecialchars(__('automation_loading'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p x-show="automationError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="automationError"></p>
    <p x-show="automationSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="automationSuccessMessage"></p>

    <p
        x-show="!automationLoading && !automationError && automationRules.length === 0"
        x-cloak
        class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-8 text-sm text-zinc-500"
    >
        <?= htmlspecialchars(__('automation_empty'), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div x-show="!automationLoading && automationRules.length > 0" x-cloak class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-soft">
        <table class="min-w-full divide-y divide-zinc-200">
            <thead class="bg-zinc-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('automation_col_name'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('automation_col_type'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('automation_col_config'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('automation_col_recipients'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('automation_col_enabled'), ENT_QUOTES, 'UTF-8') ?></th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200">
                <template x-for="rule in automationRules" :key="rule.id">
                    <tr class="hover:bg-zinc-50/80">
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-zinc-900" x-text="rule.name"></p>
                            <p class="mt-1 text-xs text-zinc-400" x-show="rule.last_run_at" x-text="(window.__i18n.automation_last_run || '') + ': ' + rule.last_run_at"></p>
                        </td>
                        <td class="px-6 py-4 text-sm text-zinc-700" x-text="automationRuleTypeLabel(rule.rule_type)"></td>
                        <td class="px-6 py-4 text-sm text-zinc-700" x-text="automationRuleConfigSummary(rule)"></td>
                        <td class="px-6 py-4 text-sm text-zinc-700" x-text="automationRecipientLabel(rule)"></td>
                        <td class="px-6 py-4">
                            <button
                                type="button"
                                @click="toggleAutomationRule(rule)"
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium transition"
                                :class="rule.is_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-zinc-100 text-zinc-600'"
                                x-text="rule.is_enabled ? window.__i18n.automation_enabled : window.__i18n.automation_disabled"
                            ></button>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    @click="openAutomationRuleModal(rule)"
                                    class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                                >
                                    <?= htmlspecialchars(__('automation_edit_rule'), ENT_QUOTES, 'UTF-8') ?>
                                </button>
                                <button
                                    type="button"
                                    @click="deleteAutomationRule(rule)"
                                    class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-50"
                                >
                                    <?= htmlspecialchars(__('automation_delete_rule'), ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div
        x-show="isAutomationRuleModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        @keydown.escape.window="closeAutomationRuleModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40" @click="closeAutomationRuleModal()"></div>
        <div class="relative w-full max-w-lg rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h3 class="text-base font-semibold text-zinc-900" x-text="automationRuleForm.id ? window.__i18n.automation_edit_rule : window.__i18n.automation_add_rule"></h3>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('automation_modal_hint'), ENT_QUOTES, 'UTF-8') ?></p>

            <form class="mt-5 space-y-4" @submit.prevent="saveAutomationRule()">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('automation_col_name'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input x-model="automationRuleForm.name" type="text" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('automation_col_type'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select
                        x-model="automationRuleForm.rule_type"
                        @change="onAutomationRuleTypeChange()"
                        :disabled="!!automationRuleForm.id"
                        class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4 disabled:bg-zinc-50"
                    >
                        <option value="license_expiring"><?= htmlspecialchars(__('automation_type_license_expiring'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="warranty_expiring"><?= htmlspecialchars(__('automation_type_warranty_expiring'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="consumable_low_stock"><?= htmlspecialchars(__('automation_type_consumable_low_stock'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="ticket_priority"><?= htmlspecialchars(__('automation_type_ticket_priority'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </label>

                <label class="block" x-show="automationRuleForm.rule_type === 'license_expiring' || automationRuleForm.rule_type === 'warranty_expiring'">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('automation_config_days'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input x-model.number="automationRuleForm.config.days" type="number" min="1" max="365" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                </label>

                <label class="block" x-show="automationRuleForm.rule_type === 'ticket_priority'">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('automation_config_priority'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select x-model="automationRuleForm.config.priority" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                        <option value="critical"><?= htmlspecialchars(__('ticket_priority_critical'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="high"><?= htmlspecialchars(__('ticket_priority_high'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="medium"><?= htmlspecialchars(__('ticket_priority_medium'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="low"><?= htmlspecialchars(__('ticket_priority_low'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('automation_col_recipients'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select x-model="automationRuleForm.recipient_mode" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                        <option value="admins"><?= htmlspecialchars(__('automation_recipient_admins'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="support"><?= htmlspecialchars(__('automation_recipient_support'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="custom"><?= htmlspecialchars(__('automation_recipient_custom'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </label>

                <label class="block" x-show="automationRuleForm.recipient_mode === 'custom'">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('automation_custom_recipients'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input
                        x-model="automationRuleForm.custom_recipients"
                        type="text"
                        class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                        placeholder="ops@example.com, it@example.com"
                    >
                </label>

                <label class="flex items-center gap-3 rounded-xl border border-zinc-200 px-4 py-3">
                    <input type="checkbox" x-model="automationRuleForm.is_enabled" class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900/20">
                    <span class="text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('automation_enabled'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>

                <p x-show="automationModalError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="automationModalError"></p>

                <div class="flex flex-wrap justify-end gap-2 pt-2">
                    <button type="button" @click="closeAutomationRuleModal()" class="rounded-xl px-4 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100">
                        <?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button
                        type="submit"
                        :disabled="automationSaving"
                        class="rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span x-show="!automationSaving"><?= htmlspecialchars(__('save_changes'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="automationSaving" x-cloak><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
