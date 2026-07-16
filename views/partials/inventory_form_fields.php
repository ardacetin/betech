<?php

declare(strict_types=1);

/**
 * Shared inventory form field partial.
 * Expects Alpine scope with: form, inventorySchema, extensionColumns(), nativeFieldColumns()
 */
?>
<div class="grid gap-8 lg:grid-cols-2">
    <div class="space-y-6">
        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('inventory_section_identity'), ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <template x-if="mode === 'edit'">
                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('col_asset_tag'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input x-model="form.asset_tag" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                </template>
                <label class="block sm:col-span-2">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_name'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input x-model="form.name" type="text" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('col_model'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input x-model="form.model" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('col_brand'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input x-model="form.brand" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_serial_number'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input x-model="form.serial_number" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('col_category'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input x-model="form.type" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_status'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select x-model="form.status" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                        <option value="ready"><?= htmlspecialchars(__('status_ready'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="deployed"><?= htmlspecialchars(__('status_deployed'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="storage"><?= htmlspecialchars(__('status_storage'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="broken"><?= htmlspecialchars(__('status_broken'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </label>
            </div>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('inventory_section_location_network'), ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('col_location'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input x-model="form.location" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('col_building'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input x-model="form.building" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_mac_address_1'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input x-model="form.mac_address_1" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 font-mono text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_mac_address_2'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input x-model="form.mac_address_2" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 font-mono text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_warranty_expires_at'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input x-model="form.warranty_expires_at" type="date" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                </label>
            </div>
        </section>

        <section x-show="extensionColumns().length > 0" x-cloak class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('technical_specifications'), ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <template x-for="field in extensionColumns()" :key="field.column">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700" x-text="field.label"></span>
                        <input
                            x-model="form[field.column]"
                            type="text"
                            class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                        >
                    </label>
                </template>
            </div>
        </section>
    </div>

    <div class="space-y-6">
        <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('label_assign_user'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('assign_user_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            <template x-if="mode === 'edit'">
                <label class="mt-4 block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('col_assigned_user'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input x-model="form.assigned_to" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4" placeholder="<?= htmlspecialchars(__('assign_user_hint'), ENT_QUOTES, 'UTF-8') ?>">
                </label>
            </template>
            <?php require __DIR__ . '/user_picker.php'; ?>
        </section>

        <section class="rounded-2xl border border-zinc-200 bg-zinc-50 p-6">
            <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('inventory_form_actions_title'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('inventory_form_actions_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
            <div x-show="errorMessage" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="errorMessage"></div>
            <div class="mt-6 flex flex-wrap items-center gap-3">
                <a href="<?= htmlspecialchars($cancelUrl ?? '/', ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl px-4 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></a>
                <button
                    type="submit"
                    :disabled="isSubmitting || !canSubmit()"
                    class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span x-show="isSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span x-show="!isSubmitting" x-text="mode === 'edit' ? window.__i18n.save_changes : window.__i18n.create_asset"></span>
                </button>
            </div>
        </section>
    </div>
</div>
