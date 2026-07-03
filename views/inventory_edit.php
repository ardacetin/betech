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
 * @var string $activeAssetTypeName
 * @var string $assetSchemaJson
 * @var string $assetJson
 * @var string $cancelUrl
 */
?>
<div class="min-h-screen bg-gray-50" x-data="inventoryEditPage()" x-init="init()">
    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-4">
            <div class="flex min-w-0 items-center gap-4">
                <a href="<?= htmlspecialchars($cancelUrl, ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg p-2 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-600" aria-label="<?= htmlspecialchars(__('back_to_inventory'), ENT_QUOTES, 'UTF-8') ?>">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </a>
                <div class="min-w-0">
                    <h1 class="truncate text-xl font-semibold text-zinc-900"><?= htmlspecialchars(__('inventory_edit_page_title'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="mt-0.5 text-sm text-zinc-500">
                        <?= htmlspecialchars(__('inventory_edit_page_subtitle'), ENT_QUOTES, 'UTF-8') ?>
                        <span class="font-medium text-zinc-700"><?= htmlspecialchars($activeAssetTypeName, ENT_QUOTES, 'UTF-8') ?></span>
                    </p>
                </div>
            </div>
            <div class="hidden text-sm text-zinc-500 sm:block"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-6 py-8">
        <section class="mb-8 rounded-2xl border border-zinc-200 bg-zinc-50 p-4">
            <p class="text-sm text-zinc-600">
                <?= htmlspecialchars(__('inventory_edit_type_locked'), ENT_QUOTES, 'UTF-8') ?>:
                <span class="font-medium text-zinc-900"><?= htmlspecialchars($activeAssetTypeName, ENT_QUOTES, 'UTF-8') ?></span>
            </p>
        </section>

        <form @submit.prevent="submitForm">
            <?php require __DIR__ . '/partials/inventory_form_fields.php'; ?>
        </form>
    </main>
</div>

<script>
    window.__i18n = {
        locale: <?= json_encode($locale, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        create_asset: <?= json_encode(__('create_asset'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        save_changes: <?= json_encode(__('save_changes'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        update_error: <?= json_encode(__('update_error'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        network_error: <?= json_encode(__('network_error'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        search_users_placeholder: <?= json_encode(__('search_users_placeholder'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        no_users_found: <?= json_encode(__('no_users_found'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
        manual_user_create_error: <?= json_encode(__('manual_user_create_error'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
    };

    function inventoryEditPage() {
        return {
            mode: 'edit',
            assetId: 0,
            assetTypeSlug: <?= json_encode($activeAssetTypeSlug, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
            inventorySchema: <?= $assetSchemaJson ?>,
            form: {
                asset_tag: '',
                name: '',
                model: '',
                brand: '',
                serial_number: '',
                type: '',
                status: 'ready',
                location: '',
                building: '',
                assigned_to: '',
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
                const asset = <?= $assetJson ?>;
                this.assetId = Number(asset.id || 0);
                this.form = {
                    asset_tag: asset.asset_tag || '',
                    name: asset.name || '',
                    model: asset.model || '',
                    brand: asset.brand || '',
                    serial_number: asset.serial_number || '',
                    type: asset.type || asset.category_name || '',
                    status: asset.status || 'ready',
                    location: asset.location || asset.location_name || '',
                    building: asset.building || asset.location_building || '',
                    assigned_to: asset.assigned_to || asset.user_name || '',
                    mac_address_1: asset.mac_address_1 || '',
                    mac_address_2: asset.mac_address_2 || '',
                };

                this.extensionColumns().forEach((field) => {
                    this.form[field.column] = asset[field.column] || '';
                });

                if (this.form.assigned_to) {
                    this.selectedUser = {
                        id: '',
                        name: this.form.assigned_to,
                        email: this.form.assigned_to,
                        department: null,
                    };
                }
            },
            canSubmit() {
                return this.assetId > 0 && String(this.form.name || '').trim() !== '';
            },
            extensionColumns() {
                return (Array.isArray(this.inventorySchema) ? this.inventorySchema : [])
                    .filter((column) => column.is_custom || column.is_component);
            },
            buildPayload() {
                const payload = {
                    asset_tag: String(this.form.asset_tag || '').trim(),
                    name: String(this.form.name || '').trim(),
                    status: this.form.status,
                };

                ['model', 'brand', 'serial_number', 'type', 'location', 'building', 'mac_address_1', 'mac_address_2'].forEach((field) => {
                    payload[field] = String(this.form[field] || '').trim();
                });

                this.extensionColumns().forEach((field) => {
                    payload[field.column] = String(this.form[field.column] || '').trim();
                });

                if (this.selectedUser?.id) {
                    payload.personnel_id = Number(this.selectedUser.id);
                } else {
                    payload.assigned_to = String(
                        this.form.assigned_to
                        || this.selectedUser?.name
                        || this.selectedUser?.email
                        || ''
                    ).trim();
                }

                return payload;
            },
            async submitForm() {
                if (!this.canSubmit()) {
                    return;
                }

                this.isSubmitting = true;
                this.errorMessage = '';

                const typeQuery = this.assetTypeSlug !== ''
                    ? `?type=${encodeURIComponent(this.assetTypeSlug)}`
                    : '';

                try {
                    const response = await fetch(`/api/assets/${this.assetId}${typeQuery}`, {
                        method: 'PUT',
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
                            this.errorMessage = result.message || window.__i18n.update_error;
                        }

                        return;
                    }

                    window.location.href = <?= json_encode($cancelUrl, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
                } catch (error) {
                    this.errorMessage = window.__i18n.network_error;
                } finally {
                    this.isSubmitting = false;
                }
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
