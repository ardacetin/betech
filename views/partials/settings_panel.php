<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'settings'" x-cloak class="space-y-6">
    <div>
        <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('settings_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('settings_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <form @submit.prevent="saveSettings" class="space-y-6">
        <article class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('settings_auth_title'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('settings_auth_hint'), ENT_QUOTES, 'UTF-8') ?></p>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <template x-for="driver in authDrivers" :key="driver.id">
                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-xl border px-4 py-3 transition"
                        :class="settingsForm.active_auth_driver === driver.id ? 'border-zinc-900 bg-zinc-50 ring-1 ring-zinc-900/10' : 'border-zinc-200 hover:border-zinc-300'"
                    >
                        <input
                            type="radio"
                            class="mt-1"
                            name="active_auth_driver"
                            :value="driver.id"
                            x-model="settingsForm.active_auth_driver"
                        >
                        <span>
                            <span class="block text-sm font-medium text-zinc-900" x-text="driver.label"></span>
                            <span class="mt-1 block text-xs text-zinc-500" x-text="driver.description"></span>
                        </span>
                    </label>
                </template>
            </div>
        </article>

        <article class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('settings_zimmet_title'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('settings_zimmet_hint'), ENT_QUOTES, 'UTF-8') ?></p>

            <textarea
                x-model="settingsForm.zimmet_template"
                rows="12"
                class="mt-5 w-full rounded-xl border border-zinc-300 px-4 py-3 font-mono text-sm leading-relaxed text-zinc-800 outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
            ></textarea>

            <p class="mt-3 text-xs text-zinc-400"><?= htmlspecialchars(__('settings_zimmet_placeholders'), ENT_QUOTES, 'UTF-8') ?></p>
        </article>

        <article class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('settings_custom_fields_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('settings_custom_fields_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button
                    type="button"
                    @click="addCustomField()"
                    class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                >
                    <span class="text-base leading-none">+</span>
                    <?= htmlspecialchars(__('settings_add_custom_field'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>

            <p
                x-show="settingsForm.custom_fields.length === 0"
                x-cloak
                class="mt-5 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-6 text-sm text-zinc-500"
            >
                <?= htmlspecialchars(__('settings_no_custom_fields'), ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div x-show="settingsForm.custom_fields.length > 0" x-cloak class="mt-5 space-y-3">
                <template x-for="(field, index) in settingsForm.custom_fields" :key="index">
                    <div class="grid gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 sm:grid-cols-12">
                        <label class="block sm:col-span-3">
                            <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_field_name'), ENT_QUOTES, 'UTF-8') ?></span>
                            <input
                                type="text"
                                x-model="field.name"
                                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400"
                                placeholder="location"
                            >
                        </label>
                        <label class="block sm:col-span-4">
                            <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_field_label'), ENT_QUOTES, 'UTF-8') ?></span>
                            <input
                                type="text"
                                x-model="field.label"
                                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400"
                                placeholder="<?= htmlspecialchars(__('settings_field_label_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </label>
                        <label class="block sm:col-span-3">
                            <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_field_type'), ENT_QUOTES, 'UTF-8') ?></span>
                            <select
                                x-model="field.type"
                                class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400"
                            >
                                <option value="text"><?= htmlspecialchars(__('settings_field_type_text'), ENT_QUOTES, 'UTF-8') ?></option>
                                <option value="number"><?= htmlspecialchars(__('settings_field_type_number'), ENT_QUOTES, 'UTF-8') ?></option>
                                <option value="textarea"><?= htmlspecialchars(__('settings_field_type_textarea'), ENT_QUOTES, 'UTF-8') ?></option>
                            </select>
                        </label>
                        <div class="flex items-end justify-end sm:col-span-2">
                            <button
                                type="button"
                                @click="removeCustomField(index)"
                                class="rounded-lg px-3 py-2 text-xs font-medium text-rose-600 transition hover:bg-rose-50"
                            >
                                <?= htmlspecialchars(__('settings_remove_custom_field'), ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </article>

        <div x-show="settingsErrorMessage" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="settingsErrorMessage"></div>
        <div x-show="settingsSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="settingsSuccessMessage"></div>

        <div class="flex items-center justify-end gap-3">
            <button
                type="submit"
                :disabled="isSavingSettings"
                class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span x-show="isSavingSettings"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                <span x-show="!isSavingSettings"><?= htmlspecialchars(__('settings_save'), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
        </div>
    </form>
</section>
