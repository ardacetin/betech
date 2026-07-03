<?php

declare(strict_types=1);

/**
 * @var string $appName
 * @var string $pageTitle
 * @var string $locale
 * @var string $csrfToken
 * @var string $assetTypesJson
 * @var int $activeAssetTypeId
 * @var string $activeAssetTypeSlug
 * @var string $assetSchemaJson
 * @var string $cancelUrl
 */
?>
<div class="min-h-screen bg-gray-50" x-data="inventoryFormPage()" x-init="init()">
    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-4">
            <div class="flex min-w-0 items-center gap-4">
                <a href="<?= htmlspecialchars($cancelUrl, ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg p-2 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-600" aria-label="<?= htmlspecialchars(__('back_to_inventory'), ENT_QUOTES, 'UTF-8') ?>">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <h1 class="truncate text-xl font-semibold text-zinc-900"><?= htmlspecialchars(__('inventory_add_page_title'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="mt-0.5 text-sm text-zinc-500"><?= htmlspecialchars(__('inventory_add_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <div class="hidden text-sm text-zinc-500 sm:block"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-6 py-8">
        <section class="mb-8 rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <label class="block max-w-xl">
                <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('select_asset_section'), ENT_QUOTES, 'UTF-8') ?> <span class="text-rose-600">*</span></span>
                <select
                    x-model="selectedTypeId"
                    @change="onTypeChange()"
                    required
                    class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                >
                    <option value=""><?= htmlspecialchars(__('select_asset_section_placeholder'), ENT_QUOTES, 'UTF-8') ?></option>
                    <template x-for="assetType in assetTypes" :key="assetType.id">
                        <option :value="String(assetType.id)" x-text="assetType.name"></option>
                    </template>
                </select>
            </label>
            <p x-show="schemaLoading" x-cloak class="mt-3 text-sm text-zinc-500"><?= htmlspecialchars(__('inventory_schema_loading'), ENT_QUOTES, 'UTF-8') ?></p>
            <p x-show="schemaError" x-cloak class="mt-3 text-sm text-rose-600" x-text="schemaError"></p>
        </section>

        <form x-show="selectedTypeId !== ''" x-cloak @submit.prevent="submitForm">
            <?php require __DIR__ . '/partials/inventory_form_fields.php'; ?>
        </form>
    </main>
</div>

<script>
    window.__i18n = {
        locale: <?= json_encode($locale, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        create_asset: <?= json_encode(__('create_asset'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        save_changes: <?= json_encode(__('save_changes'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        create_error: <?= json_encode(__('create_error'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        network_error: <?= json_encode(__('network_error'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        select_asset_section_required: <?= json_encode(__('select_asset_section_required'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        search_users_placeholder: <?= json_encode(__('search_users_placeholder'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        no_users_found: <?= json_encode(__('no_users_found'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        manual_user_create_error: <?= json_encode(__('manual_user_create_error'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
    };

    function inventoryFormPage() {
        return {
            mode: 'add',
            assetTypes: <?= $assetTypesJson ?>,
            selectedTypeId: <?= json_encode($activeAssetTypeId > 0 ? (string) $activeAssetTypeId : '', JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
            inventorySchema: <?= $assetSchemaJson ?>,
            schemaLoading: false,
            schemaError: '',
            form: {
                name: '',
                model: '',
                brand: '',
                serial_number: '',
                type: '',
                status: 'ready',
                location: '',
                building: '',
                mac_address_1: '',
                mac_address_2: '',
            },
            isSubmitting: false,
            errorMessage: '',
            userSearchQuery: '',
            userSearchResults: [],
            userSearchLoading: false,
            userSearchError: '',
            showUserResults: false,
            selectedUser: null,
            isManualUserFormOpen: false,
            isManualUserSubmitting: false,
            manualUserForm: { name: '', email: '' },
            manualUserFormError: '',
            init() {
                if (this.selectedTypeId !== '') {
                    this.syncExtensionFields();
                }
            },
            canSubmit() {
                return this.selectedTypeId !== '' && String(this.form.name || '').trim() !== '';
            },
            extensionColumns() {
                return (Array.isArray(this.inventorySchema) ? this.inventorySchema : [])
                    .filter((column) => column.is_custom || column.is_component);
            },
            syncExtensionFields() {
                this.extensionColumns().forEach((field) => {
                    if (!Object.prototype.hasOwnProperty.call(this.form, field.column)) {
                        this.form[field.column] = '';
                    }
                });
            },
            async onTypeChange() {
                this.schemaError = '';
                this.errorMessage = '';

                if (this.selectedTypeId === '') {
                    this.inventorySchema = [];
                    return;
                }

                const activeType = this.assetTypes.find((type) => String(type.id) === String(this.selectedTypeId));
                const identifier = activeType?.slug || this.selectedTypeId;

                this.schemaLoading = true;

                try {
                    const response = await fetch(`/api/asset-types/${encodeURIComponent(identifier)}/schema`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const result = await response.json().catch(() => ({}));

                    if (!response.ok || !Array.isArray(result?.data?.columns)) {
                        this.schemaError = result.message || window.__i18n.network_error;
                        this.inventorySchema = [];
                        return;
                    }

                    this.inventorySchema = result.data.columns;
                    this.syncExtensionFields();
                } catch (error) {
                    this.schemaError = window.__i18n.network_error;
                    this.inventorySchema = [];
                } finally {
                    this.schemaLoading = false;
                }
            },
            buildPayload() {
                const payload = {
                    name: String(this.form.name || '').trim(),
                    status: this.form.status,
                    asset_type_id: Number(this.selectedTypeId),
                };

                ['model', 'brand', 'serial_number', 'type', 'location', 'building', 'mac_address_1', 'mac_address_2'].forEach((field) => {
                    const value = String(this.form[field] || '').trim();

                    if (value !== '') {
                        payload[field] = value;
                    }
                });

                this.extensionColumns().forEach((field) => {
                    payload[field.column] = String(this.form[field.column] || '').trim();
                });

                if (this.selectedUser?.id) {
                    payload.personnel_id = Number(this.selectedUser.id);
                }

                return payload;
            },
            async submitForm() {
                if (!this.canSubmit()) {
                    this.errorMessage = window.__i18n.select_asset_section_required;
                    return;
                }

                this.isSubmitting = true;
                this.errorMessage = '';

                const activeType = this.assetTypes.find((type) => String(type.id) === String(this.selectedTypeId));
                const typeQuery = activeType?.slug ? `?type=${encodeURIComponent(activeType.slug)}` : '';

                try {
                    const response = await fetch(`/api/assets${typeQuery}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(this.buildPayload()),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        if (result.errors) {
                            this.errorMessage = Object.values(result.errors).flat().join(' ');
                        } else {
                            this.errorMessage = result.message || window.__i18n.create_error;
                        }

                        return;
                    }

                    const redirectSlug = activeType?.slug || '';
                    window.location.href = redirectSlug !== ''
                        ? `/inventory/${encodeURIComponent(redirectSlug)}`
                        : '/';
                } catch (error) {
                    this.errorMessage = window.__i18n.network_error;
                } finally {
                    this.isSubmitting = false;
                }
            },
            resetUserSearch() {
                this.userSearchQuery = '';
                this.userSearchResults = [];
                this.userSearchLoading = false;
                this.userSearchError = '';
                this.showUserResults = false;
                this.selectedUser = null;
                this.closeManualUserForm();
            },
            openManualUserForm() {
                this.manualUserFormError = '';
                this.isManualUserFormOpen = true;
                this.showUserResults = false;
            },
            closeManualUserForm() {
                if (this.isManualUserSubmitting) {
                    return;
                }

                this.isManualUserFormOpen = false;
                this.manualUserForm = { name: '', email: '' };
                this.manualUserFormError = '';
            },
            async submitManualUser() {
                this.isManualUserSubmitting = true;
                this.manualUserFormError = '';

                try {
                    const response = await fetch('/api/personnel', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            name: this.manualUserForm.name.trim(),
                            email: this.manualUserForm.email.trim(),
                        }),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.manualUserFormError = result.message || window.__i18n.manual_user_create_error;
                        return;
                    }

                    if (result.data) {
                        this.selectUser(result.data);
                    }

                    this.closeManualUserForm();
                } catch (error) {
                    this.manualUserFormError = window.__i18n.manual_user_create_error;
                } finally {
                    this.isManualUserSubmitting = false;
                }
            },
            async searchUsers() {
                this.userSearchLoading = true;
                this.userSearchError = '';

                try {
                    const query = encodeURIComponent(String(this.userSearchQuery || '').trim());
                    const response = await fetch(`/api/personnel/search?q=${query}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.userSearchResults = [];
                        this.userSearchError = result.message || window.__i18n.network_error;
                    } else {
                        this.userSearchResults = Array.isArray(result.data) ? result.data : [];
                    }
                } catch (error) {
                    this.userSearchResults = [];
                    this.userSearchError = window.__i18n.network_error;
                } finally {
                    this.showUserResults = true;
                    this.userSearchLoading = false;
                }
            },
            selectUser(user) {
                this.selectedUser = user;
                this.userSearchQuery = '';
                this.userSearchResults = [];
                this.userSearchError = '';
                this.showUserResults = false;
            },
            clearSelectedUser() {
                this.selectedUser = null;
            },
        };
    }
</script>
