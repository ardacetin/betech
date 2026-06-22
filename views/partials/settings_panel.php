<?php

declare(strict_types=1);
?>
<section x-show="activeView === 'settings'" x-cloak class="space-y-6">
    <div>
        <h2 class="text-lg font-semibold tracking-tight text-zinc-900"><?= htmlspecialchars(__('settings_page_title'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('settings_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <nav class="flex flex-wrap gap-2 border-b border-zinc-200 pb-1">
        <button
            type="button"
            @click="settingsTab = 'general'"
            class="rounded-lg px-3 py-2 text-sm font-medium transition"
            :class="settingsTab === 'general' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100'"
        >
            <?= htmlspecialchars(__('settings_tab_general'), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <button
            type="button"
            @click="settingsTab = 'categories'; fetchCategories()"
            class="rounded-lg px-3 py-2 text-sm font-medium transition"
            :class="settingsTab === 'categories' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100'"
        >
            <?= htmlspecialchars(__('settings_tab_categories'), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <button
            type="button"
            @click="settingsTab = 'locations'; fetchLocations()"
            class="rounded-lg px-3 py-2 text-sm font-medium transition"
            :class="settingsTab === 'locations' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100'"
        >
            <?= htmlspecialchars(__('settings_tab_locations'), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <button
            type="button"
            @click="settingsTab = 'ticket_categories'; fetchTicketCategories()"
            class="rounded-lg px-3 py-2 text-sm font-medium transition"
            :class="settingsTab === 'ticket_categories' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100'"
        >
            <?= htmlspecialchars(__('settings_tab_ticket_categories'), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <?php if ($canAccessSettings): ?>
        <button
            type="button"
            @click="settingsTab = 'smtp'"
            class="rounded-lg px-3 py-2 text-sm font-medium transition"
            :class="settingsTab === 'smtp' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100'"
        >
            <?= htmlspecialchars(__('settings_tab_smtp'), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <button
            type="button"
            @click="settingsTab = 'backup'; fetchBackups()"
            class="rounded-lg px-3 py-2 text-sm font-medium transition"
            :class="settingsTab === 'backup' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100'"
        >
            <?= htmlspecialchars(__('settings_tab_backup'), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <?php endif; ?>
    </nav>

    <form x-show="settingsTab === 'general'" @submit.prevent="saveSettings" class="space-y-6">
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

        <article x-show="settingsForm.active_auth_driver === 'ldap'" x-cloak class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('settings_ldap_title'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('settings_ldap_hint'), ENT_QUOTES, 'UTF-8') ?></p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="block sm:col-span-1">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_ldap_host'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="settingsForm.ldap_config.host" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" placeholder="ldap.sirket.local">
                </label>
                <label class="block sm:col-span-1">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_ldap_port'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="number" min="1" max="65535" x-model="settingsForm.ldap_config.port" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" placeholder="389">
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_ldap_base_dn'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="settingsForm.ldap_config.base_dn" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" placeholder="dc=sirket,dc=local">
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_ldap_account_suffix'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="settingsForm.ldap_config.account_suffix" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" placeholder="@domain.edu.tr">
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_ldap_bind_dn'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="settingsForm.ldap_config.bind_dn" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" placeholder="cn=admin,dc=sirket,dc=local">
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_ldap_bind_password'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="password" x-model="settingsForm.ldap_config.bind_password" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" :placeholder="settingsForm.ldap_config.bind_password_configured ? '<?= htmlspecialchars(__('settings_secret_configured'), ENT_QUOTES, 'UTF-8') ?>' : ''">
                </label>
                <label class="flex items-center gap-2 sm:col-span-2">
                    <input type="checkbox" x-model="settingsForm.ldap_config.use_tls" class="rounded border-zinc-300">
                    <span class="text-sm text-zinc-700"><?= htmlspecialchars(__('settings_ldap_use_tls'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
            </div>
        </article>

        <article x-show="settingsForm.active_auth_driver === 'google'" x-cloak class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('settings_google_title'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('settings_google_hint'), ENT_QUOTES, 'UTF-8') ?></p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="block sm:col-span-1">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_google_domain'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="settingsForm.google_config.domain" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" placeholder="sirket.com">
                </label>
                <label class="block sm:col-span-1">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_google_admin_email'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="email" x-model="settingsForm.google_config.admin_email" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" placeholder="admin@sirket.com">
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_google_auth_mode'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select x-model="settingsForm.google_config.auth_mode" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400">
                        <option value="service_account"><?= htmlspecialchars(__('settings_google_auth_service_account'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="oauth"><?= htmlspecialchars(__('settings_google_auth_oauth'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </label>
                <label x-show="settingsForm.google_config.auth_mode === 'service_account'" x-cloak class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_google_service_account_json'), ENT_QUOTES, 'UTF-8') ?></span>
                    <textarea
                        x-model="settingsForm.google_config.service_account_json"
                        rows="8"
                        class="w-full rounded-lg border border-zinc-300 px-3 py-2 font-mono text-xs outline-none focus:border-zinc-400"
                        :placeholder="settingsForm.google_config.service_account_configured ? '<?= htmlspecialchars(__('settings_secret_configured'), ENT_QUOTES, 'UTF-8') ?>' : '{ \"client_email\": \"...\", \"private_key\": \"...\" }'"
                    ></textarea>
                </label>
                <label x-show="settingsForm.google_config.auth_mode === 'oauth'" x-cloak class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_google_oauth_token_json'), ENT_QUOTES, 'UTF-8') ?></span>
                    <textarea
                        x-model="settingsForm.google_config.oauth_token_json"
                        rows="8"
                        class="w-full rounded-lg border border-zinc-300 px-3 py-2 font-mono text-xs outline-none focus:border-zinc-400"
                        :placeholder="settingsForm.google_config.oauth_token_configured ? '<?= htmlspecialchars(__('settings_secret_configured'), ENT_QUOTES, 'UTF-8') ?>' : '{ \"access_token\": \"...\", \"refresh_token\": \"...\" }'"
                    ></textarea>
                </label>
            </div>
        </article>

        <article class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('settings_login_title'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('settings_login_hint'), ENT_QUOTES, 'UTF-8') ?></p>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <label class="flex items-center gap-3 rounded-xl border border-zinc-200 px-4 py-3">
                    <input type="checkbox" x-model="settingsForm.login_config.providers.local" class="rounded border-zinc-300">
                    <span class="text-sm text-zinc-700"><?= htmlspecialchars(__('settings_login_local'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <label class="flex items-center gap-3 rounded-xl border border-zinc-200 px-4 py-3">
                    <input type="checkbox" x-model="settingsForm.login_config.providers.ldap" class="rounded border-zinc-300">
                    <span class="text-sm text-zinc-700"><?= htmlspecialchars(__('settings_login_ldap'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <label class="flex items-center gap-3 rounded-xl border border-zinc-200 px-4 py-3">
                    <input type="checkbox" x-model="settingsForm.login_config.providers.google" class="rounded border-zinc-300">
                    <span class="text-sm text-zinc-700"><?= htmlspecialchars(__('settings_login_google'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <label class="flex items-center gap-3 rounded-xl border border-zinc-200 px-4 py-3">
                    <input type="checkbox" x-model="settingsForm.login_config.providers.microsoft" class="rounded border-zinc-300">
                    <span class="text-sm text-zinc-700"><?= htmlspecialchars(__('settings_login_microsoft'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
            </div>

            <div x-show="settingsForm.login_config.providers.google" x-cloak class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_google_sso_client_id'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="settingsForm.login_config.google_sso.client_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400">
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_google_sso_client_secret'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="password" x-model="settingsForm.login_config.google_sso.client_secret" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" :placeholder="settingsForm.login_config.google_sso.client_secret_configured ? '<?= htmlspecialchars(__('settings_secret_configured'), ENT_QUOTES, 'UTF-8') ?>' : ''">
                </label>
                <p class="sm:col-span-2 text-xs text-zinc-400"><?= htmlspecialchars(__('settings_google_sso_redirect_hint'), ENT_QUOTES, 'UTF-8') ?>: /auth/callback/google</p>
            </div>

            <div x-show="settingsForm.login_config.providers.microsoft" x-cloak class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="block sm:col-span-1">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_microsoft_sso_tenant_id'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="settingsForm.login_config.microsoft_sso.tenant_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" placeholder="organizations">
                </label>
                <label class="block sm:col-span-1">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_microsoft_sso_client_id'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="settingsForm.login_config.microsoft_sso.client_id" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400">
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_microsoft_sso_client_secret'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="password" x-model="settingsForm.login_config.microsoft_sso.client_secret" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" :placeholder="settingsForm.login_config.microsoft_sso.client_secret_configured ? '<?= htmlspecialchars(__('settings_secret_configured'), ENT_QUOTES, 'UTF-8') ?>' : ''">
                </label>
                <p class="sm:col-span-2 text-xs text-zinc-400"><?= htmlspecialchars(__('settings_microsoft_sso_redirect_hint'), ENT_QUOTES, 'UTF-8') ?>: /auth/callback/microsoft</p>
            </div>
        </article>

        <article class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('settings_zimmet_title'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('settings_zimmet_hint'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-1 text-xs text-zinc-400"><?= htmlspecialchars(__('settings_zimmet_rich_hint'), ENT_QUOTES, 'UTF-8') ?></p>

            <input type="hidden" name="zimmet_template" x-ref="zimmetTemplateInput" :value="settingsForm.zimmet_template">

            <div class="mt-5 overflow-hidden rounded-xl border border-zinc-300 bg-white" id="zimmet-quill-wrapper">
                <div id="zimmet-quill-editor" class="min-h-[280px] text-sm text-zinc-800"></div>
            </div>

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
                        <label class="block sm:col-span-7">
                            <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_field_label'), ENT_QUOTES, 'UTF-8') ?></span>
                            <input
                                type="text"
                                x-model="field.label"
                                @input="syncCustomFieldCode(index)"
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

    <form x-show="settingsTab === 'smtp'" x-cloak @submit.prevent="saveSmtpSettings" class="space-y-6">
        <article class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('settings_smtp_title'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('settings_smtp_hint'), ENT_QUOTES, 'UTF-8') ?></p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="flex items-center gap-2 sm:col-span-2">
                    <input type="checkbox" x-model="settingsForm.smtp_config.enabled" class="rounded border-zinc-300">
                    <span class="text-sm text-zinc-700"><?= htmlspecialchars(__('settings_smtp_enabled'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_smtp_host'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="settingsForm.smtp_config.host" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" placeholder="smtp.sirket.com">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_smtp_port'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="number" min="1" max="65535" x-model="settingsForm.smtp_config.port" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" placeholder="587">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_smtp_user'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="settingsForm.smtp_config.user" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" placeholder="notifications@sirket.com">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_smtp_pass'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="password" x-model="settingsForm.smtp_config.pass" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" :placeholder="settingsForm.smtp_config.pass_configured ? '<?= htmlspecialchars(__('settings_secret_configured'), ENT_QUOTES, 'UTF-8') ?>' : ''">
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_smtp_encryption'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select x-model="settingsForm.smtp_config.encryption" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400">
                        <option value="tls"><?= htmlspecialchars(__('settings_smtp_encryption_tls'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="ssl"><?= htmlspecialchars(__('settings_smtp_encryption_ssl'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="none"><?= htmlspecialchars(__('settings_smtp_encryption_none'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_smtp_sender_email'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="email" x-model="settingsForm.smtp_config.sender_email" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" placeholder="itms@sirket.com">
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_smtp_sender_name'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="settingsForm.smtp_config.sender_name" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" placeholder="Betech ITMS">
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_smtp_support_to'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="settingsForm.smtp_config.support_to" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" placeholder="support@sirket.com, helpdesk@sirket.com">
                    <span class="mt-1 block text-xs text-zinc-400"><?= htmlspecialchars(__('settings_smtp_support_to_hint'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('settings_smtp_test_recipient'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="email" x-model="smtpTestRecipient" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400" placeholder="admin@sirket.com">
                </label>
            </div>
        </article>

        <div x-show="settingsErrorMessage" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="settingsErrorMessage"></div>
        <div x-show="settingsSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="settingsSuccessMessage"></div>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <button
                type="button"
                @click="sendSmtpTestEmail()"
                :disabled="isSendingSmtpTest || isSavingSettings"
                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span x-show="isSendingSmtpTest"><?= htmlspecialchars(__('settings_smtp_test_sending'), ENT_QUOTES, 'UTF-8') ?></span>
                <span x-show="!isSendingSmtpTest"><?= htmlspecialchars(__('settings_smtp_test_send'), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
            <button
                type="submit"
                :disabled="isSavingSettings || isSendingSmtpTest"
                class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span x-show="isSavingSettings"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                <span x-show="!isSavingSettings"><?= htmlspecialchars(__('settings_save'), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
        </div>
    </form>

    <section x-show="settingsTab === 'backup'" x-cloak class="space-y-6">
        <article class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('settings_backup_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('settings_backup_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-xs text-zinc-400" x-text="window.__i18n.settings_backup_retention.replace(':days', backupRetentionDays)"></p>
                </div>
                <button
                    type="button"
                    @click="createBackup()"
                    :disabled="isCreatingBackup || isLoadingBackups"
                    class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span x-show="isCreatingBackup"><?= htmlspecialchars(__('settings_backup_creating'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span x-show="!isCreatingBackup"><?= htmlspecialchars(__('settings_backup_create'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            </div>

            <p x-show="isLoadingBackups" x-cloak class="mt-5 text-sm text-zinc-500"><?= htmlspecialchars(__('settings_backup_loading'), ENT_QUOTES, 'UTF-8') ?></p>

            <p
                x-show="!isLoadingBackups && backups.length === 0"
                x-cloak
                class="mt-5 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-6 text-sm text-zinc-500"
            >
                <?= htmlspecialchars(__('settings_backup_empty'), ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div x-show="!isLoadingBackups && backups.length > 0" x-cloak class="mt-5 overflow-x-auto rounded-xl border border-zinc-200">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('settings_backup_col_filename'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('settings_backup_col_size'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('settings_backup_col_created'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('settings_backup_col_actions'), ENT_QUOTES, 'UTF-8') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 bg-white">
                        <template x-for="backup in backups" :key="backup.filename">
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-800" x-text="backup.filename"></td>
                                <td class="px-4 py-3 text-zinc-600" x-text="backup.size_label"></td>
                                <td class="px-4 py-3 text-zinc-600" x-text="backup.created_at"></td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        @click="downloadBackup(backup.filename)"
                                        class="inline-flex items-center rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                                    >
                                        <?= htmlspecialchars(__('settings_backup_download'), ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </article>

        <div x-show="backupErrorMessage" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="backupErrorMessage"></div>
        <div x-show="backupSuccessMessage" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="backupSuccessMessage"></div>
    </section>
</section>
