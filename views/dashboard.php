<?php

declare(strict_types=1);

/**
 * @var string $appName
 * @var string $pageTitle
 * @var string $environment
 * @var string $locale
 * @var list<array<string, mixed>> $assets
 * @var array<string, mixed> $analytics
 * @var string $analyticsJson
 * @var list<array<string, mixed>> $categories
 * @var string $categoryFieldsJson
 * @var string $assetQrCodesJson
 * @var string $settingsJson
 * @var string $globalCustomFieldsJson
 * @var string $personnelJson
 */

$userRole = $userRole ?? 'end_user';
$canManageAssets = $canManageAssets ?? false;
$canAccessSettings = $canAccessSettings ?? false;
$canAccessPersonnel = $canAccessPersonnel ?? false;
$isEndUser = $isEndUser ?? false;
$isSuperAdmin = $isSuperAdmin ?? false;
$hasPersonnelProfile = $hasPersonnelProfile ?? true;
$userName = $userName ?? '';
$userEmail = $userEmail ?? '';

$statusStyles = [
    'ready' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
    'deployed' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    'storage' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
    'broken' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
    'under_repair' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
];

$translateStatus = static function (string $status): string {
    $key = 'status_' . $status;

    return __($key);
};

$formatPropertyValue = static function (mixed $value): string {
    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    return (string) $value;
};

$formatLocationLabel = static function (?string $building, ?string $name): string {
    $building = trim((string) $building);
    $name = trim((string) $name);

    if ($name === '') {
        return '';
    }

    if ($building === '') {
        return $name;
    }

    return $building . ' / ' . $name;
};

$assetOptions = array_map(
    static fn (array $asset): array => [
        'id' => (int) $asset['id'],
        'asset_tag' => (string) ($asset['asset_tag'] ?? ''),
        'name' => (string) ($asset['name'] ?? ''),
    ],
    $canManageAssets ? $assets : []
);
$assetOptionsJson = json_encode($assetOptions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

$assetFilterDefinitions = $assetFilterDefinitions ?? [];
$assetActiveFilters = $assetActiveFilters ?? [];
$initialAssetFilters = [];

foreach ($assetFilterDefinitions as $filterDefinition) {
    $filterName = (string) ($filterDefinition['name'] ?? '');

    if ($filterName === '') {
        continue;
    }

    $initialAssetFilters[$filterName] = $assetActiveFilters[$filterName] ?? '';
}

$assetFilterFieldsJson = json_encode($assetFilterDefinitions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$initialAssetFiltersJson = json_encode($initialAssetFilters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$inventoryAssetsJson = json_encode($canManageAssets ? $assets : [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$assetPagination = $assetPagination ?? ['page' => 1, 'per_page' => 50, 'total' => 0, 'total_pages' => 1];
$assetPaginationJson = json_encode($assetPagination, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

$licenseFilterDefinitions = $licenseFilterDefinitions ?? [];
$licenseActiveFilters = $licenseActiveFilters ?? [];
$initialLicenseFilters = [];

foreach ($licenseFilterDefinitions as $filterDefinition) {
    $filterName = (string) ($filterDefinition['name'] ?? '');

    if ($filterName === '') {
        continue;
    }

    $initialLicenseFilters[$filterName] = $licenseActiveFilters[$filterName] ?? '';
}

$licenseFilterFieldsJson = json_encode($licenseFilterDefinitions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$initialLicenseFiltersJson = json_encode($initialLicenseFilters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

$consumableFilterDefinitions = $consumableFilterDefinitions ?? [];
$consumableActiveFilters = $consumableActiveFilters ?? [];
$initialConsumableFilters = [];

foreach ($consumableFilterDefinitions as $filterDefinition) {
    $filterName = (string) ($filterDefinition['name'] ?? '');

    if ($filterName === '') {
        continue;
    }

    $initialConsumableFilters[$filterName] = $consumableActiveFilters[$filterName] ?? '';
}

$consumableFilterFieldsJson = json_encode($consumableFilterDefinitions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
$initialConsumableFiltersJson = json_encode($initialConsumableFilters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

$i18nScript = json_encode([
    'create_error' => __('create_error'),
    'update_error' => __('update_error'),
    'network_error' => __('network_error'),
    'locale' => $locale ?? 'tr',
    'no_category_fields' => __('no_category_fields'),
    'no_users_found' => __('no_users_found'),
    'personnel_search_failed' => __('personnel_search_failed'),
    'search_users_placeholder' => __('search_users_placeholder'),
    'label_mac_address_1' => __('label_mac_address_1'),
    'label_mac_address_2' => __('label_mac_address_2'),
    'history_empty' => __('history_empty'),
    'history_loading' => __('history_loading'),
    'history_error' => __('history_error'),
    'history_action_created' => __('history_action_created'),
    'history_action_assigned' => __('history_action_assigned'),
    'history_action_unassigned' => __('history_action_unassigned'),
    'history_action_status_change' => __('history_action_status_change'),
    'history_action_updated' => __('history_action_updated'),
    'settings_save_success' => __('settings_save_success'),
    'settings_save_error' => __('settings_save_error'),
    'settings_network_error' => __('settings_network_error'),
    'settings_smtp_test_success' => __('settings_smtp_test_success'),
    'settings_smtp_test_failed' => __('settings_smtp_test_failed'),
    'settings_smtp_test_recipient_invalid' => __('settings_smtp_test_recipient_invalid'),
    'settings_smtp_test_validation_failed' => __('settings_smtp_test_validation_failed'),
    'settings_backup_retention' => __('settings_backup_retention'),
    'backup_create_success' => __('backup_create_success'),
    'backup_create_error' => __('backup_create_error'),
    'backup_fetch_error' => __('backup_fetch_error'),
    'backup_network_error' => __('backup_network_error'),
    'backup_download_error' => __('backup_download_error'),
    'settings_auth_local' => __('settings_auth_local'),
    'settings_auth_local_hint' => __('settings_auth_local_hint'),
    'settings_auth_ldap' => __('settings_auth_ldap'),
    'settings_auth_ldap_hint' => __('settings_auth_ldap_hint'),
    'settings_auth_google' => __('settings_auth_google'),
    'settings_auth_google_hint' => __('settings_auth_google_hint'),
    'settings_auth_azure' => __('settings_auth_azure'),
    'settings_auth_azure_hint' => __('settings_auth_azure_hint'),
    'history_action_offboarded' => __('history_action_offboarded'),
    'offboard_confirm' => __('offboard_confirm'),
    'offboard_success' => __('offboard_success'),
    'offboard_error' => __('offboard_error'),
    'offboard_network_error' => __('offboard_network_error'),
    'personnel_status_active' => __('personnel_status_active'),
    'personnel_status_offboarded' => __('personnel_status_offboarded'),
    'personnel_sync_button' => __('personnel_sync_button'),
    'personnel_syncing' => __('personnel_syncing'),
    'personnel_sync_success' => __('personnel_sync_success'),
    'personnel_sync_error' => __('personnel_sync_error'),
    'personnel_sync_unsupported' => __('personnel_sync_unsupported'),
    'personnel_sync_empty' => __('personnel_sync_empty'),
    'personnel_sync_failed' => __('personnel_sync_failed'),
    'personnel_ldap_sync_button' => __('personnel_ldap_sync_button'),
    'personnel_ldap_syncing' => __('personnel_ldap_syncing'),
    'personnel_ldap_sync_success' => __('personnel_ldap_sync_success'),
    'personnel_ldap_sync_error' => __('personnel_ldap_sync_error'),
    'personnel_search_placeholder' => __('personnel_search_placeholder'),
    'personnel_pagination_prev' => __('personnel_pagination_prev'),
    'personnel_pagination_next' => __('personnel_pagination_next'),
    'personnel_pagination_info' => __('personnel_pagination_info'),
    'personnel_fetch_error' => __('personnel_fetch_error'),
    'personnel_network_error' => __('personnel_network_error'),
    'personnel_role_user' => __('personnel_role_user'),
    'personnel_role_admin' => __('personnel_role_admin'),
    'personnel_role_update_success' => __('personnel_role_update_success'),
    'personnel_role_update_error' => __('personnel_role_update_error'),
    'delete_confirm' => __('delete_confirm'),
    'delete_success' => __('delete_success'),
    'delete_error' => __('delete_error'),
    'delete_network_error' => __('delete_network_error'),
    'import_success' => __('import_success'),
    'import_partial_success' => __('import_partial_success'),
    'import_all_failed' => __('import_all_failed'),
    'import_network_error' => __('import_network_error'),
    'import_file_missing' => __('import_file_missing'),
    'import_row_error' => __('import_row_error'),
    'import_mapping_preview_error' => __('import_mapping_preview_error'),
    'import_map_select' => __('import_map_select'),
    'import_map_device_name' => __('import_map_device_name'),
    'import_map_serial_number' => __('import_map_serial_number'),
    'import_map_asset_tag' => __('import_map_asset_tag'),
    'import_map_brand' => __('import_map_brand'),
    'import_map_device_model' => __('import_map_device_model'),
    'import_map_category' => __('import_map_category'),
    'import_map_status' => __('import_map_status'),
    'import_map_location' => __('import_map_location'),
    'import_map_building' => __('import_map_building'),
    'import_map_personnel' => __('import_map_personnel'),
    'import_map_custom_birim' => __('import_map_custom_birim'),
    'import_map_custom_grup' => __('import_map_custom_grup'),
    'import_map_custom_konum' => __('import_map_custom_konum'),
    'import_map_custom_son_guncelleme' => __('import_map_custom_son_guncelleme'),
    'import_map_custom_mac_adresi_1' => __('import_map_custom_mac_adresi_1'),
    'import_map_custom_mac_adresi_2' => __('import_map_custom_mac_adresi_2'),
    'import_map_custom_eski_kullanici' => __('import_map_custom_eski_kullanici'),
    'return_confirm' => __('return_confirm'),
    'return_success' => __('return_success'),
    'return_error' => __('return_error'),
    'return_network_error' => __('return_network_error'),
    'transfer_success' => __('transfer_success'),
    'transfer_error' => __('transfer_error'),
    'transfer_network_error' => __('transfer_network_error'),
    'transfer_select_user' => __('transfer_select_user'),
    'transfer_print_tutanak_prompt' => __('transfer_print_tutanak_prompt'),
    'assign_select_user' => __('assign_select_user'),
    'assign_success' => __('assign_success'),
    'assign_error' => __('assign_error'),
    'assign_network_error' => __('assign_network_error'),
    'assign_print_tutanak_prompt' => __('assign_print_tutanak_prompt'),
    'history_action_returned' => __('history_action_returned'),
    'history_action_transferred' => __('history_action_transferred'),
    'categories_fetch_error' => __('categories_fetch_error'),
    'categories_network_error' => __('categories_network_error'),
    'category_create_success' => __('category_create_success'),
    'category_update_success' => __('category_update_success'),
    'category_delete_success' => __('category_delete_success'),
    'category_create_error' => __('category_create_error'),
    'category_update_error' => __('category_update_error'),
    'category_delete_error' => __('category_delete_error'),
    'category_delete_confirm' => __('category_delete_confirm'),
    'category_delete_in_use' => __('category_delete_in_use'),
    'category_field_count' => __('category_field_count'),
    'locations_fetch_error' => __('locations_fetch_error'),
    'locations_network_error' => __('locations_network_error'),
    'location_create_success' => __('location_create_success'),
    'location_update_success' => __('location_update_success'),
    'location_delete_success' => __('location_delete_success'),
    'location_create_error' => __('location_create_error'),
    'location_update_error' => __('location_update_error'),
    'location_delete_error' => __('location_delete_error'),
    'location_delete_confirm' => __('location_delete_confirm'),
    'location_delete_in_use' => __('location_delete_in_use'),
    'history_action_location_moved' => __('history_action_location_moved'),
    'licenses_fetch_error' => __('licenses_fetch_error'),
    'licenses_network_error' => __('licenses_network_error'),
    'license_create_success' => __('license_create_success'),
    'license_create_error' => __('license_create_error'),
    'license_assign_success' => __('license_assign_success'),
    'license_assign_error' => __('license_assign_error'),
    'license_unassign_success' => __('license_unassign_success'),
    'license_unassign_error' => __('license_unassign_error'),
    'license_unassign_confirm' => __('license_unassign_confirm'),
    'license_seats_in_use' => __('license_seats_in_use'),
    'license_no_expiration' => __('license_no_expiration'),
    'asset_licenses_error' => __('asset_licenses_error'),
    'consumables_fetch_error' => __('consumables_fetch_error'),
    'consumables_network_error' => __('consumables_network_error'),
    'consumable_create_success' => __('consumable_create_success'),
    'consumable_create_error' => __('consumable_create_error'),
    'consumable_update_success' => __('consumable_update_success'),
    'consumable_update_error' => __('consumable_update_error'),
    'consumable_delete_success' => __('consumable_delete_success'),
    'consumable_checkout_success' => __('consumable_checkout_success'),
    'consumable_restock_success' => __('consumable_restock_success'),
    'consumable_checkout_error' => __('consumable_checkout_error'),
    'consumable_restock_error' => __('consumable_restock_error'),
    'consumable_delete_confirm' => __('consumable_delete_confirm'),
    'kb_fetch_error' => __('kb_fetch_error'),
    'kb_network_error' => __('kb_network_error'),
    'kb_create_success' => __('kb_create_success'),
    'kb_create_error' => __('kb_create_error'),
    'kb_update_success' => __('kb_update_success'),
    'kb_update_error' => __('kb_update_error'),
    'kb_delete_success' => __('kb_delete_success'),
    'kb_delete_error' => __('kb_delete_error'),
    'kb_delete_confirm' => __('kb_delete_confirm'),
    'portal_knowledge_base_error' => __('portal_knowledge_base_error'),
    'helpdesk_fetch_error' => __('helpdesk_fetch_error'),
    'helpdesk_network_error' => __('helpdesk_network_error'),
    'ticket_create_success' => __('ticket_create_success'),
    'ticket_create_error' => __('ticket_create_error'),
    'ticket_update_success' => __('ticket_update_success'),
    'ticket_update_error' => __('ticket_update_error'),
    'ticket_delete_success' => __('ticket_delete_success'),
    'ticket_delete_error' => __('ticket_delete_error'),
    'ticket_delete_confirm' => __('ticket_delete_confirm'),
    'ticket_category_create_success' => __('ticket_category_create_success'),
    'ticket_category_create_error' => __('ticket_category_create_error'),
    'ticket_category_update_success' => __('ticket_category_update_success'),
    'ticket_category_update_error' => __('ticket_category_update_error'),
    'ticket_category_delete_success' => __('ticket_category_delete_success'),
    'ticket_category_delete_confirm' => __('ticket_category_delete_confirm'),
    'reports_fetch_error' => __('reports_fetch_error'),
    'ticket_comment_create_success' => __('ticket_comment_create_success'),
    'ticket_comment_create_error' => __('ticket_comment_create_error'),
    'helpdesk_filter_all' => __('helpdesk_filter_all'),
    'ticket_status_open' => __('ticket_status_open'),
    'ticket_status_in_progress' => __('ticket_status_in_progress'),
    'ticket_status_resolved' => __('ticket_status_resolved'),
    'ticket_status_closed' => __('ticket_status_closed'),
    'ticket_priority_low' => __('ticket_priority_low'),
    'ticket_priority_medium' => __('ticket_priority_medium'),
    'ticket_priority_high' => __('ticket_priority_high'),
    'ticket_priority_critical' => __('ticket_priority_critical'),
    'ticket_personnel_required' => __('ticket_personnel_required'),
    'add_manual_user' => __('add_manual_user'),
    'manual_user_create_button' => __('manual_user_create_button'),
    'manual_user_create_error' => __('manual_user_create_error'),
    'dashboard_activity_assigned' => __('dashboard_activity_assigned'),
    'dashboard_activity_transferred' => __('dashboard_activity_transferred'),
    'dashboard_activity_returned' => __('dashboard_activity_returned'),
    'dashboard_activity_created' => __('dashboard_activity_created'),
    'dashboard_activity_updated' => __('dashboard_activity_updated'),
    'dashboard_activity_offboarded' => __('dashboard_activity_offboarded'),
    'dashboard_activity_unassigned' => __('dashboard_activity_unassigned'),
    'dashboard_activity_status_change' => __('dashboard_activity_status_change'),
    'dashboard_activity_location_moved' => __('dashboard_activity_location_moved'),
    'dashboard_log_template' => __('dashboard_log_template'),
    'dashboard_log_template_simple' => __('dashboard_log_template_simple'),
    'dashboard_log_unknown_user' => __('dashboard_log_unknown_user'),
    'dashboard_log_verb_created' => __('dashboard_log_verb_created'),
    'dashboard_log_verb_updated' => __('dashboard_log_verb_updated'),
    'dashboard_log_verb_deleted' => __('dashboard_log_verb_deleted'),
    'dashboard_log_verb_login' => __('dashboard_log_verb_login'),
    'dashboard_log_verb_assigned' => __('dashboard_log_verb_assigned'),
    'dashboard_log_verb_returned' => __('dashboard_log_verb_returned'),
    'dashboard_log_verb_transferred' => __('dashboard_log_verb_transferred'),
    'dashboard_log_entity_asset' => __('dashboard_log_entity_asset'),
    'dashboard_log_entity_ticket' => __('dashboard_log_entity_ticket'),
    'dashboard_log_entity_category' => __('dashboard_log_entity_category'),
    'dashboard_log_entity_setting' => __('dashboard_log_entity_setting'),
    'dashboard_log_entity_user' => __('dashboard_log_entity_user'),
    'dashboard_log_entity_network' => __('dashboard_log_entity_network'),
    'mail_ticket_reply_label' => __('mail_ticket_reply_label'),
    'yes' => __('yes'),
    'no' => __('no'),
    'nav_audit_logs' => __('nav_audit_logs'),
    'audit_logs_page_title' => __('audit_logs_page_title'),
    'audit_logs_page_subtitle' => __('audit_logs_page_subtitle'),
    'audit_logs_loading' => __('audit_logs_loading'),
    'audit_logs_empty' => __('audit_logs_empty'),
    'audit_logs_fetch_error' => __('audit_logs_fetch_error'),
    'audit_logs_network_error' => __('audit_logs_network_error'),
    'audit_filter_user' => __('audit_filter_user'),
    'audit_filter_action' => __('audit_filter_action'),
    'audit_filter_entity' => __('audit_filter_entity'),
    'audit_filter_date_from' => __('audit_filter_date_from'),
    'audit_filter_date_to' => __('audit_filter_date_to'),
    'audit_filter_all_users' => __('audit_filter_all_users'),
    'audit_filter_all_actions' => __('audit_filter_all_actions'),
    'audit_filter_all_entities' => __('audit_filter_all_entities'),
    'audit_filter_reset' => __('audit_filter_reset'),
    'audit_refresh' => __('audit_refresh'),
    'audit_col_timestamp' => __('audit_col_timestamp'),
    'audit_col_user' => __('audit_col_user'),
    'audit_col_action' => __('audit_col_action'),
    'audit_col_entity' => __('audit_col_entity'),
    'audit_col_summary' => __('audit_col_summary'),
    'audit_col_ip' => __('audit_col_ip'),
    'audit_pagination_prev' => __('audit_pagination_prev'),
    'audit_pagination_next' => __('audit_pagination_next'),
    'audit_pagination_info' => __('audit_pagination_info'),
    'audit_action_created' => __('audit_action_created'),
    'audit_action_updated' => __('audit_action_updated'),
    'audit_action_deleted' => __('audit_action_deleted'),
    'audit_action_login' => __('audit_action_login'),
    'audit_action_assigned' => __('audit_action_assigned'),
    'audit_action_returned' => __('audit_action_returned'),
    'audit_action_transferred' => __('audit_action_transferred'),
    'audit_entity_asset' => __('audit_entity_asset'),
    'audit_entity_ticket' => __('audit_entity_ticket'),
    'audit_entity_category' => __('audit_entity_category'),
    'audit_entity_setting' => __('audit_entity_setting'),
    'audit_entity_user' => __('audit_entity_user'),
    'nav_ipam' => __('nav_ipam'),
    'ipam_page_title' => __('ipam_page_title'),
    'ipam_page_subtitle' => __('ipam_page_subtitle'),
    'ipam_loading' => __('ipam_loading'),
    'ipam_networks_empty' => __('ipam_networks_empty'),
    'ipam_fetch_error' => __('ipam_fetch_error'),
    'ipam_network_error' => __('ipam_network_error'),
    'ipam_network_create_success' => __('ipam_network_create_success'),
    'ipam_network_update_success' => __('ipam_network_update_success'),
    'ipam_network_delete_success' => __('ipam_network_delete_success'),
    'ipam_network_delete_confirm' => __('ipam_network_delete_confirm'),
    'ipam_address_update_success' => __('ipam_address_update_success'),
    'ipam_utilization' => __('ipam_utilization'),
    'ipam_status_available' => __('ipam_status_available'),
    'ipam_status_reserved' => __('ipam_status_reserved'),
    'ipam_status_assigned' => __('ipam_status_assigned'),
    'ipam_status_dhcp' => __('ipam_status_dhcp'),
    'ipam_status_broken' => __('ipam_status_broken'),
    'ipam_bulk_edit' => __('ipam_bulk_edit'),
    'ipam_bulk_edit_title' => __('ipam_bulk_edit_title'),
    'ipam_bulk_edit_subtitle' => __('ipam_bulk_edit_subtitle'),
    'ipam_bulk_edit_selected_count' => __('ipam_bulk_edit_selected_count'),
    'ipam_bulk_edit_select_minimum' => __('ipam_bulk_edit_select_minimum'),
    'ipam_bulk_edit_confirm' => __('ipam_bulk_edit_confirm'),
    'ipam_bulk_edit_no_fields' => __('ipam_bulk_edit_no_fields'),
    'ipam_bulk_update_success' => __('ipam_bulk_update_success'),
    'audit_entity_ip_address' => __('audit_entity_ip_address'),
    'ipam_filter_all' => __('ipam_filter_all'),
    'ipam_import_success' => __('ipam_import_success'),
    'ipam_import_partial_success' => __('ipam_import_partial_success'),
    'portal_assets_loading' => __('portal_assets_loading'),
    'portal_assets_empty' => __('portal_assets_empty'),
    'portal_assets_empty_hint' => __('portal_assets_empty_hint'),
    'portal_create_ticket' => __('portal_create_ticket'),
    'portal_assets_error' => __('portal_assets_error'),
    'portal_tickets_loading' => __('portal_tickets_loading'),
    'portal_tickets_empty' => __('portal_tickets_empty'),
    'portal_tickets_error' => __('portal_tickets_error'),
    'portal_report_issue' => __('portal_report_issue'),
    'portal_ticket_for_asset' => __('portal_ticket_for_asset'),
    'ticket_not_found' => __('ticket_not_found'),
    'status_ready' => __('status_ready'),
    'status_deployed' => __('status_deployed'),
    'status_storage' => __('status_storage'),
    'status_broken' => __('status_broken'),
    'status_under_repair' => __('status_under_repair'),
    'col_asset_tag' => __('col_asset_tag'),
    'col_name' => __('col_name'),
    'col_category' => __('col_category'),
    'col_status' => __('col_status'),
    'col_assigned_user' => __('col_assigned_user'),
    'col_location' => __('col_location'),
    'label_serial_number' => __('label_serial_number'),
    'unknown_category' => __('unknown_category'),
    'inventory_filter_error' => __('inventory_filter_error'),
    'licenses_filter_error' => __('licenses_filter_error'),
    'consumables_filter_error' => __('consumables_filter_error'),
    'list_pagination_prev' => __('list_pagination_prev'),
    'list_pagination_next' => __('list_pagination_next'),
    'list_pagination_info' => __('list_pagination_info'),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
?>
<div class="min-h-screen bg-gray-50" x-data="assetDashboard()" x-init="restoreDashboardView(); if (isEndUser) { initEndUserPortal(); } else if (canManageAssets) { fetchCategories(); fetchLocations(); fetchTicketCategories(); fetchLicenses(); fetchConsumables(); fetchTickets(); if (activeView === 'dashboard') { fetchDashboardStats(); } } if (canAccessSettings && activeView === 'reports') { fetchReports(); } this.isAssignLicenseModalOpen = false;">
    <div class="flex h-screen overflow-hidden bg-gray-50">
        <aside class="hidden h-full w-64 min-h-0 flex-shrink-0 flex-col border-r border-gray-200 bg-white lg:flex">
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-gray-200 px-5">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gray-900 text-sm font-semibold text-white">B</div>
                <div class="flex min-w-0 flex-col">
                    <span class="text-xl font-bold tracking-tight text-gray-900">Betech</span>
                </div>
            </div>

            <?php require __DIR__ . '/partials/sidebar_nav.php'; ?>

            <?php
                $sidebarPrimaryLabel = $userName !== '' ? $userName : ($userEmail !== '' ? $userEmail : __('app_name'));
                $sidebarRoleLabel = $isEndUser ? __('personnel_role_user') : __('personnel_role_admin');
            ?>
            <div class="mt-auto flex shrink-0 items-center justify-between border-t border-gray-200 bg-gray-50/50 p-4">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-gray-900"><?= htmlspecialchars($sidebarPrimaryLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="truncate text-xs text-gray-500"><?= htmlspecialchars($sidebarRoleLabel, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <a
                    href="/logout"
                    title="<?= htmlspecialchars(__('nav_logout'), ENT_QUOTES, 'UTF-8') ?>"
                    aria-label="<?= htmlspecialchars(__('nav_logout'), ENT_QUOTES, 'UTF-8') ?>"
                    class="shrink-0 rounded-lg p-2 text-gray-400 transition-colors hover:bg-white hover:text-red-600"
                >
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"></path>
                    </svg>
                </a>
            </div>
        </aside>

        <main class="flex-1 overflow-y-auto">
            <header class="sticky top-0 z-10 border-b border-zinc-200 bg-white/90 backdrop-blur">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-4">
                    <div>
                        <h1
                            class="text-2xl font-semibold tracking-tight text-zinc-900"
                            x-text="resolvePageTitle()"
                        ></h1>
                        <p
                            class="mt-1 text-sm text-zinc-500"
                            x-text="resolvePageSubtitle()"
                        ></p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="inline-flex items-center rounded-xl border border-zinc-200 bg-white p-1 shadow-soft">
                            <span class="sr-only"><?= htmlspecialchars(__('language'), ENT_QUOTES, 'UTF-8') ?></span>
                            <a
                                href="<?= htmlspecialchars(lang_url('tr'), ENT_QUOTES, 'UTF-8') ?>"
                                class="<?= ($locale ?? 'tr') === 'tr' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100' ?> rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                            >TR</a>
                            <a
                                href="<?= htmlspecialchars(lang_url('en'), ENT_QUOTES, 'UTF-8') ?>"
                                class="<?= ($locale ?? 'tr') === 'en' ? 'bg-zinc-900 text-white' : 'text-zinc-600 hover:bg-zinc-100' ?> rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                            >EN</a>
                        </div>
                        <button
                            type="button"
                            x-show="activeView === 'dashboard' && canManageAssets"
                            @click="fetchDashboardStats()"
                            :disabled="dashboardLoading"
                            class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 shadow-soft transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <svg x-show="dashboardLoading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <?= htmlspecialchars(__('dashboard_refresh'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button
                            type="button"
                            x-show="activeView === 'assets' && canManageAssets"
                            @click="exportAssets()"
                            class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 shadow-soft transition hover:bg-zinc-50"
                        >
                            <?= htmlspecialchars(__('export_assets'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button
                            type="button"
                            x-show="activeView === 'assets' && canManageAssets"
                            @click="openImportModal()"
                            class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 shadow-soft transition hover:bg-zinc-50"
                        >
                            <?= htmlspecialchars(__('inventory_import_excel'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button
                            type="button"
                            x-show="activeView === 'assets' && canManageAssets"
                            @click="openAddModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
                        >
                            <span class="text-lg leading-none">+</span>
                            <?= htmlspecialchars(__('add_asset'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button
                            type="button"
                            x-show="activeView === 'settings' && settingsTab === 'categories' && canAccessSettings"
                            @click="openCategoryModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
                        >
                            <span class="text-lg leading-none">+</span>
                            <?= htmlspecialchars(__('add_category'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button
                            type="button"
                            x-show="activeView === 'settings' && settingsTab === 'locations' && canAccessSettings"
                            @click="openLocationModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
                        >
                            <span class="text-lg leading-none">+</span>
                            <?= htmlspecialchars(__('add_location'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button
                            type="button"
                            x-show="activeView === 'settings' && settingsTab === 'ticket_categories' && canAccessSettings"
                            @click="openTicketCategoryModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
                        >
                            <span class="text-lg leading-none">+</span>
                            <?= htmlspecialchars(__('add_ticket_category'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button
                            type="button"
                            x-show="activeView === 'licenses' && canManageAssets"
                            @click="openLicenseModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
                        >
                            <span class="text-lg leading-none">+</span>
                            <?= htmlspecialchars(__('add_license'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button
                            type="button"
                            x-show="activeView === 'consumables' && canManageAssets"
                            @click="openConsumableModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
                        >
                            <span class="text-lg leading-none">+</span>
                            <?= htmlspecialchars(__('add_consumable'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button
                            type="button"
                            x-show="activeView === 'knowledge_base' && canManageAssets"
                            @click="openKnowledgeBaseModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
                        >
                            <span class="text-lg leading-none">+</span>
                            <?= htmlspecialchars(__('kb_add_article'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button
                            type="button"
                            x-show="activeView === 'my_tickets' && isEndUser"
                            @click="openPortalTicketModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
                        >
                            <span class="text-lg leading-none">+</span>
                            <?= htmlspecialchars(__('portal_open_new_ticket'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button
                            type="button"
                            x-show="activeView === 'helpdesk' && canManageAssets"
                            @click="openTicketModal()"
                            class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white shadow-soft transition hover:bg-zinc-800"
                        >
                            <span class="text-lg leading-none">+</span>
                            <?= htmlspecialchars(__('add_ticket'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                </div>
            </header>

            <div class="mx-auto max-w-7xl space-y-8 px-6 py-8">
                <?php if ($isEndUser): ?>
                <?php require __DIR__ . '/partials/end_user_knowledge_base_panel.php'; ?>
                <?php endif; ?>

                <?php if ($canManageAssets): ?>
                    <?php require __DIR__ . '/partials/dashboard_home_panel.php'; ?>
                <?php endif; ?>

                <?php if (!$isEndUser): ?>
                <?php require __DIR__ . '/partials/assets_inventory_panel.php'; ?>
                <?php endif; ?>

                <?php if ($isEndUser): ?>
                <?php require __DIR__ . '/partials/end_user_assets_panel.php'; ?>
                <?php require __DIR__ . '/partials/end_user_tickets_panel.php'; ?>
                <?php require __DIR__ . '/partials/end_user_ticket_modals.php'; ?>
                <?php endif; ?>

                <?php if ($canManageAssets): ?>
                <?php require __DIR__ . '/partials/licenses_panel.php'; ?>
                <?php require __DIR__ . '/partials/consumables_panel.php'; ?>
                <?php require __DIR__ . '/partials/knowledge_base_panel.php'; ?>
                <?php require __DIR__ . '/partials/helpdesk_panel.php'; ?>
                <?php require __DIR__ . '/partials/ipam_panel.php'; ?>
                <?php endif; ?>
                <?php if ($canAccessSettings): ?>
                <?php require __DIR__ . '/partials/admin_reports.php'; ?>
                <?php require __DIR__ . '/partials/audit_logs_panel.php'; ?>
                <?php require __DIR__ . '/partials/settings_panel.php'; ?>
                <?php require __DIR__ . '/partials/categories_panel.php'; ?>
                <?php require __DIR__ . '/partials/locations_panel.php'; ?>
                <?php require __DIR__ . '/partials/ticket_categories_panel.php'; ?>
                <?php endif; ?>
                <?php if ($canAccessPersonnel): ?>
                <?php require __DIR__ . '/partials/personnel_panel.php'; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div
        x-show="isAddOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        @keydown.escape.window="closeAddModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeAddModal()"></div>

        <div class="relative w-full max-w-2xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('modal_add_asset'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('modal_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeAddModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <form @submit.prevent="submitAddForm" class="max-h-[70vh] overflow-y-auto px-6 py-5">
                <div class="grid gap-4 sm:grid-cols-2">
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
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_status'), ENT_QUOTES, 'UTF-8') ?></span>
                        <select x-model="form.status" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                            <option value="ready"><?= htmlspecialchars(__('status_ready'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="deployed"><?= htmlspecialchars(__('status_deployed'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="storage"><?= htmlspecialchars(__('status_storage'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="broken"><?= htmlspecialchars(__('status_broken'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </label>
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
                </div>

                <div class="mt-6 border-t border-zinc-200 pt-5">
                    <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('label_assign_user'), ENT_QUOTES, 'UTF-8') ?></h4>
                    <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('assign_user_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php require __DIR__ . '/partials/user_picker.php'; ?>
                </div>

                <div x-show="addErrorMessage" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="addErrorMessage"></div>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button type="button" @click="closeAddModal()" class="rounded-xl px-4 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span x-show="isSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isSubmitting"><?= htmlspecialchars(__('create_asset'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-show="isEditOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        @keydown.escape.window="closeEditModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeEditModal()"></div>

        <div class="relative w-full max-w-xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('modal_edit_asset'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('modal_edit_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeEditModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <form @submit.prevent="submitEditForm" class="max-h-[70vh] overflow-y-auto px-6 py-5">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_asset_tag'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900" x-text="editAsset?.asset_tag"></p>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_name'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input x-model="editForm.name" type="text" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('col_model'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input x-model="editForm.model" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('col_brand'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input x-model="editForm.brand" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_serial_number'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input x-model="editForm.serial_number" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('col_category'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input x-model="editForm.type" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_status'), ENT_QUOTES, 'UTF-8') ?></span>
                        <select x-model="editForm.status" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                            <option value="ready"><?= htmlspecialchars(__('status_ready'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="deployed"><?= htmlspecialchars(__('status_deployed'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="storage"><?= htmlspecialchars(__('status_storage'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="broken"><?= htmlspecialchars(__('status_broken'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('col_location'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input x-model="editForm.location" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('col_building'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input x-model="editForm.building" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_mac_address_1'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input x-model="editForm.mac_address_1" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 font-mono text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_mac_address_2'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input x-model="editForm.mac_address_2" type="text" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 font-mono text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                </div>

                <div class="mt-6 border-t border-zinc-200 pt-5">
                    <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('label_assign_user'), ENT_QUOTES, 'UTF-8') ?></h4>
                    <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('assign_user_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php require __DIR__ . '/partials/user_picker.php'; ?>
                </div>

                <div x-show="editErrorMessage" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="editErrorMessage"></div>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button type="button" @click="closeEditModal()" class="rounded-xl px-4 py-2.5 text-sm font-medium text-zinc-600 hover:bg-zinc-100"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span x-show="isSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isSubmitting"><?= htmlspecialchars(__('save_changes'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-show="isImportOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        @keydown.escape.window="closeImportModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeImportModal()"></div>

        <div class="relative w-full max-w-2xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('inventory_import_modal_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('inventory_import_modal_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeImportModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <div class="px-6 py-5">
                <a
                    href="/api/inventory/import/template"
                    class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100"
                    download="standart_envanter_import_template.csv"
                >
                    <?= htmlspecialchars(__('import_download_template'), ENT_QUOTES, 'UTF-8') ?>
                </a>

                <p class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-xs leading-relaxed text-zinc-600">
                    <?= htmlspecialchars(__('inventory_import_master_schema_hint'), ENT_QUOTES, 'UTF-8') ?>
                </p>

                <label
                    class="mt-5 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-10 text-center transition-colors"
                    :class="importDragOver ? 'border-zinc-900 bg-zinc-50' : 'border-zinc-200 bg-white hover:border-zinc-300 hover:bg-zinc-50/50'"
                    @dragover.prevent="importDragOver = true"
                    @dragleave.prevent="importDragOver = false"
                    @drop.prevent="onImportFileDropped($event)"
                >
                    <svg class="h-11 w-11 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6"></path>
                    </svg>
                    <span class="mt-4 text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('inventory_import_master_standard_file'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('inventory_import_select_file'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span x-show="importFileName" x-cloak class="mt-3 text-xs font-medium text-emerald-700" x-text="importFileName"></span>
                    <input
                        type="file"
                        accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                        @change="onImportFileSelected($event)"
                        class="sr-only"
                    >
                </label>

                <p x-show="importErrorMessage" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="importErrorMessage"></p>
                <p x-show="importSuccessMessage" x-cloak class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="importSuccessMessage"></p>

                <ul x-show="importResultErrors.length > 0" x-cloak class="mt-3 max-h-36 space-y-1 overflow-y-auto rounded-xl border border-rose-100 bg-rose-50/50 px-4 py-3 text-xs text-rose-700">
                    <template x-for="(item, index) in importResultErrors" :key="index">
                        <li x-text="formatImportError(item)"></li>
                    </template>
                </ul>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-zinc-200 px-6 py-4">
                <button
                    type="button"
                    @click="closeImportModal()"
                    :disabled="isImportSubmitting"
                    class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button
                    type="button"
                    @click="submitImport()"
                    :disabled="isImportSubmitting || !importFile"
                    class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span x-show="isImportSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span x-show="!isImportSubmitting"><?= htmlspecialchars(__('import_submit'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            </div>
        </div>
    </div>

    <div
        x-show="isDetailOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
        @keydown.escape.window="closeDetailModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeDetailModal()"></div>

        <div class="relative w-full max-w-2xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('modal_asset_detail'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('modal_asset_detail_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeDetailModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <div class="max-h-[70vh] overflow-y-auto px-6 py-5">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_asset_tag'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900" x-text="detailAsset?.asset_tag"></p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_name'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-1 text-sm text-zinc-700" x-text="detailAsset?.name"></p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_category'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mt-1 text-sm text-zinc-700" x-text="detailAsset?.category_name"></p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('qr_code_title'), ENT_QUOTES, 'UTF-8') ?></h4>
                            <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('qr_code_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <button
                            type="button"
                            @click="printAssetLabel()"
                            class="inline-flex shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs font-medium text-zinc-700 transition hover:bg-zinc-100"
                        >
                            <?= htmlspecialchars(__('print_label'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                    <div class="mt-4 flex justify-center rounded-xl border border-dashed border-zinc-200 bg-white p-4">
                        <div class="h-36 w-36" x-html="detailQrSvg"></div>
                    </div>
                </div>

                <div class="mt-6">
                    <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('asset_licenses_title'), ENT_QUOTES, 'UTF-8') ?></h4>
                    <p x-show="assetLicensesLoading" x-cloak class="mt-4 text-sm text-zinc-500"><?= htmlspecialchars(__('asset_licenses_loading'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p x-show="assetLicensesError" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="assetLicensesError"></p>
                    <p x-show="!assetLicensesLoading && !assetLicensesError && assetLicenses.length === 0" x-cloak class="mt-4 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-500"><?= htmlspecialchars(__('asset_licenses_empty'), ENT_QUOTES, 'UTF-8') ?></p>
                    <div x-show="!assetLicensesLoading && assetLicenses.length > 0" x-cloak class="mt-4 space-y-3">
                        <template x-for="entry in assetLicenses" :key="entry.id">
                            <article class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900" x-text="entry.license_name"></p>
                                        <p class="mt-1 text-xs text-zinc-500" x-text="entry.license_vendor"></p>
                                    </div>
                                    <span class="text-xs text-zinc-500" x-text="formatLicenseExpiration(entry.license_expiration_date)"></span>
                                </div>
                            </article>
                        </template>
                    </div>
                </div>

                <div class="mt-6">
                    <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('history_title'), ENT_QUOTES, 'UTF-8') ?></h4>

                    <p x-show="historyLoading" x-cloak class="mt-4 text-sm text-zinc-500" x-text="window.__i18n.history_loading"></p>
                    <p x-show="historyError" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="historyError"></p>
                    <p x-show="!historyLoading && !historyError && assetHistory.length === 0" x-cloak class="mt-4 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-500" x-text="window.__i18n.history_empty"></p>

                    <div x-show="!historyLoading && assetHistory.length > 0" x-cloak class="mt-4 space-y-4">
                        <template x-for="entry in assetHistory" :key="entry.id">
                            <article class="relative border-l-2 border-zinc-200 pl-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700" x-text="resolveHistoryAction(entry.action)"></span>
                                    <time class="text-xs text-zinc-400" x-text="formatHistoryDate(entry.created_at)"></time>
                                </div>
                                <p class="mt-2 text-sm text-zinc-700" x-text="entry.notes"></p>
                                <p x-show="entry.target_user_name" class="mt-1 text-xs text-zinc-500">
                                    <span><?= htmlspecialchars(__('col_assigned_user'), ENT_QUOTES, 'UTF-8') ?>:</span>
                                    <span x-text="entry.target_user_name"></span>
                                </p>
                            </article>
                        </template>
                    </div>
                </div>
            </div>

            <div x-show="canManageAssets && detailAsset && !detailAsset.user_id" x-cloak class="flex flex-wrap gap-2 border-t border-zinc-200 px-6 py-4">
                <button
                    type="button"
                    @click="openAssignModal(detailAsset)"
                    class="inline-flex items-center rounded-lg border border-emerald-200 px-3 py-2 text-xs font-medium text-emerald-800 transition hover:bg-emerald-50"
                >
                    <?= htmlspecialchars(__('action_assign'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>

            <div x-show="canManageAssets && detailAsset?.user_id" x-cloak class="flex flex-wrap gap-2 border-t border-zinc-200 px-6 py-4">
                <button
                    type="button"
                    @click="openReturnModal(detailAsset)"
                    class="inline-flex items-center rounded-lg border border-amber-200 px-3 py-2 text-xs font-medium text-amber-800 transition hover:bg-amber-50"
                >
                    <?= htmlspecialchars(__('action_return_to_storage'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button
                    type="button"
                    @click="openTransferModal(detailAsset)"
                    class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-2 text-xs font-medium text-indigo-800 transition hover:bg-indigo-50"
                >
                    <?= htmlspecialchars(__('action_transfer'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </div>
    </div>

    <div
        x-show="isAssignOpen"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center px-4"
        @keydown.escape.window="closeAssignModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeAssignModal()"></div>

        <div class="relative w-full max-w-lg rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('assign_modal_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('assign_modal_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeAssignModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <div class="px-6 py-5">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_asset_tag'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900" x-text="assignAsset?.asset_tag"></p>
                    <p class="mt-2 text-sm text-zinc-600" x-text="assignAsset?.name"></p>
                </div>

                <p class="mt-4 text-sm text-zinc-600"><?= htmlspecialchars(__('assign_select_user_hint'), ENT_QUOTES, 'UTF-8') ?></p>

                <div class="relative mt-3">
                    <div x-show="assignSelectedUser" x-cloak class="mb-3 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-emerald-900" x-text="assignSelectedUser?.name"></p>
                            <p class="text-xs text-emerald-700" x-text="assignSelectedUser?.email"></p>
                        </div>
                        <button type="button" @click="clearAssignUser()" class="text-xs font-medium text-emerald-800 hover:underline">
                            <?= htmlspecialchars(__('unassign_user'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>

                    <input
                        type="text"
                        x-model="assignUserSearchQuery"
                        @input.debounce.300ms="searchAssignUsers()"
                        @focus="showAssignUserResults = true"
                        :placeholder="window.__i18n.search_users_placeholder"
                        class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                    >

                    <div
                        x-show="showAssignUserResults && (assignUserSearchResults.length > 0 || assignUserSearchError || (assignUserSearchQuery !== '' && !assignUserSearchLoading))"
                        x-cloak
                        @click.outside="showAssignUserResults = false"
                        class="absolute z-10 mt-2 max-h-56 w-full overflow-y-auto rounded-xl border border-zinc-200 bg-white shadow-soft"
                    >
                        <template x-for="user in assignUserSearchResults" :key="user.id">
                            <button
                                type="button"
                                @click="selectAssignUser(user)"
                                class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50"
                            >
                                <span class="text-sm font-medium text-zinc-900" x-text="user.name"></span>
                                <span class="text-xs text-zinc-500" x-text="user.email"></span>
                            </button>
                        </template>
                        <p
                            x-show="assignUserSearchError"
                            class="px-4 py-3 text-sm text-rose-600"
                            x-text="assignUserSearchError"
                        ></p>
                        <p
                            x-show="assignUserSearchResults.length === 0 && assignUserSearchQuery !== '' && !assignUserSearchLoading && !assignUserSearchError"
                            class="px-4 py-3 text-sm text-zinc-500"
                            x-text="window.__i18n.no_users_found"
                        ></p>
                    </div>
                </div>

                <label class="mt-4 block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('label_select_location'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select x-model="assignLocationId" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                        <option value=""><?= htmlspecialchars(__('select_location'), ENT_QUOTES, 'UTF-8') ?></option>
                        <template x-for="location in locations" :key="location.id">
                            <option :value="String(location.id)" x-text="formatLocationLabel(location)"></option>
                        </template>
                    </select>
                </label>

                <p x-show="assignErrorMessage" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="assignErrorMessage"></p>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-zinc-200 px-6 py-4">
                <button
                    type="button"
                    @click="closeAssignModal()"
                    :disabled="isAssignSubmitting"
                    class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button
                    type="button"
                    @click="submitAssign()"
                    :disabled="isAssignSubmitting"
                    class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span x-show="isAssignSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span x-show="!isAssignSubmitting"><?= htmlspecialchars(__('assign_submit'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            </div>
        </div>
    </div>

    <div
        x-show="isReturnOpen"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center px-4"
        @keydown.escape.window="closeReturnModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeReturnModal()"></div>

        <div class="relative w-full max-w-lg rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('return_modal_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('return_modal_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeReturnModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <div class="px-6 py-5">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500"><?= htmlspecialchars(__('col_asset_tag'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900" x-text="returnAsset?.asset_tag"></p>
                    <p class="mt-2 text-sm text-zinc-600" x-text="returnAsset?.name"></p>
                    <p class="mt-3 text-sm text-zinc-700">
                        <span class="font-medium"><?= htmlspecialchars(__('col_assigned_user'), ENT_QUOTES, 'UTF-8') ?>:</span>
                        <span x-text="returnAsset?.user_name"></span>
                    </p>
                </div>

                <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <?= htmlspecialchars(__('return_confirm'), ENT_QUOTES, 'UTF-8') ?>
                </p>

                <p x-show="returnErrorMessage" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="returnErrorMessage"></p>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-zinc-200 px-6 py-4">
                <button
                    type="button"
                    @click="closeReturnModal()"
                    :disabled="isReturnSubmitting"
                    class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button
                    type="button"
                    @click="submitReturn()"
                    :disabled="isReturnSubmitting"
                    class="inline-flex min-w-[9rem] items-center justify-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span x-show="isReturnSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span x-show="!isReturnSubmitting"><?= htmlspecialchars(__('return_submit'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            </div>
        </div>
    </div>

    <div
        x-show="isTransferOpen"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center px-4"
        @keydown.escape.window="closeTransferModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeTransferModal()"></div>

        <div class="relative w-full max-w-md rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('transfer_modal_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('transfer_modal_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeTransferModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <div class="px-6 py-5">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_asset_tag'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900" x-text="transferAsset?.asset_tag"></p>
                    <p class="mt-2 text-xs text-zinc-500">
                        <span><?= htmlspecialchars(__('col_assigned_user'), ENT_QUOTES, 'UTF-8') ?>:</span>
                        <span x-text="transferAsset?.user_name"></span>
                    </p>
                </div>

                <p class="mt-4 text-sm text-zinc-600"><?= htmlspecialchars(__('transfer_select_user_hint'), ENT_QUOTES, 'UTF-8') ?></p>

                <div class="relative mt-3">
                    <div x-show="transferSelectedUser" x-cloak class="mb-3 flex items-center justify-between rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-indigo-900" x-text="transferSelectedUser?.name"></p>
                            <p class="text-xs text-indigo-700" x-text="transferSelectedUser?.email"></p>
                        </div>
                        <button type="button" @click="clearTransferUser()" class="text-xs font-medium text-indigo-800 hover:underline">
                            <?= htmlspecialchars(__('unassign_user'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                    <input
                        type="text"
                        x-model="transferUserSearchQuery"
                        @input.debounce.300ms="searchTransferUsers()"
                        @focus="showTransferUserResults = true"
                        :placeholder="window.__i18n.search_users_placeholder"
                        class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                    >
                    <div
                        x-show="showTransferUserResults && (transferUserSearchResults.length > 0 || transferUserSearchError || (transferUserSearchQuery !== '' && !transferUserSearchLoading))"
                        x-cloak
                        @click.outside="showTransferUserResults = false"
                        class="absolute z-10 mt-2 max-h-56 w-full overflow-y-auto rounded-xl border border-zinc-200 bg-white shadow-soft"
                    >
                        <template x-for="user in transferUserSearchResults" :key="user.id">
                            <button
                                type="button"
                                @click="selectTransferUser(user)"
                                class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50"
                            >
                                <span class="text-sm font-medium text-zinc-900" x-text="user.name"></span>
                                <span class="text-xs text-zinc-500" x-text="user.email"></span>
                                <span class="text-xs text-zinc-400" x-text="user.department || ''"></span>
                            </button>
                        </template>
                        <p
                            x-show="transferUserSearchError"
                            class="px-4 py-3 text-sm text-rose-600"
                            x-text="transferUserSearchError"
                        ></p>
                        <p
                            x-show="transferUserSearchResults.length === 0 && transferUserSearchQuery !== '' && !transferUserSearchLoading && !transferUserSearchError"
                            class="px-4 py-3 text-sm text-zinc-500"
                            x-text="window.__i18n.no_users_found"
                        ></p>
                    </div>
                </div>

                <p x-show="transferErrorMessage" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="transferErrorMessage"></p>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-zinc-200 px-6 py-4">
                <button
                    type="button"
                    @click="closeTransferModal()"
                    :disabled="isTransferSubmitting"
                    class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button
                    type="button"
                    @click="submitTransfer()"
                    :disabled="isTransferSubmitting"
                    class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span x-show="isTransferSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span x-show="!isTransferSubmitting"><?= htmlspecialchars(__('transfer_submit'), ENT_QUOTES, 'UTF-8') ?></span>
                </button>
            </div>
        </div>
    </div>

    <div
        x-show="isCategoryModalOpen"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center px-4"
        @keydown.escape.window="closeCategoryModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeCategoryModal()"></div>

        <div class="relative w-full max-w-2xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900" x-text="editingCategoryId ? '<?= htmlspecialchars(__('edit_category'), ENT_QUOTES, 'UTF-8') ?>' : '<?= htmlspecialchars(__('add_category'), ENT_QUOTES, 'UTF-8') ?>'"></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('modal_category_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeCategoryModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <form @submit.prevent="submitCategoryForm" class="max-h-[70vh] overflow-y-auto px-6 py-5">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('category_name_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input
                        type="text"
                        x-model="categoryForm.name"
                        required
                        class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                    >
                </label>

                <div class="mt-6 border-t border-zinc-200 pt-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('category_fields_title'), ENT_QUOTES, 'UTF-8') ?></h4>
                            <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('category_fields_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <button
                            type="button"
                            @click="addCategoryField()"
                            class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-xs font-medium text-zinc-700 transition hover:bg-zinc-50"
                        >
                            <span class="text-base leading-none">+</span>
                            <?= htmlspecialchars(__('category_add_field'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>

                    <p
                        x-show="categoryForm.fields.length === 0"
                        x-cloak
                        class="mt-4 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-500"
                    >
                        <?= htmlspecialchars(__('category_no_fields'), ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <div x-show="categoryForm.fields.length > 0" x-cloak class="mt-4 space-y-3">
                        <template x-for="(field, index) in categoryForm.fields" :key="index">
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                                <div class="grid gap-3 sm:grid-cols-12">
                                    <label class="block sm:col-span-7">
                                        <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('category_field_label'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <input
                                            type="text"
                                            x-model="field.label"
                                            class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400"
                                            placeholder="<?= htmlspecialchars(__('category_field_label_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
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
                                            <option value="dropdown"><?= htmlspecialchars(__('settings_field_type_dropdown'), ENT_QUOTES, 'UTF-8') ?></option>
                                        </select>
                                    </label>
                                    <div class="flex items-end justify-end sm:col-span-2">
                                        <button
                                            type="button"
                                            @click="removeCategoryField(index)"
                                            class="rounded-lg px-3 py-2 text-xs font-medium text-rose-600 transition hover:bg-rose-50"
                                        >
                                            <?= htmlspecialchars(__('category_remove_field'), ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                    </div>
                                </div>
                                <label x-show="field.type === 'dropdown'" x-cloak class="mt-3 block">
                                    <span class="mb-1 block text-xs font-medium text-zinc-600"><?= htmlspecialchars(__('category_field_options'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <input
                                        type="text"
                                        x-model="field.optionsText"
                                        class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-zinc-400"
                                        placeholder="<?= htmlspecialchars(__('category_field_options_hint'), ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                    <p class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars(__('category_field_options_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                                </label>
                            </div>
                        </template>
                    </div>
                </div>

                <p x-show="categoryFormError" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="categoryFormError"></p>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button
                        type="button"
                        @click="closeCategoryModal()"
                        :disabled="isCategorySubmitting"
                        class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button
                        type="submit"
                        :disabled="isCategorySubmitting"
                        class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span x-show="isCategorySubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isCategorySubmitting"><?= htmlspecialchars(__('category_save'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-show="isLocationModalOpen"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center px-4"
        @keydown.escape.window="closeLocationModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeLocationModal()"></div>

        <div class="relative w-full max-w-xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900" x-text="locationForm.id ? '<?= htmlspecialchars(__('edit_location'), ENT_QUOTES, 'UTF-8') ?>' : '<?= htmlspecialchars(__('add_location'), ENT_QUOTES, 'UTF-8') ?>'"></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('modal_location_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeLocationModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <form @submit.prevent="submitLocationForm" class="px-6 py-5">
                <div class="grid gap-4">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('location_name_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input
                            type="text"
                            x-model="locationForm.name"
                            required
                            class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                        >
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('location_building_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input
                            type="text"
                            x-model="locationForm.building"
                            class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                        >
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('location_description_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <textarea
                            x-model="locationForm.description"
                            rows="3"
                            class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                        ></textarea>
                    </label>
                </div>

                <p x-show="locationFormError" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="locationFormError"></p>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button
                        type="button"
                        @click="closeLocationModal()"
                        :disabled="isLocationSubmitting"
                        class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button
                        type="submit"
                        :disabled="isLocationSubmitting"
                        class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span x-show="isLocationSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isLocationSubmitting"><?= htmlspecialchars(__('category_save'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-show="isTicketCategoryModalOpen"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center px-4"
        @keydown.escape.window="closeTicketCategoryModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeTicketCategoryModal()"></div>

        <div class="relative w-full max-w-xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900" x-text="ticketCategoryForm.id ? '<?= htmlspecialchars(__('edit_ticket_category'), ENT_QUOTES, 'UTF-8') ?>' : '<?= htmlspecialchars(__('add_ticket_category'), ENT_QUOTES, 'UTF-8') ?>'"></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('ticket_categories_page_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeTicketCategoryModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <form @submit.prevent="submitTicketCategoryForm" class="px-6 py-5">
                <div class="grid gap-4">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_category_name_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input type="text" x-model="ticketCategoryForm.name" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_category_color_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <div class="flex items-center gap-3">
                            <input type="color" x-model="ticketCategoryForm.color_code" class="h-11 w-16 cursor-pointer rounded-lg border border-zinc-300 bg-white p-1">
                            <input type="text" x-model="ticketCategoryForm.color_code" pattern="^#[0-9A-Fa-f]{6}$" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                        </div>
                    </label>
                </div>

                <p x-show="ticketCategoryFormError" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="ticketCategoryFormError"></p>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button type="button" @click="closeTicketCategoryModal()" :disabled="isTicketCategorySubmitting" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="submit" :disabled="isTicketCategorySubmitting" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="isTicketCategorySubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isTicketCategorySubmitting"><?= htmlspecialchars(__('ticket_category_save'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-show="isLicenseModalOpen"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center px-4"
        @keydown.escape.window="closeLicenseModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeLicenseModal()"></div>

        <div class="relative w-full max-w-xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('add_license'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('modal_license_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeLicenseModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <form @submit.prevent="submitLicenseForm" class="px-6 py-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('license_name_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input type="text" x-model="licenseForm.name" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('license_vendor_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input type="text" x-model="licenseForm.vendor" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('license_seats_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input type="number" min="1" x-model="licenseForm.seats" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('license_expiration_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input type="date" x-model="licenseForm.expiration_date" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('license_key_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input type="text" x-model="licenseForm.license_key" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('license_notes_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <textarea x-model="licenseForm.notes" rows="3" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"></textarea>
                    </label>
                </div>

                <p x-show="licenseFormError" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="licenseFormError"></p>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button type="button" @click="closeLicenseModal()" :disabled="isLicenseSubmitting" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="submit" :disabled="isLicenseSubmitting" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="isLicenseSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isLicenseSubmitting"><?= htmlspecialchars(__('category_save'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-show="isAssignLicenseModalOpen"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center px-4"
        @keydown.escape.window="closeAssignLicenseModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeAssignLicenseModal()"></div>

        <div class="relative w-full max-w-xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('assign_license'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('modal_assign_license_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeAssignLicenseModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>

            <div class="max-h-[75vh] overflow-y-auto px-6 py-5">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('col_license_name'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select x-model="assignLicenseForm.license_id" @change="loadLicenseAssignments()" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                        <option value=""><?= htmlspecialchars(__('license_select_license_placeholder'), ENT_QUOTES, 'UTF-8') ?></option>
                        <template x-for="license in licenses" :key="license.id">
                            <option :value="license.id" x-text="`${license.vendor} — ${license.name} (${license.remaining_seats} boş)`"></option>
                        </template>
                    </select>
                </label>

                <fieldset class="mt-4">
                    <legend class="mb-2 text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('license_assign_type_label'), ENT_QUOTES, 'UTF-8') ?></legend>
                    <div class="flex flex-wrap gap-3">
                        <label class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 px-3 py-2 text-sm">
                            <input type="radio" value="asset" x-model="assignLicenseForm.assign_type">
                            <?= htmlspecialchars(__('license_assign_type_asset'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 px-3 py-2 text-sm">
                            <input type="radio" value="personnel" x-model="assignLicenseForm.assign_type">
                            <?= htmlspecialchars(__('license_assign_type_user'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                    </div>
                </fieldset>

                <div x-show="assignLicenseForm.assign_type === 'asset'" x-cloak class="mt-4">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('license_select_asset'), ENT_QUOTES, 'UTF-8') ?></span>
                        <select x-model="assignLicenseForm.asset_id" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                            <option value=""><?= htmlspecialchars(__('license_select_asset_placeholder'), ENT_QUOTES, 'UTF-8') ?></option>
                            <template x-for="asset in assetOptions" :key="asset.id">
                                <option :value="asset.id" x-text="`${asset.asset_tag} — ${asset.name}`"></option>
                            </template>
                        </select>
                    </label>
                </div>

                <div x-show="assignLicenseForm.assign_type === 'personnel'" x-cloak class="mt-4">
                    <p class="text-sm text-zinc-600"><?= htmlspecialchars(__('license_select_user_hint'), ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="relative mt-3">
                        <div x-show="assignLicenseSelectedPersonnel" x-cloak class="mb-3 flex items-center justify-between rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-indigo-900" x-text="assignLicenseSelectedPersonnel?.name"></p>
                                <p class="text-xs text-indigo-700" x-text="assignLicenseSelectedPersonnel?.email"></p>
                            </div>
                            <button type="button" @click="clearAssignLicensePersonnel()" class="text-xs font-medium text-indigo-700 hover:text-indigo-900"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                        </div>
                        <input
                            type="text"
                            x-model="assignLicensePersonnelSearchQuery"
                            @input.debounce.300ms="searchAssignLicensePersonnel()"
                            @focus="showAssignLicensePersonnelResults = true"
                            :placeholder="window.__i18n.search_users_placeholder"
                            class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                        >
                        <div x-show="showAssignLicensePersonnelResults && (assignLicensePersonnelSearchResults.length > 0 || assignLicensePersonnelSearchError || (assignLicensePersonnelSearchQuery !== '' && !assignLicensePersonnelSearchLoading))" x-cloak @click.outside="showAssignLicensePersonnelResults = false" class="absolute z-10 mt-2 w-full overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-soft">
                            <template x-for="personnel in assignLicensePersonnelSearchResults" :key="personnel.id">
                                <button type="button" @click="selectAssignLicensePersonnel(personnel)" class="block w-full px-4 py-3 text-left hover:bg-zinc-50">
                                    <p class="text-sm font-medium text-zinc-900" x-text="personnel.name"></p>
                                    <p class="text-xs text-zinc-500" x-text="personnel.email"></p>
                                </button>
                            </template>
                            <p x-show="assignLicensePersonnelSearchError" class="px-4 py-3 text-sm text-rose-600" x-text="assignLicensePersonnelSearchError"></p>
                            <p x-show="assignLicensePersonnelSearchResults.length === 0 && assignLicensePersonnelSearchQuery !== '' && !assignLicensePersonnelSearchLoading && !assignLicensePersonnelSearchError" class="px-4 py-3 text-sm text-zinc-500" x-text="window.__i18n.no_users_found"></p>
                        </div>
                        <p x-show="assignLicensePersonnelSearchLoading" x-cloak class="mt-2 text-xs text-zinc-500"><?= htmlspecialchars(__('history_loading'), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>

                <div class="mt-6">
                    <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('license_current_assignments'), ENT_QUOTES, 'UTF-8') ?></h4>
                    <p x-show="assignLicenseAssignmentsLoading" x-cloak class="mt-3 text-sm text-zinc-500"><?= htmlspecialchars(__('licenses_loading'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p x-show="!assignLicenseAssignmentsLoading && assignLicenseAssignments.length === 0" x-cloak class="mt-3 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-500"><?= htmlspecialchars(__('license_no_assignments'), ENT_QUOTES, 'UTF-8') ?></p>
                    <div x-show="!assignLicenseAssignmentsLoading && assignLicenseAssignments.length > 0" x-cloak class="mt-3 space-y-2">
                        <template x-for="assignment in assignLicenseAssignments" :key="assignment.id">
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                                <div>
                                    <p class="text-sm text-zinc-800" x-text="formatLicenseAssignmentTarget(assignment)"></p>
                                    <p class="text-xs text-zinc-500" x-text="formatHistoryDate(assignment.assigned_at)"></p>
                                </div>
                                <button type="button" @click="unassignLicenseSeat(assignment)" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-50"><?= htmlspecialchars(__('action_unassign_license'), ENT_QUOTES, 'UTF-8') ?></button>
                            </div>
                        </template>
                    </div>
                </div>

                <p x-show="assignLicenseFormError" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="assignLicenseFormError"></p>
                <p x-show="assignLicenseSuccessMessage" x-cloak class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" x-text="assignLicenseSuccessMessage"></p>

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button type="button" @click="closeAssignLicenseModal()" :disabled="isAssignLicenseSubmitting" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="button" @click="submitAssignLicenseForm()" :disabled="isAssignLicenseSubmitting" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="isAssignLicenseSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isAssignLicenseSubmitting"><?= htmlspecialchars(__('assign_license'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div
        x-show="isConsumableModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeConsumableModal()"></div>
        <div class="relative w-full max-w-lg rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h3 class="text-lg font-semibold text-zinc-900" x-text="consumableForm.id ? '<?= htmlspecialchars(__('edit_consumable'), ENT_QUOTES, 'UTF-8') ?>' : '<?= htmlspecialchars(__('add_consumable'), ENT_QUOTES, 'UTF-8') ?>'"></h3>
            <form class="mt-5 space-y-4" @submit.prevent="submitConsumableForm()">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('consumable_name_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="consumableForm.name" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('consumable_quantity_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input type="number" min="0" x-model="consumableForm.quantity" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('consumable_min_stock_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input type="number" min="0" x-model="consumableForm.min_stock_level" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                </div>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('consumable_location_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select x-model="consumableForm.location_id" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                        <option value=""><?= htmlspecialchars(__('consumable_location_none'), ENT_QUOTES, 'UTF-8') ?></option>
                        <template x-for="location in locations" :key="location.id">
                            <option :value="location.id" x-text="formatLocationLabel(location)"></option>
                        </template>
                    </select>
                </label>
                <p x-show="consumableFormError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="consumableFormError"></p>
                <div class="flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button type="button" @click="closeConsumableModal()" :disabled="isConsumableSubmitting" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="submit" :disabled="isConsumableSubmitting" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="isConsumableSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isConsumableSubmitting"><?= htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-show="isKnowledgeBaseModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeKnowledgeBaseModal()"></div>
        <div class="relative w-full max-w-2xl rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h3 class="text-lg font-semibold text-zinc-900" x-text="knowledgeBaseForm.id ? '<?= htmlspecialchars(__('kb_edit_article'), ENT_QUOTES, 'UTF-8') ?>' : '<?= htmlspecialchars(__('kb_add_article'), ENT_QUOTES, 'UTF-8') ?>'"></h3>
            <form class="mt-5 space-y-4" @submit.prevent="submitKnowledgeBaseForm()">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('kb_title_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="text" x-model="knowledgeBaseForm.title" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('kb_content_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <textarea x-model="knowledgeBaseForm.content" required rows="8" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"></textarea>
                </label>
                <label class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                    <input type="checkbox" x-model="knowledgeBaseForm.is_published" class="mt-1 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900">
                    <span>
                        <span class="block text-sm font-medium text-zinc-900"><?= htmlspecialchars(__('kb_published_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="mt-1 block text-xs text-zinc-500"><?= htmlspecialchars(__('kb_published_hint'), ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                </label>
                <p x-show="knowledgeBaseFormError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="knowledgeBaseFormError"></p>
                <div class="flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button type="button" @click="closeKnowledgeBaseModal()" :disabled="isKnowledgeBaseSubmitting" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="submit" :disabled="isKnowledgeBaseSubmitting" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="isKnowledgeBaseSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isKnowledgeBaseSubmitting"><?= htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-show="isConsumableAdjustModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center px-4"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeConsumableAdjustModal()"></div>
        <div class="relative w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 shadow-soft">
            <h3 class="text-lg font-semibold text-zinc-900" x-text="consumableAdjustMode === 'checkout' ? '<?= htmlspecialchars(__('consumable_checkout_title'), ENT_QUOTES, 'UTF-8') ?>' : '<?= htmlspecialchars(__('consumable_restock_title'), ENT_QUOTES, 'UTF-8') ?>'"></h3>
            <p class="mt-1 text-sm text-zinc-500" x-text="consumableAdjustTarget?.name || ''"></p>
            <form class="mt-5 space-y-4" @submit.prevent="submitConsumableAdjustForm()">
                <label class="block">
                    <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('consumable_adjust_quantity_label'), ENT_QUOTES, 'UTF-8') ?></span>
                    <input type="number" min="1" x-model="consumableAdjustQuantity" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                </label>
                <p x-show="consumableAdjustError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="consumableAdjustError"></p>
                <div class="flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button type="button" @click="closeConsumableAdjustModal()" :disabled="isConsumableAdjustSubmitting" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="submit" :disabled="isConsumableAdjustSubmitting" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="isConsumableAdjustSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isConsumableAdjustSubmitting"><?= htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-show="isTicketModalOpen"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center px-4"
        @keydown.escape.window="closeTicketModal()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeTicketModal()"></div>
        <div class="relative w-full max-w-2xl rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900"><?= htmlspecialchars(__('add_ticket'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('modal_ticket_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeTicketModal()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>
            <form @submit.prevent="submitTicketForm()" class="px-6 py-5">
                <div class="grid gap-4">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_subject_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <input type="text" x-model="ticketForm.subject" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_description_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <textarea x-model="ticketForm.description" rows="4" required class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"></textarea>
                    </label>
                    <div>
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_requester_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <div class="relative">
                            <div x-show="ticketSelectedPersonnel" x-cloak class="mb-3 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                <div>
                                    <p class="text-sm font-medium text-emerald-900" x-text="ticketSelectedPersonnel?.name"></p>
                                    <p class="text-xs text-emerald-700" x-text="ticketSelectedPersonnel?.email"></p>
                                </div>
                                <button type="button" @click="clearTicketSelectedPersonnel()" class="text-xs font-medium text-emerald-800 hover:underline"><?= htmlspecialchars(__('unassign_user'), ENT_QUOTES, 'UTF-8') ?></button>
                            </div>
                            <input
                                type="text"
                                x-model="ticketUserSearchQuery"
                                @input.debounce.300ms="searchTicketPersonnel()"
                                @focus="showTicketUserResults = true"
                                :placeholder="window.__i18n.search_users_placeholder"
                                class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"
                            >
                            <div
                                x-show="showTicketUserResults && (ticketUserSearchResults.length > 0 || ticketUserSearchError || (ticketUserSearchQuery !== '' && !ticketUserSearchLoading))"
                                x-cloak
                                @click.outside="showTicketUserResults = false"
                                class="absolute z-10 mt-2 max-h-56 w-full overflow-y-auto rounded-xl border border-zinc-200 bg-white shadow-soft"
                            >
                                <template x-for="user in ticketUserSearchResults" :key="user.id">
                                    <button type="button" @click="selectTicketPersonnel(user)" class="flex w-full flex-col items-start px-4 py-3 text-left hover:bg-zinc-50">
                                        <span class="text-sm font-medium text-zinc-900" x-text="user.name"></span>
                                        <span class="text-xs text-zinc-500" x-text="user.email"></span>
                                    </button>
                                </template>
                                <p x-show="ticketUserSearchError" class="px-4 py-3 text-sm text-rose-600" x-text="ticketUserSearchError"></p>
                                <p x-show="ticketUserSearchResults.length === 0 && ticketUserSearchQuery !== '' && !ticketUserSearchLoading && !ticketUserSearchError" class="px-4 py-3 text-sm text-zinc-500" x-text="window.__i18n.no_users_found"></p>
                            </div>
                        </div>
                    </div>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_asset_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <select x-model="ticketForm.asset_id" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                            <option value="">—</option>
                            <template x-for="asset in assetOptions" :key="asset.id">
                                <option :value="asset.id" x-text="`${asset.asset_tag} — ${asset.name}`"></option>
                            </template>
                        </select>
                    </label>
                    <label class="block sm:max-w-xs">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_priority_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <select x-model="ticketForm.priority" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                            <option value="low"><?= htmlspecialchars(__('ticket_priority_low'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="medium"><?= htmlspecialchars(__('ticket_priority_medium'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="high"><?= htmlspecialchars(__('ticket_priority_high'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="critical"><?= htmlspecialchars(__('ticket_priority_critical'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </label>
                </div>
                <p x-show="ticketFormError" x-cloak class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="ticketFormError"></p>
                <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-200 pt-5">
                    <button type="button" @click="closeTicketModal()" :disabled="isTicketSubmitting" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="submit" :disabled="isTicketSubmitting" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="isTicketSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span x-show="!isTicketSubmitting"><?= htmlspecialchars(__('add_ticket'), ENT_QUOTES, 'UTF-8') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-show="isTicketDetailOpen"
        x-cloak
        class="fixed inset-0 z-[60] flex items-center justify-center px-4"
        @keydown.escape.window="closeTicketDetail()"
    >
        <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" @click="closeTicketDetail()"></div>
        <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl border border-zinc-200 bg-white shadow-soft">
            <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-400" x-text="ticketDetail?.ticket_number"></p>
                    <h3 class="mt-1 text-lg font-semibold text-zinc-900" x-text="ticketDetail?.subject"></h3>
                    <p class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars(__('modal_ticket_detail_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <button type="button" @click="closeTicketDetail()" class="rounded-lg p-2 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600">&times;</button>
            </div>
            <div class="overflow-y-auto px-6 py-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                        <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_ticket_requester'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-1 text-sm font-medium text-zinc-900" x-text="ticketDetail?.personnel_name"></p>
                        <p class="text-xs text-zinc-500" x-text="ticketDetail?.personnel_email"></p>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3" x-show="!ticketDetail?.asset_id">
                        <p class="text-xs uppercase tracking-wide text-zinc-400"><?= htmlspecialchars(__('col_ticket_asset'), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-1 text-sm text-zinc-700">—</p>
                    </div>
                </div>
                <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-4" x-show="ticketDetail?.asset_id" x-cloak>
                    <p class="text-xs font-semibold uppercase tracking-wide text-sky-700"><?= htmlspecialchars(__('ticket_linked_asset_title'), ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-base font-semibold text-zinc-900" x-text="ticketDetail?.asset_name"></p>
                            <p class="mt-1 text-sm font-medium tabular-nums text-sky-800" x-text="ticketDetail?.asset_tag"></p>
                        </div>
                        <button
                            type="button"
                            @click="openTicketLinkedAsset()"
                            class="shrink-0 rounded-lg border border-sky-300 bg-white px-3 py-1.5 text-xs font-medium text-sky-900 transition hover:bg-sky-100"
                        ><?= htmlspecialchars(__('action_view_linked_asset'), ENT_QUOTES, 'UTF-8') ?></button>
                    </div>
                </div>
                <p class="mt-4 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-700" x-text="ticketDetail?.description"></p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_category_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <select x-model="ticketDetailForm.category_id" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                            <option value=""><?= htmlspecialchars(__('ticket_category_none'), ENT_QUOTES, 'UTF-8') ?></option>
                            <template x-for="category in ticketCategories" :key="category.id">
                                <option :value="String(category.id)" x-text="category.name"></option>
                            </template>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_status_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <select x-model="ticketDetailForm.status" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                            <option value="open"><?= htmlspecialchars(__('ticket_status_open'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="in_progress"><?= htmlspecialchars(__('ticket_status_in_progress'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="resolved"><?= htmlspecialchars(__('ticket_status_resolved'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="closed"><?= htmlspecialchars(__('ticket_status_closed'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_priority_label'), ENT_QUOTES, 'UTF-8') ?></span>
                        <select x-model="ticketDetailForm.priority" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4">
                            <option value="low"><?= htmlspecialchars(__('ticket_priority_low'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="medium"><?= htmlspecialchars(__('ticket_priority_medium'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="high"><?= htmlspecialchars(__('ticket_priority_high'), ENT_QUOTES, 'UTF-8') ?></option>
                            <option value="critical"><?= htmlspecialchars(__('ticket_priority_critical'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                    </label>
                </div>
                <div class="mt-6">
                    <h4 class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars(__('ticket_comments_title'), ENT_QUOTES, 'UTF-8') ?></h4>
                    <p x-show="ticketDetailLoading" x-cloak class="mt-4 text-sm text-zinc-500"><?= htmlspecialchars(__('helpdesk_loading'), ENT_QUOTES, 'UTF-8') ?></p>
                    <p x-show="!ticketDetailLoading && ticketComments.length === 0" x-cloak class="mt-4 rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-500"><?= htmlspecialchars(__('ticket_no_comments'), ENT_QUOTES, 'UTF-8') ?></p>
                    <div x-show="!ticketDetailLoading && ticketComments.length > 0" x-cloak class="mt-4 space-y-3">
                        <template x-for="comment in ticketComments" :key="comment.id">
                            <article class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-zinc-900" x-text="comment.author_name"></span>
                                    <time class="text-xs text-zinc-400" x-text="formatTicketDate(comment.created_at)"></time>
                                </div>
                                <p class="mt-2 whitespace-pre-wrap text-sm text-zinc-700" x-text="comment.body"></p>
                            </article>
                        </template>
                    </div>
                    <form class="mt-4 space-y-3" @submit.prevent="submitTicketComment()">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-zinc-700"><?= htmlspecialchars(__('ticket_comment_label'), ENT_QUOTES, 'UTF-8') ?></span>
                            <textarea x-model="ticketCommentBody" rows="3" class="w-full rounded-xl border border-zinc-300 px-3 py-2.5 text-sm outline-none ring-zinc-900/10 focus:border-zinc-400 focus:ring-4"></textarea>
                        </label>
                        <p x-show="ticketCommentError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="ticketCommentError"></p>
                        <button type="submit" :disabled="isTicketCommentSubmitting" class="inline-flex items-center gap-2 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60">
                            <span x-show="isTicketCommentSubmitting"><?= htmlspecialchars(__('saving'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span x-show="!isTicketCommentSubmitting"><?= htmlspecialchars(__('action_add_ticket_reply'), ENT_QUOTES, 'UTF-8') ?></span>
                        </button>
                    </form>
                </div>
            </div>
            <div class="flex items-center justify-between gap-3 border-t border-zinc-200 px-6 py-4">
                <button type="button" @click="deleteTicket()" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-medium text-rose-700 hover:bg-rose-100"><?= htmlspecialchars(__('action_delete_ticket'), ENT_QUOTES, 'UTF-8') ?></button>
                <div class="flex items-center gap-3">
                    <button type="button" @click="closeTicketDetail()" class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50"><?= htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="button" @click="updateTicketDetail()" :disabled="isTicketDetailSubmitting" class="rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"><?= htmlspecialchars(__('save_changes'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    #zimmet-quill-wrapper .ql-toolbar.ql-snow {
        border: 0;
        border-bottom: 1px solid #e4e4e7;
        background: #fafafa;
    }

    #zimmet-quill-wrapper .ql-container.ql-snow {
        border: 0;
        font-family: Inter, ui-sans-serif, system-ui, sans-serif;
    }

    #zimmet-quill-wrapper .ql-editor {
        min-height: 240px;
        line-height: 1.6;
    }
</style>

<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<script>
    window.__i18n = <?= $i18nScript ?>;
    window.__categoryFields = <?= $categoryFieldsJson ?>;
    window.__assetQrCodes = <?= $assetQrCodesJson ?>;
    window.__analytics = <?= $analyticsJson ?>;
    window.__settings = <?= $settingsJson ?>;
    window.__globalCustomFields = <?= $globalCustomFieldsJson ?>;
    window.__personnel = <?= $personnelJson ?? '[]' ?>;
    window.__assetOptions = <?= $assetOptionsJson ?? '[]' ?>;
    window.__portalStatusStyles = <?= json_encode($statusStyles, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;

    function assetDashboard() {
        return {
            activeView: <?= $isEndUser ? "'knowledge_base'" : ($canManageAssets ? "'dashboard'" : "'assets'") ?>,
            dashboardStats: null,
            dashboardLoading: false,
            dashboardError: '',
            categoryChart: null,
            userRole: <?= json_encode($userRole, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
            isEndUser: <?= $isEndUser ? 'true' : 'false' ?>,
            canManageAssets: <?= $canManageAssets ? 'true' : 'false' ?>,
            canAccessSettings: <?= $canAccessSettings ? 'true' : 'false' ?>,
            canAccessPersonnel: <?= $canAccessPersonnel ? 'true' : 'false' ?>,
            currentUserId: <?= (int) ($currentUserId ?? 0) ?>,
            currentUserEmail: <?= json_encode($currentUserEmail ?? '', JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
            isSuperAdmin: <?= $isSuperAdmin ? 'true' : 'false' ?>,
            settingsTab: 'general',
            pageTitles: {
                dashboard: <?= json_encode(__('dashboard_page_title'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                assets: <?= json_encode(__('nav_assets'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                my_assets: <?= json_encode(__('portal_tab_assets'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                my_tickets: <?= json_encode(__('portal_tab_tickets'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                licenses: <?= json_encode(__('licenses_page_title'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                consumables: <?= json_encode(__('consumables_page_title'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                knowledge_base: <?= json_encode(__('portal_knowledge_base_title'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                helpdesk: <?= json_encode(__('helpdesk_page_title'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                reports: <?= json_encode(__('reports_page_title'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                ipam: <?= json_encode(__('ipam_page_title'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                settings: <?= json_encode(__('settings_page_title'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                categories: <?= json_encode(__('categories_page_title'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                locations: <?= json_encode(__('locations_page_title'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                ticket_categories: <?= json_encode(__('ticket_categories_page_title'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                personnel: <?= json_encode(__('personnel_page_title'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                smtp: <?= json_encode(__('settings_tab_smtp'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                backup: <?= json_encode(__('settings_tab_backup'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                audit_logs: <?= json_encode(__('audit_logs_page_title'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
            },
            pageSubtitles: {
                dashboard: <?= json_encode(__('dashboard_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                assets: <?= json_encode(__('page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                my_assets: <?= json_encode(__('portal_my_assets_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                my_tickets: <?= json_encode(__('portal_my_tickets_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                licenses: <?= json_encode(__('licenses_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                consumables: <?= json_encode(__('consumables_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                knowledge_base: <?= json_encode(__('kb_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                helpdesk: <?= json_encode(__('helpdesk_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                reports: <?= json_encode(__('reports_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                ipam: <?= json_encode(__('ipam_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                settings: <?= json_encode(__('settings_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                categories: <?= json_encode(__('categories_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                locations: <?= json_encode(__('locations_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                ticket_categories: <?= json_encode(__('ticket_categories_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                personnel: <?= json_encode(__('personnel_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                smtp: <?= json_encode(__('settings_smtp_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                backup: <?= json_encode(__('settings_backup_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
                audit_logs: <?= json_encode(__('audit_logs_page_subtitle'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
            },
            isAddOpen: false,
            isImportOpen: false,
            isImportSubmitting: false,
            importDragOver: false,
            importFile: null,
            importFileName: '',
            importErrorMessage: '',
            importSuccessMessage: '',
            importResultErrors: [],
            importSummaryMessage: '',
            importSummaryIsError: false,
            importSummaryErrors: [],
            csrfToken: <?= json_encode($csrfToken ?? '', JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
            inventoryAssets: <?= $inventoryAssetsJson ?>,
            assetFilterFields: <?= $assetFilterFieldsJson ?>,
            assetFilters: <?= $initialAssetFiltersJson ?>,
            assetFiltersLoading: false,
            assetFiltersError: '',
            inventoryPage: <?= (int) ($assetPagination['page'] ?? 1) ?>,
            inventoryPagination: <?= $assetPaginationJson ?>,
            isEditOpen: false,
            isDetailOpen: false,
            isTransferOpen: false,
            isTransferSubmitting: false,
            isAssignOpen: false,
            isAssignSubmitting: false,
            assignAsset: null,
            assignSelectedUser: null,
            assignUserSearchQuery: '',
            assignUserSearchResults: [],
            assignUserSearchLoading: false,
            showAssignUserResults: false,
            assignUserSearchError: '',
            assignLocationId: '',
            assignErrorMessage: '',
            isReturnOpen: false,
            isReturnSubmitting: false,
            returnAsset: null,
            returnErrorMessage: '',
            transferAsset: null,
            transferSelectedUser: null,
            transferUserSearchQuery: '',
            transferUserSearchResults: [],
            transferUserSearchLoading: false,
            showTransferUserResults: false,
            transferUserSearchError: '',
            transferErrorMessage: '',
            categories: [],
            categoriesLoading: false,
            categoriesError: '',
            categoriesSuccessMessage: '',
            isCategoryModalOpen: false,
            isCategorySubmitting: false,
            editingCategoryId: null,
            categoryForm: {
                id: null,
                name: '',
                fields: [],
            },
            categoryFormError: '',
            locations: [],
            locationsLoading: false,
            locationsError: '',
            locationsSuccessMessage: '',
            isLocationModalOpen: false,
            isLocationSubmitting: false,
            locationForm: {
                id: null,
                name: '',
                building: '',
                description: '',
            },
            locationFormError: '',
            ticketCategories: [],
            ticketCategoriesLoading: false,
            ticketCategoriesError: '',
            ticketCategoriesSuccessMessage: '',
            isTicketCategoryModalOpen: false,
            isTicketCategorySubmitting: false,
            ticketCategoryForm: {
                id: null,
                name: '',
                color_code: '#6366f1',
            },
            ticketCategoryFormError: '',
            reportsStats: null,
            reportsLoading: false,
            reportsError: '',
            licenses: [],
            licensesLoading: false,
            licensesError: '',
            licensesSuccessMessage: '',
            licenseFilterFields: <?= $licenseFilterFieldsJson ?>,
            licenseFilters: <?= $initialLicenseFiltersJson ?>,
            licensesPage: 1,
            licensesPagination: { page: 1, per_page: 50, total: 0, total_pages: 1 },
            isLicenseModalOpen: false,
            isLicenseSubmitting: false,
            licenseForm: {
                name: '',
                vendor: '',
                license_key: '',
                seats: 1,
                expiration_date: '',
                notes: '',
            },
            licenseFormError: '',
            isAssignLicenseModalOpen: false,
            isAssignLicenseSubmitting: false,
            assignLicenseForm: {
                license_id: '',
                assign_type: 'asset',
                asset_id: '',
            },
            assignLicenseFormError: '',
            assignLicenseSuccessMessage: '',
            assignLicenseSelectedPersonnel: null,
            assignLicensePersonnelSearchQuery: '',
            assignLicensePersonnelSearchResults: [],
            assignLicensePersonnelSearchLoading: false,
            assignLicensePersonnelSearchError: '',
            showAssignLicensePersonnelResults: false,
            assignLicenseAssignments: [],
            assignLicenseAssignmentsLoading: false,
            consumables: [],
            consumablesLoading: false,
            consumablesError: '',
            consumablesSuccessMessage: '',
            consumableFilterFields: <?= $consumableFilterFieldsJson ?>,
            consumableFilters: <?= $initialConsumableFiltersJson ?>,
            consumablesPage: 1,
            consumablesPagination: { page: 1, per_page: 50, total: 0, total_pages: 1 },
            isConsumableModalOpen: false,
            isConsumableSubmitting: false,
            consumableForm: {
                id: null,
                name: '',
                quantity: 0,
                min_stock_level: 0,
                location_id: '',
            },
            consumableFormError: '',
            isConsumableAdjustModalOpen: false,
            isConsumableAdjustSubmitting: false,
            consumableAdjustMode: 'checkout',
            consumableAdjustTarget: null,
            consumableAdjustQuantity: 1,
            consumableAdjustError: '',
            knowledgeBaseArticles: [],
            knowledgeBaseLoading: false,
            knowledgeBaseError: '',
            knowledgeBaseSuccessMessage: '',
            isKnowledgeBaseModalOpen: false,
            isKnowledgeBaseSubmitting: false,
            knowledgeBaseForm: {
                id: null,
                title: '',
                content: '',
                is_published: false,
            },
            knowledgeBaseFormError: '',
            publishedKnowledgeBase: [],
            publishedKnowledgeBaseSearchQuery: '',
            publishedKnowledgeBaseLoading: false,
            publishedKnowledgeBaseError: '',
            tickets: [],
            ticketsLoading: false,
            ticketsError: '',
            ticketsSuccessMessage: '',
            ticketsPage: 1,
            ticketsPagination: { page: 1, per_page: 50, total: 0, total_pages: 1 },
            ticketLayout: 'table',
            ticketStatusFilter: 'all',
            ticketStatusFilters: [
                { value: 'all', label: window.__i18n.helpdesk_filter_all },
                { value: 'open', label: window.__i18n.ticket_status_open },
                { value: 'in_progress', label: window.__i18n.ticket_status_in_progress },
                { value: 'resolved', label: window.__i18n.ticket_status_resolved },
                { value: 'closed', label: window.__i18n.ticket_status_closed },
            ],
            ticketBoardColumns: [
                { status: 'open', label: window.__i18n.ticket_status_open },
                { status: 'in_progress', label: window.__i18n.ticket_status_in_progress },
                { status: 'resolved', label: window.__i18n.ticket_status_resolved },
                { status: 'closed', label: window.__i18n.ticket_status_closed },
            ],
            isTicketModalOpen: false,
            isTicketSubmitting: false,
            ticketForm: {
                subject: '',
                description: '',
                asset_id: '',
                priority: 'medium',
            },
            ticketFormError: '',
            ticketSelectedPersonnel: null,
            ticketUserSearchQuery: '',
            ticketUserSearchResults: [],
            ticketUserSearchLoading: false,
            ticketUserSearchError: '',
            showTicketUserResults: false,
            isTicketDetailOpen: false,
            ticketDetailLoading: false,
            ticketDetail: null,
            ticketDetailForm: {
                status: 'open',
                priority: 'medium',
                category_id: '',
            },
            isTicketDetailSubmitting: false,
            ticketComments: [],
            ticketCommentBody: '',
            ticketCommentError: '',
            isTicketCommentSubmitting: false,
            assetOptions: Array.isArray(window.__assetOptions) ? window.__assetOptions : [],
            assetLicenses: [],
            assetLicensesLoading: false,
            assetLicensesError: '',
            isSubmitting: false,
            addErrorMessage: '',
            editErrorMessage: '',
            detailAsset: null,
            detailQrSvg: '',
            assetHistory: [],
            historyLoading: false,
            historyError: '',
            categoryFields: window.__categoryFields || {},
            dynamicFields: [],
            dynamicValues: {},
            userSearchQuery: '',
            userSearchResults: [],
            userSearchLoading: false,
            userSearchError: '',
            showUserResults: false,
            selectedUser: null,
            isManualUserFormOpen: false,
            manualUserForm: {
                name: '',
                email: '',
            },
            manualUserFormError: '',
            isManualUserSubmitting: false,
            editAsset: null,
            editForm: {
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
            settingsForm: {
                active_auth_driver: window.__settings?.active_auth_driver || 'local',
                zimmet_template: window.__settings?.zimmet_template || '',
                custom_fields: Array.isArray(window.__settings?.custom_fields)
                    ? JSON.parse(JSON.stringify(window.__settings.custom_fields))
                    : [],
                ldap_config: {
                    host: window.__settings?.ldap_config?.host || '',
                    port: window.__settings?.ldap_config?.port || '389',
                    base_dn: window.__settings?.ldap_config?.base_dn || '',
                    account_suffix: window.__settings?.ldap_config?.account_suffix || '',
                    bind_dn: window.__settings?.ldap_config?.bind_dn || '',
                    bind_password: '',
                    bind_password_configured: Boolean(window.__settings?.ldap_config?.bind_password_configured),
                    use_tls: Boolean(window.__settings?.ldap_config?.use_tls),
                },
                google_config: {
                    domain: window.__settings?.google_config?.domain || '',
                    admin_email: window.__settings?.google_config?.admin_email || '',
                    auth_mode: window.__settings?.google_config?.auth_mode || 'service_account',
                    service_account_json: '',
                    service_account_configured: Boolean(window.__settings?.google_config?.service_account_configured),
                    oauth_token_json: '',
                    oauth_token_configured: Boolean(window.__settings?.google_config?.oauth_token_configured),
                },
                login_config: {
                    providers: {
                        local: Boolean(window.__settings?.login_config?.providers?.local ?? true),
                        ldap: Boolean(window.__settings?.login_config?.providers?.ldap),
                        google: Boolean(window.__settings?.login_config?.providers?.google),
                        microsoft: Boolean(window.__settings?.login_config?.providers?.microsoft),
                    },
                    google_sso: {
                        client_id: window.__settings?.login_config?.google_sso?.client_id || '',
                        client_secret: '',
                        client_secret_configured: Boolean(window.__settings?.login_config?.google_sso?.client_secret_configured),
                    },
                    microsoft_sso: {
                        tenant_id: window.__settings?.login_config?.microsoft_sso?.tenant_id || '',
                        client_id: window.__settings?.login_config?.microsoft_sso?.client_id || '',
                        client_secret: '',
                        client_secret_configured: Boolean(window.__settings?.login_config?.microsoft_sso?.client_secret_configured),
                    },
                },
                smtp_config: {
                    enabled: Boolean(window.__settings?.smtp_config?.enabled),
                    host: window.__settings?.smtp_config?.host || '',
                    port: window.__settings?.smtp_config?.port || '587',
                    user: window.__settings?.smtp_config?.user || '',
                    pass: '',
                    pass_configured: Boolean(window.__settings?.smtp_config?.pass_configured),
                    sender_email: window.__settings?.smtp_config?.sender_email || '',
                    sender_name: window.__settings?.smtp_config?.sender_name || 'Betech ITMS',
                    encryption: window.__settings?.smtp_config?.encryption || 'tls',
                    support_to: window.__settings?.smtp_config?.support_to || '',
                },
            },
            smtpTestRecipient: <?= json_encode($currentUserEmail ?? '', JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>,
            isSendingSmtpTest: false,
            backups: [],
            isLoadingBackups: false,
            isCreatingBackup: false,
            backupRetentionDays: 7,
            backupErrorMessage: '',
            backupSuccessMessage: '',
            authDrivers: [
                {
                    id: 'local',
                    label: window.__i18n.settings_auth_local,
                    description: window.__i18n.settings_auth_local_hint,
                },
                {
                    id: 'ldap',
                    label: window.__i18n.settings_auth_ldap,
                    description: window.__i18n.settings_auth_ldap_hint,
                },
                {
                    id: 'google',
                    label: window.__i18n.settings_auth_google,
                    description: window.__i18n.settings_auth_google_hint,
                },
                {
                    id: 'azure',
                    label: window.__i18n.settings_auth_azure,
                    description: window.__i18n.settings_auth_azure_hint,
                },
            ],
            isSavingSettings: false,
            settingsErrorMessage: '',
            settingsSuccessMessage: '',
            quillEditor: null,
            globalCustomFields: Array.isArray(window.__globalCustomFields) ? window.__globalCustomFields : [],
            personnel: [],
            personnelLoading: false,
            personnelError: '',
            personnelSearch: '',
            personnelPage: 1,
            personnelPerPage: 50,
            personnelPagination: {
                page: 1,
                per_page: 50,
                total: 0,
                total_pages: 1,
            },
            personnelSyncing: false,
            personnelRoleUpdating: null,
            personnelSyncMessage: '',
            personnelSyncError: '',
            isOffboarding: false,
            offboardSuccessMessage: '',
            offboardErrorMessage: '',
            ipamSubView: 'networks',
            ipNetworks: [],
            ipNetworksLoading: false,
            ipNetworksError: '',
            ipNetworksSuccessMessage: '',
            selectedIpNetwork: null,
            ipAddresses: [],
            ipAddressesLoading: false,
            ipAddressesError: '',
            ipAddressStatusFilter: 'all',
            ipAddressStatusFilters: [
                { value: 'all', label: window.__i18n.ipam_filter_all },
                { value: 'available', label: window.__i18n.ipam_status_available },
                { value: 'assigned', label: window.__i18n.ipam_status_assigned },
                { value: 'reserved', label: window.__i18n.ipam_status_reserved },
                { value: 'dhcp', label: window.__i18n.ipam_status_dhcp },
                { value: 'broken', label: window.__i18n.ipam_status_broken },
            ],
            isIpNetworkModalOpen: false,
            isIpNetworkSubmitting: false,
            ipNetworkForm: {
                id: null,
                name: '',
                network_address: '',
                cidr: 24,
                gateway: '',
                vlan_id: '',
                description: '',
                auto_generate: true,
            },
            ipNetworkFormError: '',
            isIpAddressModalOpen: false,
            isIpAddressSubmitting: false,
            ipAddressForm: {
                id: null,
                ip_address: '',
                status: 'available',
                hostname: '',
                mac_address: '',
                notes: '',
            },
            ipAddressFormError: '',
            selectedIpAddressIds: [],
            isIpBulkEditOpen: false,
            isIpBulkEditSubmitting: false,
            ipBulkEditFormError: '',
            ipBulkEditForm: {
                applyStatus: true,
                status: 'available',
                applyNotes: false,
                notes: '',
                applyDepartment: false,
                department: '',
            },
            isIpamImportOpen: false,
            isIpamImportSubmitting: false,
            ipamImportType: 'networks',
            ipamImportFile: null,
            ipamImportFileName: '',
            ipamImportErrorMessage: '',
            ipamImportSuccessMessage: '',
            ipamImportResultErrors: [],
            auditLogs: [],
            auditLogsLoading: false,
            auditLogsError: '',
            auditLogFilters: {
                user_id: '',
                action_type: '',
                entity_type: '',
                date_from: '',
                date_to: '',
            },
            auditLogFilterUsers: [],
            auditLogFilterActions: [],
            auditLogFilterEntities: [],
            auditLogPage: 1,
            auditLogPagination: {
                page: 1,
                per_page: 50,
                total: 0,
                total_pages: 1,
            },
            portalAssets: [],
            portalAssetsLoading: false,
            portalAssetsError: '',
            portalTickets: [],
            portalTicketsLoading: false,
            portalTicketsError: '',
            portalTicketsPage: 1,
            portalTicketsPagination: { page: 1, per_page: 50, total: 0, total_pages: 1 },
            portalToastMessage: '',
            portalToastVisible: false,
            portalToastTimer: null,
            isPortalTicketModalOpen: false,
            isPortalTicketSubmitting: false,
            portalTicketLinkedAsset: null,
            portalTicketForm: { subject: '', description: '', priority: 'medium' },
            portalTicketFormError: '',
            isPortalTicketDetailOpen: false,
            portalTicketDetailLoading: false,
            portalTicketDetail: null,
            portalTicketComments: [],
            portalTicketCommentBody: '',
            portalTicketCommentError: '',
            isPortalTicketCommentSubmitting: false,
            resolvePageTitle() {
                if (this.activeView === 'settings') {
                    const tabTitles = {
                        general: this.pageTitles.settings,
                        categories: this.pageTitles.categories,
                        locations: this.pageTitles.locations,
                        ticket_categories: this.pageTitles.ticket_categories,
                        smtp: this.pageTitles.smtp,
                        backup: this.pageTitles.backup,
                    };

                    return tabTitles[this.settingsTab] || this.pageTitles.settings;
                }

                return this.pageTitles[this.activeView] || (this.isEndUser ? this.pageTitles.knowledge_base : this.pageTitles.assets);
            },
            resolvePageSubtitle() {
                if (this.activeView === 'settings') {
                    const tabSubtitles = {
                        general: this.pageSubtitles.settings,
                        categories: this.pageSubtitles.categories,
                        locations: this.pageSubtitles.locations,
                        ticket_categories: this.pageSubtitles.ticket_categories,
                        smtp: this.pageSubtitles.smtp,
                        backup: this.pageSubtitles.backup,
                    };

                    return tabSubtitles[this.settingsTab] || this.pageSubtitles.settings;
                }

                if (this.isEndUser) {
                    return <?= json_encode(__('app_name'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
                }

                return this.pageSubtitles[this.activeView] || this.pageSubtitles.assets;
            },
            persistDashboardView() {
                sessionStorage.setItem('betechDashboardView', JSON.stringify({
                    activeView: this.activeView,
                    settingsTab: this.settingsTab,
                }));
            },
            restoreDashboardView() {
                const raw = sessionStorage.getItem('betechDashboardView');

                if (!raw) {
                    return;
                }

                sessionStorage.removeItem('betechDashboardView');

                try {
                    const saved = JSON.parse(raw);

                    if (saved.activeView) {
                        this.activeView = this.normalizeEndUserView(saved.activeView);
                    }

                    if (saved.settingsTab) {
                        this.settingsTab = saved.settingsTab;
                    }

                    if (this.isEndUser) {
                        if (this.activeView === 'knowledge_base') {
                            this.fetchPublishedKnowledgeBase();
                        }

                        if (this.activeView === 'my_assets') {
                            this.fetchPortalAssets();
                        }

                        if (this.activeView === 'my_tickets') {
                            this.fetchPortalTickets();
                        }

                        return;
                    }

                    if (this.activeView === 'settings' && this.settingsTab === 'general') {
                        this.$nextTick(() => this.initQuillEditor());
                    }

                    if (this.activeView === 'settings' && this.settingsTab === 'categories') {
                        this.fetchCategories();
                    }

                    if (this.activeView === 'settings' && this.settingsTab === 'locations') {
                        this.fetchLocations();
                    }

                    if (this.activeView === 'settings' && this.settingsTab === 'ticket_categories') {
                        this.fetchTicketCategories();
                    }

                    if (this.activeView === 'settings' && this.settingsTab === 'backup') {
                        this.fetchBackups();
                    }

                    if (this.activeView === 'dashboard') {
                        this.fetchDashboardStats();
                    }

                    if (this.activeView === 'consumables') {
                        this.fetchConsumables();
                    }

                    if (this.activeView === 'knowledge_base') {
                        this.fetchKnowledgeBaseArticles();
                    }

                    if (this.activeView === 'helpdesk') {
                        this.fetchTickets();
                        this.fetchTicketCategories();
                    }

                    if (this.activeView === 'reports') {
                        this.fetchReports();
                    }

                    if (this.activeView === 'ipam') {
                        this.ipamSubView = 'networks';
                        this.fetchIpNetworks();
                    }

                    if (this.activeView === 'audit_logs' && this.canAccessSettings) {
                        this.fetchAuditLogs();
                    }
                } catch (error) {
                    // Ignore invalid persisted view state.
                }
            },
            normalizeEndUserView(view) {
                if (!this.isEndUser) {
                    return view;
                }

                if (view === 'assets') {
                    return 'my_assets';
                }

                if (view === 'helpdesk' || view === 'tickets') {
                    return 'my_tickets';
                }

                if (view === 'knowledge_base' || view === 'kb') {
                    return 'knowledge_base';
                }

                if (view === 'my_assets' || view === 'my_tickets' || view === 'knowledge_base') {
                    return view;
                }

                return 'knowledge_base';
            },
            initEndUserPortal() {
                this.activeView = this.normalizeEndUserView(this.activeView);
                this.fetchPublishedKnowledgeBase();

                if (this.activeView === 'my_assets') {
                    this.fetchPortalAssets();
                }

                const params = new URLSearchParams(window.location.search);
                const ticketId = params.get('ticket');

                if (ticketId) {
                    this.activeView = 'my_tickets';
                    this.fetchPortalTickets().then(() => this.maybeOpenPortalTicketFromUrl(ticketId));
                } else if (this.activeView === 'my_tickets') {
                    this.fetchPortalTickets();
                }
            },
            async fetchPortalAssets() {
                if (!this.isEndUser) {
                    return;
                }

                this.portalAssetsLoading = true;
                this.portalAssetsError = '';

                try {
                    const response = await fetch('/api/my/assets', { headers: { Accept: 'application/json' } });
                    const result = await response.json();

                    if (!response.ok) {
                        this.portalAssetsError = result.message || window.__i18n.portal_assets_error;
                        this.portalAssets = [];
                        return;
                    }

                    this.portalAssets = Array.isArray(result.data) ? result.data : [];
                } catch (error) {
                    this.portalAssetsError = window.__i18n.portal_assets_error;
                    this.portalAssets = [];
                } finally {
                    this.portalAssetsLoading = false;
                }
            },
            async fetchPortalTickets() {
                if (!this.isEndUser) {
                    return;
                }

                this.portalTicketsLoading = true;
                this.portalTicketsError = '';

                try {
                    const params = new URLSearchParams({
                        page: String(this.portalTicketsPage),
                    });
                    const response = await fetch(`/api/tickets?${params.toString()}`, { headers: { Accept: 'application/json' } });
                    const result = await this.parseApiResponse(response);

                    if (!response.ok) {
                        this.portalTicketsError = this.apiErrorMessage(result, window.__i18n.portal_tickets_error);
                        this.portalTickets = [];
                        return;
                    }

                    this.portalTickets = Array.isArray(result.data) ? result.data : [];
                    this.portalTicketsPagination = result.pagination || this.defaultListPagination();
                    this.portalTicketsPage = Number(this.portalTicketsPagination.page || 1);
                } catch (error) {
                    this.portalTicketsError = window.__i18n.portal_tickets_error;
                    this.portalTickets = [];
                } finally {
                    this.portalTicketsLoading = false;
                }
            },
            portalTicketsPageNumbers() {
                return this.listPaginationWindow(this.portalTicketsPagination);
            },
            resolvePortalTicketsPaginationLabel() {
                return this.resolveListPaginationLabel(this.portalTicketsPagination);
            },
            goToPortalTicketsPage(page) {
                const targetPage = Number(page);

                if (
                    Number.isNaN(targetPage)
                    || targetPage < 1
                    || targetPage > Number(this.portalTicketsPagination.total_pages || 1)
                    || targetPage === this.portalTicketsPagination.page
                ) {
                    return;
                }

                this.portalTicketsPage = targetPage;
                this.fetchPortalTickets();
            },
            maybeOpenPortalTicketFromUrl(ticketId) {
                if (!ticketId) {
                    return;
                }

                const ticket = this.portalTickets.find((item) => String(item.id) === String(ticketId));

                if (ticket) {
                    this.openPortalTicketDetail(ticket);
                    return;
                }

                this.openPortalTicketDetail({ id: Number(ticketId) });
            },
            portalAssetStatusClass(status) {
                return window.__portalStatusStyles[status] || 'bg-zinc-100 text-zinc-700 ring-zinc-500/20';
            },
            portalAssetStatusLabel(status) {
                const map = {
                    ready: window.__i18n.status_ready,
                    deployed: window.__i18n.status_deployed,
                    storage: window.__i18n.status_storage,
                    broken: window.__i18n.status_broken,
                    under_repair: window.__i18n.status_under_repair,
                };

                return map[status] || status || '—';
            },
            portalTicketStatusClass(status) {
                const classes = {
                    open: 'bg-sky-50 text-sky-700 ring-sky-600/20',
                    in_progress: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
                    resolved: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                    closed: 'bg-zinc-100 text-zinc-700 ring-zinc-500/20',
                };

                return classes[status] || 'bg-zinc-100 text-zinc-700 ring-zinc-500/20';
            },
            portalTicketStatusLabel(status) {
                const map = {
                    open: window.__i18n.ticket_status_open,
                    in_progress: window.__i18n.ticket_status_in_progress,
                    resolved: window.__i18n.ticket_status_resolved,
                    closed: window.__i18n.ticket_status_closed,
                };

                return map[status] || status;
            },
            formatPortalDate(value) {
                if (!value) {
                    return '—';
                }

                const date = new Date(String(value).replace(' ', 'T'));

                return Number.isNaN(date.getTime()) ? value : date.toLocaleString(window.__i18n.locale || 'tr');
            },
            openPortalTicketModal() {
                this.portalTicketLinkedAsset = null;
                this.portalTicketForm = { subject: '', description: '', priority: 'medium' };
                this.portalTicketFormError = '';
                this.isPortalTicketModalOpen = true;
            },
            openPortalTicketModalForAsset(asset) {
                this.portalTicketLinkedAsset = {
                    id: asset.id,
                    name: asset.name || '',
                    asset_tag: asset.asset_tag || '',
                };
                this.portalTicketForm = { subject: '', description: '', priority: 'medium' };
                this.portalTicketFormError = '';
                this.isPortalTicketModalOpen = true;
            },
            portalTicketLinkedAssetMessage() {
                if (!this.portalTicketLinkedAsset) {
                    return '';
                }

                return window.__i18n.portal_ticket_for_asset
                    .replace(':name', this.portalTicketLinkedAsset.name || '—')
                    .replace(':tag', this.portalTicketLinkedAsset.asset_tag || '—');
            },
            closePortalTicketModal() {
                this.isPortalTicketSubmitting = false;
                this.isPortalTicketModalOpen = false;
                this.portalTicketLinkedAsset = null;
            },
            showPortalToast(message) {
                this.portalToastMessage = message;
                this.portalToastVisible = true;

                if (this.portalToastTimer) {
                    clearTimeout(this.portalToastTimer);
                }

                this.portalToastTimer = setTimeout(() => {
                    this.portalToastVisible = false;
                    this.portalToastMessage = '';
                    this.portalToastTimer = null;
                }, 4000);
            },
            async submitPortalTicket() {
                if (this.isPortalTicketSubmitting) {
                    return;
                }

                this.isPortalTicketSubmitting = true;
                this.portalTicketFormError = '';

                const payload = {
                    subject: this.portalTicketForm.subject.trim(),
                    description: this.portalTicketForm.description.trim(),
                    priority: this.portalTicketForm.priority,
                };

                if (this.portalTicketLinkedAsset?.id) {
                    payload.asset_id = Number(this.portalTicketLinkedAsset.id);
                }

                try {
                    const response = await fetch('/api/tickets', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    const result = await this.parseApiResponse(response);

                    if (!response.ok) {
                        this.portalTicketFormError = this.apiErrorMessage(result, window.__i18n.ticket_create_error);
                        return;
                    }

                    this.isPortalTicketModalOpen = false;
                    this.portalTicketLinkedAsset = null;
                    this.portalTicketForm = { subject: '', description: '', priority: 'medium' };
                    this.activeView = 'my_tickets';

                    if (result.data) {
                        this.portalTickets = [result.data, ...this.portalTickets.filter((ticket) => ticket.id !== result.data.id)];
                    }

                    this.showPortalToast(this.apiErrorMessage(result, window.__i18n.ticket_create_success));
                    await this.fetchPortalTickets();
                } catch (error) {
                    this.portalTicketFormError = window.__i18n.ticket_create_error;
                } finally {
                    this.isPortalTicketSubmitting = false;
                }
            },
            async openPortalTicketDetail(ticket) {
                this.isPortalTicketDetailOpen = true;
                this.portalTicketDetailLoading = true;
                this.portalTicketDetail = ticket;
                this.portalTicketComments = [];
                this.portalTicketCommentBody = '';
                this.portalTicketCommentError = '';

                try {
                    const response = await fetch(`/api/tickets/${ticket.id}`, { headers: { Accept: 'application/json' } });
                    const result = await this.parseApiResponse(response);

                    if (!response.ok) {
                        this.portalTicketsError = this.apiErrorMessage(result, window.__i18n.ticket_not_found);
                        return;
                    }

                    this.portalTicketDetail = result.data;
                    this.portalTicketComments = Array.isArray(result.data?.comments) ? result.data.comments : [];
                } catch (error) {
                    this.portalTicketsError = window.__i18n.portal_tickets_error;
                } finally {
                    this.portalTicketDetailLoading = false;
                }
            },
            closePortalTicketDetail() {
                this.isPortalTicketCommentSubmitting = false;
                this.isPortalTicketDetailOpen = false;
                this.portalTicketDetail = null;
            },
            async submitPortalTicketComment() {
                if (!this.portalTicketDetail?.id || this.isPortalTicketCommentSubmitting) {
                    return;
                }

                const body = this.portalTicketCommentBody.trim();

                if (body === '') {
                    this.portalTicketCommentError = window.__i18n.ticket_comment_create_error;
                    return;
                }

                this.isPortalTicketCommentSubmitting = true;
                this.portalTicketCommentError = '';

                try {
                    const response = await fetch(`/api/tickets/${this.portalTicketDetail.id}/comments`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                        body: JSON.stringify({ body }),
                    });
                    const result = await this.parseApiResponse(response);

                    if (!response.ok) {
                        this.portalTicketCommentError = this.apiErrorMessage(result, window.__i18n.ticket_comment_create_error);
                        return;
                    }

                    this.portalTicketCommentBody = '';
                    this.portalTicketComments = this.appendTicketComment(this.portalTicketComments, result.data);
                    this.showPortalToast(this.apiErrorMessage(result, window.__i18n.ticket_comment_create_success));
                } catch (error) {
                    this.portalTicketCommentError = window.__i18n.ticket_comment_create_error;
                } finally {
                    this.isPortalTicketCommentSubmitting = false;
                }
            },
            apiErrorMessage(result, fallback) {
                if (!result || typeof result !== 'object') {
                    return fallback;
                }

                if (typeof result.message === 'string' && result.message !== '') {
                    return result.message;
                }

                if (typeof result.error === 'string' && result.error !== '') {
                    return result.error;
                }

                return fallback;
            },
            resolveCsrfToken() {
                const token = String(this.csrfToken || '').trim();

                if (token !== '') {
                    return token;
                }

                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')?.trim() ?? '';
            },
            apiFetchInit(method = 'GET', init = {}) {
                const normalizedMethod = String(method || 'GET').toUpperCase();
                const headers = new Headers(init.headers ?? {});

                if (!headers.has('Accept')) {
                    headers.set('Accept', 'application/json');
                }

                if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(normalizedMethod)) {
                    const token = this.resolveCsrfToken();

                    if (token !== '' && !headers.has('X-CSRF-TOKEN')) {
                        headers.set('X-CSRF-TOKEN', token);
                    }
                }

                return {
                    ...init,
                    method: normalizedMethod,
                    headers,
                };
            },
            apiFetchJsonInit(method = 'GET', body = null, init = {}) {
                const requestInit = this.apiFetchInit(method, init);
                const headers = new Headers(requestInit.headers ?? {});

                if (body !== null && body !== undefined && !headers.has('Content-Type')) {
                    headers.set('Content-Type', 'application/json');
                }

                requestInit.headers = headers;

                if (body !== null && body !== undefined) {
                    requestInit.body = typeof body === 'string' ? body : JSON.stringify(body);
                }

                return requestInit;
            },
            defaultListPagination() {
                return { page: 1, per_page: 50, total: 0, total_pages: 1 };
            },
            listPaginationWindow(pagination) {
                const totalPages = Number(pagination?.total_pages || 1);
                const currentPage = Number(pagination?.page || 1);
                const windowSize = 5;
                let start = Math.max(1, currentPage - Math.floor(windowSize / 2));
                let end = Math.min(totalPages, start + windowSize - 1);

                if (end - start + 1 < windowSize) {
                    start = Math.max(1, end - windowSize + 1);
                }

                const pages = [];

                for (let page = start; page <= end; page += 1) {
                    pages.push(page);
                }

                return pages;
            },
            resolveListPaginationLabel(pagination) {
                return window.__i18n.list_pagination_info
                    .replace(':total', String(pagination?.total || 0))
                    .replace(':page', String(pagination?.page || 1))
                    .replace(':total_pages', String(pagination?.total_pages || 1));
            },
            formatApiDebugDetails(result) {
                const debug = result?.debug;
                if (!debug || typeof debug !== 'object') {
                    return '';
                }

                const parts = [];
                if (typeof debug.type === 'string' && debug.type !== '') {
                    parts.push(debug.type);
                }
                if (typeof debug.file === 'string' && debug.file !== '') {
                    parts.push(`${debug.file}:${debug.line ?? '?'}`);
                }
                if (typeof debug.trace === 'string' && debug.trace !== '') {
                    parts.push(debug.trace);
                }

                return parts.join('\n');
            },
            async parseApiResponse(response) {
                const text = await response.text();

                if (!text || text.trim() === '') {
                    return response.ok
                        ? { status: 'success', success: true }
                        : { status: 'error', success: false, message: window.__i18n.helpdesk_network_error };
                }

                try {
                    const parsed = JSON.parse(text);

                    return parsed && typeof parsed === 'object'
                        ? parsed
                        : { status: 'error', success: false, message: text };
                } catch (error) {
                    return {
                        status: 'error',
                        success: false,
                        message: response.ok ? text : window.__i18n.helpdesk_network_error,
                    };
                }
            },
            appendTicketComment(comments, comment) {
                if (!comment || typeof comment !== 'object' || !comment.id) {
                    return Array.isArray(comments) ? comments : [];
                }

                const existing = Array.isArray(comments) ? comments : [];

                if (existing.some((item) => Number(item.id) === Number(comment.id))) {
                    return existing;
                }

                return [...existing, comment];
            },
            async fetchPersonnelSearchOptions(query) {
                const trimmedQuery = String(query ?? '').trim();

                try {
                    const response = await fetch(`/api/personnel/search?q=${encodeURIComponent(trimmedQuery)}`, {
                        headers: { Accept: 'application/json' },
                    });
                    const result = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        return {
                            data: [],
                            error: this.apiErrorMessage(result, window.__i18n.personnel_search_failed),
                        };
                    }

                    return {
                        data: Array.isArray(result.data) ? result.data : [],
                        error: '',
                    };
                } catch (error) {
                    return {
                        data: [],
                        error: window.__i18n.personnel_search_failed,
                    };
                }
            },
            async fetchDashboardStats() {
                if (!this.canManageAssets || this.dashboardLoading) {
                    return;
                }

                this.dashboardLoading = true;
                this.dashboardError = '';

                try {
                    const response = await fetch('/api/dashboard/stats', {
                        headers: { Accept: 'application/json' },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.dashboardError = this.apiErrorMessage(result, window.__i18n.network_error);
                        return;
                    }

                    this.dashboardStats = result.data || null;
                    this.$nextTick(() => {
                        this.$nextTick(() => this.renderCategoryChart());
                    });
                } catch (error) {
                    this.dashboardError = window.__i18n.network_error;
                } finally {
                    this.dashboardLoading = false;
                }
            },
            renderCategoryChart() {
                if (typeof Chart === 'undefined') {
                    return;
                }

                const canvas = document.getElementById('dashboardCategoryChart');

                if (!canvas || !this.dashboardStats) {
                    return;
                }

                const categories = Array.isArray(this.dashboardStats.by_category)
                    ? this.dashboardStats.by_category
                    : [];

                if (categories.length === 0) {
                    if (this.categoryChart) {
                        this.categoryChart.destroy();
                        this.categoryChart = null;
                    }

                    return;
                }

                if (this.categoryChart) {
                    this.categoryChart.destroy();
                }

                const palette = ['#18181b', '#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6', '#f43f5e', '#64748b', '#14b8a6'];

                this.categoryChart = new Chart(canvas, {
                    type: 'doughnut',
                    data: {
                        labels: categories.map((row) => String(row.category_name || '')),
                        datasets: [{
                            data: categories.map((row) => Number(row.count || 0)),
                            backgroundColor: categories.map((_, index) => palette[index % palette.length]),
                            borderWidth: 0,
                            hoverOffset: 6,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '72%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    padding: 18,
                                    color: '#52525b',
                                    font: {
                                        size: 11,
                                        family: 'Inter, ui-sans-serif, system-ui, sans-serif',
                                    },
                                },
                            },
                            tooltip: {
                                backgroundColor: '#18181b',
                                titleColor: '#fafafa',
                                bodyColor: '#e4e4e7',
                                borderColor: '#3f3f46',
                                borderWidth: 1,
                                padding: 10,
                            },
                        },
                    },
                });
            },
            formatDashboardActivity(activity) {
                const assetName = String(activity?.asset_name || 'Asset');
                const person = String(activity?.target_user_name || activity?.target_personnel_name || '—');
                const action = String(activity?.action || '');
                const templates = {
                    assigned: window.__i18n.dashboard_activity_assigned,
                    transferred: window.__i18n.dashboard_activity_transferred,
                    returned: window.__i18n.dashboard_activity_returned,
                    created: window.__i18n.dashboard_activity_created,
                    updated: window.__i18n.dashboard_activity_updated,
                    offboarded: window.__i18n.dashboard_activity_offboarded,
                    unassigned: window.__i18n.dashboard_activity_unassigned,
                    status_change: window.__i18n.dashboard_activity_status_change,
                    location_moved: window.__i18n.dashboard_activity_location_moved,
                };
                const template = templates[action] || window.__i18n.dashboard_activity_generic;

                return template
                    .replace(':asset', assetName)
                    .replace(':person', person)
                    .replace(':action', action.replace(/_/g, ' '));
            },
            formatActivityTimestamp(value, options = {}) {
                if (!value) {
                    return options.fallback ?? '';
                }

                const date = new Date(String(value).replace(' ', 'T'));

                if (Number.isNaN(date.getTime())) {
                    return String(value);
                }

                const locale = window.__i18n.locale === 'en' ? 'en-US' : 'tr-TR';
                const format = {
                    month: 'short',
                    day: 'numeric',
                };

                if (options.includeTime) {
                    format.hour = 'numeric';
                    format.minute = '2-digit';
                }

                if (options.includeYear) {
                    format.year = 'numeric';
                }

                return new Intl.DateTimeFormat(locale, format).format(date);
            },
            formatDashboardActivityTime(value) {
                return this.formatActivityTimestamp(value, { includeTime: true });
            },
            escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;',
                }[char]));
            },
            formatAuditFeed(log) {
                const rawName = String(log?.user_name || '').trim()
                    || window.__i18n.dashboard_log_unknown_user
                    || 'System';
                const action = String(log?.action_type || '');
                const entity = String(log?.entity_type || '');
                const verb = window.__i18n['dashboard_log_verb_' + action] || action.replace(/_/g, ' ');
                const entityPhrase = window.__i18n['dashboard_log_entity_' + entity] || entity;
                const userHtml = '<span class="font-semibold text-gray-900">' + this.escapeHtml(rawName) + '</span>';

                if (action === 'login' || entity === '') {
                    return (window.__i18n.dashboard_log_template_simple || ':user :verb')
                        .replace(':user', userHtml)
                        .replace(':verb', this.escapeHtml(verb));
                }

                return (window.__i18n.dashboard_log_template || ':user :verb :entity')
                    .replace(':user', userHtml)
                    .replace(':verb', this.escapeHtml(verb))
                    .replace(':entity', this.escapeHtml(entityPhrase));
            },
            formatAuditFeedTime(value) {
                if (!value) {
                    return '';
                }

                const date = new Date(String(value).replace(' ', 'T'));

                if (Number.isNaN(date.getTime())) {
                    return String(value);
                }

                return new Intl.DateTimeFormat('en-US', {
                    month: 'short',
                    day: 'numeric',
                }).format(date);
            },
            auditFeedDotClass(action) {
                const classes = {
                    created: 'bg-green-500',
                    updated: 'bg-gray-900',
                    deleted: 'bg-red-500',
                    login: 'bg-violet-500',
                    assigned: 'bg-amber-500',
                    returned: 'bg-gray-400',
                    transferred: 'bg-indigo-500',
                };

                return classes[action] || 'bg-gray-400';
            },
            resolvePersonnelStatus(status) {
                return status === 'offboarded'
                    ? window.__i18n.personnel_status_offboarded
                    : window.__i18n.personnel_status_active;
            },
            resolvePersonnelRoleLabel(role) {
                return role === 'admin'
                    ? window.__i18n.personnel_role_admin
                    : window.__i18n.personnel_role_user;
            },
            resolvePersonnelRoleBadgeClass(role) {
                return role === 'admin'
                    ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
                    : 'bg-zinc-100 text-zinc-600 ring-zinc-500/20';
            },
            async updatePersonnelRole(person, role) {
                if (!this.isSuperAdmin || !person?.id || person.role === role) {
                    return;
                }

                this.personnelRoleUpdating = person.id;
                this.personnelError = '';

                try {
                    const response = await fetch(`/api/personnel/${person.id}/role`, {
                        method: 'PUT',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ role }),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.personnelError = result.message || window.__i18n.personnel_role_update_error;
                        return;
                    }

                    person.role = role;
                    this.personnelSyncMessage = result.message || window.__i18n.personnel_role_update_success;
                } catch (error) {
                    this.personnelError = window.__i18n.personnel_network_error;
                } finally {
                    this.personnelRoleUpdating = null;
                }
            },
            resolvePersonnelPaginationLabel() {
                return this.resolveListPaginationLabel(this.personnelPagination);
            },
            personnelPageNumbers() {
                return this.listPaginationWindow(this.personnelPagination);
            },
            onPersonnelSearchInput() {
                this.personnelPage = 1;
                this.fetchPersonnel();
            },
            goToPersonnelPage(page) {
                const targetPage = Number(page);

                if (
                    Number.isNaN(targetPage)
                    || targetPage < 1
                    || targetPage > Number(this.personnelPagination.total_pages || 1)
                    || targetPage === this.personnelPagination.page
                ) {
                    return;
                }

                this.personnelPage = targetPage;
                this.fetchPersonnel();
            },
            async fetchPersonnel() {
                if (!this.canAccessPersonnel) {
                    return;
                }

                this.personnelLoading = true;
                this.personnelError = '';

                const params = new URLSearchParams({
                    page: String(this.personnelPage),
                });

                if (this.personnelSearch.trim() !== '') {
                    params.set('q', this.personnelSearch.trim());
                }

                try {
                    const response = await fetch(`/api/personnel?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.personnelError = result.message || window.__i18n.personnel_fetch_error;
                        this.personnel = [];
                        return;
                    }

                    this.personnel = Array.isArray(result.data) ? result.data : [];
                    this.personnelPagination = {
                        page: Number(result.pagination?.page || this.personnelPage),
                        per_page: Number(result.pagination?.per_page || 50),
                        total: Number(result.pagination?.total || 0),
                        total_pages: Number(result.pagination?.total_pages || 1),
                    };
                    this.personnelPage = this.personnelPagination.page;
                } catch (error) {
                    this.personnelError = window.__i18n.personnel_network_error;
                    this.personnel = [];
                } finally {
                    this.personnelLoading = false;
                }
            },
            async syncPersonnelDirectory() {
                if (!this.canAccessPersonnel || this.personnelSyncing) {
                    return;
                }

                this.personnelSyncing = true;
                this.personnelSyncMessage = '';
                this.personnelSyncError = '';

                try {
                    const response = await fetch('/api/personnel/sync-ldap', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.personnelSyncError = result.message || window.__i18n.personnel_ldap_sync_error;
                        return;
                    }

                    const stats = result.data || {};
                    const created = Number(stats.created || 0);
                    const updated = Number(stats.updated || 0);

                    this.personnelSyncMessage = result.message || window.__i18n.personnel_ldap_sync_success
                        .replace(':created', String(created))
                        .replace(':updated', String(updated));
                    this.personnelPage = 1;
                    await this.fetchPersonnel();
                } catch (error) {
                    this.personnelSyncError = window.__i18n.personnel_network_error;
                } finally {
                    this.personnelSyncing = false;
                }
            },
            printTutanak(assetId) {
                window.open(`/api/assets/${assetId}/tutanak`, '_blank', 'noopener,noreferrer');
            },
            exportAssets() {
                window.location.href = '/api/assets/export';
            },
            openAssignModal(asset) {
                if (asset?.assigned_to) {
                    return;
                }

                this.assignAsset = asset;
                this.assignErrorMessage = '';
                this.assignLocationId = '';
                this.resetAssignUserSearch();
                this.isAssignOpen = true;

                if (this.locations.length === 0 && this.canManageAssets) {
                    this.fetchLocations();
                }
            },
            closeAssignModal() {
                if (this.isAssignSubmitting) {
                    return;
                }

                this.isAssignOpen = false;
                this.assignAsset = null;
                this.resetAssignUserSearch();
                this.assignLocationId = '';
                this.assignErrorMessage = '';
            },
            resetAssignUserSearch() {
                this.assignUserSearchQuery = '';
                this.assignUserSearchResults = [];
                this.assignUserSearchLoading = false;
                this.assignUserSearchError = '';
                this.showAssignUserResults = false;
                this.assignSelectedUser = null;
            },
            async searchAssignUsers() {
                this.assignUserSearchLoading = true;
                this.assignUserSearchError = '';

                const result = await this.fetchPersonnelSearchOptions(this.assignUserSearchQuery);
                this.assignUserSearchResults = result.data;
                this.assignUserSearchError = result.error;
                this.showAssignUserResults = true;
                this.assignUserSearchLoading = false;
            },
            selectAssignUser(user) {
                this.assignSelectedUser = user;
                this.assignUserSearchQuery = '';
                this.assignUserSearchResults = [];
                this.assignUserSearchError = '';
                this.showAssignUserResults = false;
                this.assignErrorMessage = '';
            },
            clearAssignUser() {
                this.assignSelectedUser = null;
            },
            async submitAssign() {
                if (!this.assignAsset?.id) {
                    this.assignErrorMessage = window.__i18n.assign_error;
                    return;
                }

                if (!this.assignSelectedUser?.id) {
                    this.assignErrorMessage = window.__i18n.assign_select_user;
                    return;
                }

                this.isAssignSubmitting = true;
                this.assignErrorMessage = '';

                const payload = {
                    personnel_id: Number(this.assignSelectedUser.id),
                };

                if (this.assignLocationId) {
                    payload.location_id = Number(this.assignLocationId);
                }

                try {
                    const response = await fetch(`/api/assets/${this.assignAsset.id}/assign`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });
                    const result = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        this.assignErrorMessage = this.apiErrorMessage(result, window.__i18n.assign_error);
                        return;
                    }

                    const assetId = this.assignAsset.id;
                    this.closeAssignModal();

                    if (window.confirm(window.__i18n.assign_print_tutanak_prompt)) {
                        this.printTutanak(assetId);
                    }

                    window.location.reload();
                } catch (error) {
                    this.assignErrorMessage = window.__i18n.assign_network_error;
                } finally {
                    this.isAssignSubmitting = false;
                }
            },
            openReturnModal(asset) {
                if (!asset?.user_id) {
                    return;
                }

                this.returnAsset = asset;
                this.returnErrorMessage = '';
                this.isReturnOpen = true;
            },
            closeReturnModal() {
                if (this.isReturnSubmitting) {
                    return;
                }

                this.isReturnOpen = false;
                this.returnAsset = null;
                this.returnErrorMessage = '';
            },
            async submitReturn() {
                if (!this.returnAsset?.id) {
                    this.returnErrorMessage = window.__i18n.return_error;
                    return;
                }

                this.isReturnSubmitting = true;
                this.returnErrorMessage = '';

                try {
                    const response = await fetch(`/api/assets/${this.returnAsset.id}/return`, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                    });
                    const result = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        this.returnErrorMessage = this.apiErrorMessage(result, window.__i18n.return_error);
                        return;
                    }

                    this.closeReturnModal();
                    window.location.reload();
                } catch (error) {
                    this.returnErrorMessage = window.__i18n.return_network_error;
                } finally {
                    this.isReturnSubmitting = false;
                }
            },
            async deleteAsset(assetId) {
                if (!assetId || !window.confirm(window.__i18n.delete_confirm)) {
                    return;
                }

                try {
                    const response = await fetch(`/api/assets/${assetId}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        window.alert(result.message || window.__i18n.delete_error);
                        return;
                    }

                    window.alert(result.message || window.__i18n.delete_success);
                    window.location.reload();
                } catch (error) {
                    window.alert(window.__i18n.delete_network_error);
                }
            },
            openTransferModal(asset) {
                if (!asset?.user_id) {
                    return;
                }

                this.transferAsset = asset;
                this.transferErrorMessage = '';
                this.resetTransferUserSearch();
                this.isTransferOpen = true;
            },
            closeTransferModal() {
                if (this.isTransferSubmitting) {
                    return;
                }

                this.isTransferOpen = false;
                this.transferAsset = null;
                this.resetTransferUserSearch();
                this.transferErrorMessage = '';
            },
            resetTransferUserSearch() {
                this.transferUserSearchQuery = '';
                this.transferUserSearchResults = [];
                this.transferUserSearchLoading = false;
                this.transferUserSearchError = '';
                this.showTransferUserResults = false;
                this.transferSelectedUser = null;
            },
            async searchTransferUsers() {
                this.transferUserSearchLoading = true;
                this.transferUserSearchError = '';

                const result = await this.fetchPersonnelSearchOptions(this.transferUserSearchQuery);
                this.transferUserSearchResults = result.data;
                this.transferUserSearchError = result.error;
                this.showTransferUserResults = true;
                this.transferUserSearchLoading = false;
            },
            selectTransferUser(user) {
                this.transferSelectedUser = user;
                this.transferUserSearchQuery = '';
                this.transferUserSearchResults = [];
                this.transferUserSearchError = '';
                this.showTransferUserResults = false;
                this.transferErrorMessage = '';
            },
            clearTransferUser() {
                this.transferSelectedUser = null;
            },
            async submitTransfer() {
                if (!this.transferAsset?.id) {
                    this.transferErrorMessage = window.__i18n.transfer_error;
                    return;
                }

                if (!this.transferSelectedUser?.id) {
                    this.transferErrorMessage = window.__i18n.transfer_select_user;
                    return;
                }

                this.isTransferSubmitting = true;
                this.transferErrorMessage = '';

                try {
                    const response = await fetch(`/api/assets/${this.transferAsset.id}/transfer`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            personnel_id: Number(this.transferSelectedUser.id),
                        }),
                    });
                    const result = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        this.transferErrorMessage = this.apiErrorMessage(result, window.__i18n.transfer_error);
                        return;
                    }

                    const assetId = this.transferAsset.id;
                    const recipientName = this.transferSelectedUser?.name || '';
                    this.closeTransferModal();

                    const tutanakPrompt = (window.__i18n.transfer_print_tutanak_prompt || '')
                        .replace('{name}', recipientName);

                    if (window.confirm(tutanakPrompt)) {
                        this.printTutanak(assetId);
                    }

                    window.location.reload();
                } catch (error) {
                    this.transferErrorMessage = window.__i18n.transfer_network_error;
                } finally {
                    this.isTransferSubmitting = false;
                }
            },
            async startOffboarding(person) {
                if (!person?.id || !window.confirm(window.__i18n.offboard_confirm)) {
                    return;
                }

                this.isOffboarding = true;
                this.offboardSuccessMessage = '';
                this.offboardErrorMessage = '';

                try {
                    const response = await fetch(`/api/personnel/${person.id}/offboard`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.offboardErrorMessage = result.message || window.__i18n.offboard_error;
                        return;
                    }

                    this.offboardSuccessMessage = result.message || window.__i18n.offboard_success;
                    person.status = 'offboarded';
                    person.assigned_asset_count = 0;
                    await this.fetchPersonnel();
                } catch (error) {
                    this.offboardErrorMessage = window.__i18n.offboard_network_error;
                } finally {
                    this.isOffboarding = false;
                }
            },
            openAddModal() {
                this.addErrorMessage = '';
                this.resetUserSearch();
                this.form = {
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
                };
                this.isAddOpen = true;
            },
            closeAddModal() {
                if (this.isSubmitting) {
                    return;
                }

                this.isAddOpen = false;
            },
            openImportModal() {
                this.importFile = null;
                this.importFileName = '';
                this.importDragOver = false;
                this.importErrorMessage = '';
                this.importSuccessMessage = '';
                this.importResultErrors = [];
                this.isImportOpen = true;
            },
            closeImportModal() {
                if (this.isImportSubmitting) {
                    return;
                }

                this.isImportOpen = false;
                this.importDragOver = false;
                this.importFile = null;
                this.importFileName = '';
            },
            setImportFile(file) {
                this.importFile = file;
                this.importFileName = file ? file.name : '';
                this.importErrorMessage = '';
                this.importSuccessMessage = '';
                this.importResultErrors = [];
            },
            onImportFileSelected(event) {
                const file = event.target.files?.[0] ?? null;
                this.setImportFile(file);
            },
            onImportFileDropped(event) {
                this.importDragOver = false;
                const file = event.dataTransfer?.files?.[0] ?? null;

                if (!file) {
                    return;
                }

                this.setImportFile(file);
            },
            resolveAssetFilterLabel(field) {
                if (field?.label_key && window.__i18n[field.label_key]) {
                    return window.__i18n[field.label_key];
                }

                const locale = window.__i18n.locale || 'tr';

                if (locale === 'en' && field?.label_en) {
                    return field.label_en;
                }

                return field?.label || field?.name || '';
            },
            resolveAssetFilterOptionLabel(option) {
                if (option?.label_key && window.__i18n[option.label_key]) {
                    return window.__i18n[option.label_key];
                }

                return option?.label || option?.value || '';
            },
            hasActiveAssetFilters() {
                return Object.values(this.assetFilters || {}).some((value) => String(value || '').trim() !== '');
            },
            inventoryStatusClass(status) {
                const classes = {
                    ready: 'bg-sky-50 text-sky-700 ring-sky-600/20',
                    deployed: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                    storage: 'bg-amber-50 text-amber-700 ring-amber-600/20',
                    broken: 'bg-rose-50 text-rose-700 ring-rose-600/20',
                    under_repair: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
                };

                return classes[status] || 'bg-zinc-100 text-zinc-700 ring-zinc-500/20';
            },
            translateInventoryStatus(status) {
                const key = `status_${status}`;

                return window.__i18n[key] || status;
            },
            formatInventoryLocation(asset) {
                const building = String(asset?.building || asset?.location_building || '').trim();
                const name = String(asset?.location || asset?.location_name || '').trim();

                if (name === '') {
                    return building;
                }

                if (building === '') {
                    return name;
                }

                return `${building} / ${name}`;
            },
            buildInventoryDetailPayload(asset) {
                return {
                    id: asset.id,
                    asset_tag: asset.asset_tag,
                    name: asset.name,
                    model: asset.model || '',
                    brand: asset.brand || '',
                    serial_number: asset.serial_number || '',
                    type: asset.type || asset.category_name || '',
                    status: asset.status,
                    location: asset.location || asset.location_name || '',
                    building: asset.building || asset.location_building || '',
                    assigned_to: asset.assigned_to || asset.user_name || '',
                    mac_address_1: asset.mac_address_1 || '',
                    mac_address_2: asset.mac_address_2 || '',
                };
            },
            buildInventoryAssignPayload(asset) {
                return {
                    id: asset.id,
                    asset_tag: asset.asset_tag,
                    name: asset.name,
                    serial_number: asset.serial_number || '',
                    type: asset.type || asset.category_name || '',
                    location: asset.location || asset.location_name || '',
                    building: asset.building || asset.location_building || '',
                    assigned_to: asset.assigned_to || asset.user_name || '',
                };
            },
            buildInventoryEditPayload(asset) {
                return {
                    id: asset.id,
                    asset_tag: asset.asset_tag,
                    name: asset.name,
                    model: asset.model || '',
                    brand: asset.brand || '',
                    serial_number: asset.serial_number || '',
                    type: asset.type || asset.category_name || '',
                    status: asset.status,
                    location: asset.location || asset.location_name || '',
                    building: asset.building || asset.location_building || '',
                    assigned_to: asset.assigned_to || asset.user_name || '',
                    mac_address_1: asset.mac_address_1 || '',
                    mac_address_2: asset.mac_address_2 || '',
                };
            },
            buildInventoryReturnPayload(asset) {
                return {
                    id: asset.id,
                    asset_tag: asset.asset_tag,
                    name: asset.name,
                    assigned_to: asset.assigned_to || asset.user_name || '',
                };
            },
            buildInventoryTransferPayload(asset) {
                return {
                    id: asset.id,
                    asset_tag: asset.asset_tag,
                    name: asset.name,
                    assigned_to: asset.assigned_to || asset.user_name || '',
                    location: asset.location || asset.location_name || '',
                    building: asset.building || asset.location_building || '',
                };
            },
            syncInventoryAssetOptions() {
                this.assetOptions = (this.inventoryAssets || []).map((asset) => ({
                    id: asset.id,
                    asset_tag: asset.asset_tag,
                    name: asset.name,
                }));
            },
            buildAssetFilterQueryString(page = null) {
                const params = new URLSearchParams();
                params.set('page', String(page ?? this.inventoryPage ?? 1));

                Object.entries(this.assetFilters || {}).forEach(([name, value]) => {
                    const trimmed = String(value || '').trim();

                    if (trimmed !== '') {
                        params.append(`filter[${name}]`, trimmed);
                    }
                });

                return params.toString();
            },
            inventoryPageNumbers() {
                return this.listPaginationWindow(this.inventoryPagination);
            },
            resolveInventoryPaginationLabel() {
                return this.resolveListPaginationLabel(this.inventoryPagination);
            },
            goToInventoryPage(page) {
                const targetPage = Number(page);

                if (
                    Number.isNaN(targetPage)
                    || targetPage < 1
                    || targetPage > Number(this.inventoryPagination.total_pages || 1)
                    || targetPage === this.inventoryPagination.page
                ) {
                    return;
                }

                this.inventoryPage = targetPage;
                this.fetchInventoryList(false);
            },
            async fetchInventoryList(resetPage = false) {
                if (!this.canManageAssets || this.assetFiltersLoading) {
                    return;
                }

                if (resetPage) {
                    this.inventoryPage = 1;
                }

                this.assetFiltersLoading = true;
                this.assetFiltersError = '';

                const query = this.buildAssetFilterQueryString(this.inventoryPage);
                const url = query ? `/api/assets?${query}` : '/api/assets';

                try {
                    const response = await fetch(url, this.apiFetchInit('GET'));
                    const result = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        this.assetFiltersError = this.apiErrorMessage(result, window.__i18n.inventory_filter_error);
                        return;
                    }

                    this.inventoryAssets = Array.isArray(result.data) ? result.data : [];
                    this.inventoryPagination = result.pagination || this.defaultListPagination();
                    this.inventoryPage = Number(this.inventoryPagination.page || 1);
                    this.syncInventoryAssetOptions();

                    const nextUrl = new URL(window.location.href);
                    nextUrl.search = query ? `?${query}` : '';
                    window.history.replaceState({}, '', nextUrl.toString());
                } catch (error) {
                    this.assetFiltersError = window.__i18n.inventory_filter_error;
                } finally {
                    this.assetFiltersLoading = false;
                }
            },
            async applyAssetFilters() {
                await this.fetchInventoryList(true);
            },
            resetAssetFilters() {
                const cleared = {};

                (this.assetFilterFields || []).forEach((field) => {
                    cleared[field.name] = '';
                });

                this.assetFilters = cleared;
                this.fetchInventoryList(true);
            },
            formatImportError(item) {
                const row = Number(item?.row ?? 0);
                const message = String(item?.message ?? '');

                return (window.__i18n.import_row_error || 'Row %d: %s')
                    .replace('%d', String(row))
                    .replace('%s', message);
            },
            setImportSummary(message, isError, errors = []) {
                this.importSummaryMessage = message;
                this.importSummaryIsError = isError;
                this.importSummaryErrors = Array.isArray(errors) ? errors : [];
            },
            async submitImport() {
                if (!this.importFile) {
                    this.importErrorMessage = window.__i18n.import_file_missing || 'Please select a file.';
                    return;
                }

                this.isImportSubmitting = true;
                this.importErrorMessage = '';
                this.importSuccessMessage = '';
                this.importResultErrors = [];

                const formData = new FormData();
                formData.append('file', this.importFile);

                const requestInit = this.apiFetchInit('POST');

                try {
                    const response = await fetch('/api/inventory/import', {
                        method: 'POST',
                        headers: requestInit.headers,
                        body: formData,
                    });
                    const result = await response.json().catch(() => ({}));
                    const data = result?.data ?? {};
                    const errors = [
                        ...(Array.isArray(data.errors) ? data.errors : []),
                        ...(Array.isArray(data.warnings) ? data.warnings : []),
                    ];
                    const imported = Number(data.imported ?? 0);
                    const updated = Number(data.updated ?? 0);
                    const failed = Number(data.failed ?? 0);
                    const message = this.apiErrorMessage(result, '');
                    const debugDetails = this.formatApiDebugDetails(result);

                    if (!response.ok) {
                        this.importErrorMessage = message || window.__i18n.import_all_failed;
                        this.importResultErrors = errors;
                        if (debugDetails) {
                            this.importResultErrors = [debugDetails, ...errors];
                        }
                        this.setImportSummary(this.importErrorMessage, true, this.importResultErrors);
                        return;
                    }

                    this.importSuccessMessage = message;
                    this.importResultErrors = errors;
                    this.setImportSummary(message, false, errors);

                    if (imported > 0 || updated > 0) {
                        window.setTimeout(() => window.location.reload(), 1200);
                    }
                } catch (error) {
                    this.importErrorMessage = window.__i18n.import_network_error;
                    this.setImportSummary(this.importErrorMessage, true, []);
                } finally {
                    this.isImportSubmitting = false;
                }
            },
            openEditModal(asset) {
                this.editErrorMessage = '';
                this.editAsset = asset;
                this.editForm = {
                    name: asset.name || '',
                    model: asset.model || '',
                    brand: asset.brand || '',
                    serial_number: asset.serial_number || '',
                    type: asset.type || asset.category_name || '',
                    status: asset.status || 'ready',
                    location: asset.location || asset.location_name || '',
                    building: asset.building || asset.location_building || '',
                    mac_address_1: asset.mac_address_1 || '',
                    mac_address_2: asset.mac_address_2 || '',
                };
                this.resetUserSearch();

                if (asset.assigned_to || asset.user_name) {
                    this.selectedUser = {
                        id: '',
                        name: asset.assigned_to || asset.user_name || '',
                        email: asset.assigned_to || '',
                        department: null,
                    };
                } else {
                    this.selectedUser = null;
                }

                this.isEditOpen = true;
            },
            closeEditModal() {
                if (this.isSubmitting) {
                    return;
                }

                this.isEditOpen = false;
                this.editAsset = null;
            },
            async openDetailModal(asset) {
                this.detailAsset = asset;
                this.detailQrSvg = window.__assetQrCodes?.[asset.id] || '';
                this.assetHistory = [];
                this.historyError = '';
                this.historyLoading = true;
                this.assetLicenses = [];
                this.assetLicensesError = '';
                this.assetLicensesLoading = true;
                this.isDetailOpen = true;

                try {
                    const [historyResponse, licensesResponse] = await Promise.all([
                        fetch(`/api/assets/${asset.id}/history`, {
                            headers: { 'Accept': 'application/json' },
                        }),
                        fetch(`/api/assets/${asset.id}/licenses`, {
                            headers: { 'Accept': 'application/json' },
                        }),
                    ]);
                    const historyResult = await historyResponse.json();
                    const licensesResult = await licensesResponse.json();

                    if (!historyResponse.ok) {
                        this.historyError = historyResult.message || window.__i18n.history_error;
                    } else {
                        this.assetHistory = Array.isArray(historyResult.data) ? historyResult.data : [];
                    }

                    if (!licensesResponse.ok) {
                        this.assetLicensesError = licensesResult.message || window.__i18n.asset_licenses_error;
                    } else {
                        this.assetLicenses = Array.isArray(licensesResult.data) ? licensesResult.data : [];
                    }
                } catch (error) {
                    this.historyError = window.__i18n.history_error;
                    this.assetLicensesError = window.__i18n.asset_licenses_error;
                } finally {
                    this.historyLoading = false;
                    this.assetLicensesLoading = false;
                }
            },
            closeDetailModal() {
                this.isDetailOpen = false;
                this.detailAsset = null;
                this.detailQrSvg = '';
                this.assetHistory = [];
                this.historyError = '';
                this.historyLoading = false;
                this.assetLicenses = [];
                this.assetLicensesError = '';
                this.assetLicensesLoading = false;
            },
            printAssetLabel() {
                if (!this.detailAsset) {
                    return;
                }

                const qrSvg = window.__assetQrCodes?.[this.detailAsset.id] || this.detailQrSvg || '';
                const assetTag = this.detailAsset.asset_tag || '';
                const categoryName = this.detailAsset.category_name || '';
                const printWindow = window.open('', '_blank', 'width=420,height=320');

                if (!printWindow) {
                    return;
                }

                printWindow.document.write(`<!DOCTYPE html>
<html lang="${window.__i18n.locale || 'tr'}">
<head>
    <meta charset="UTF-8">
    <title>${assetTag}</title>
    <style>
        @page { size: 62mm 29mm; margin: 2mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .label {
            width: 58mm;
            min-height: 25mm;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 1.5mm;
        }
        .tag {
            font-size: 11pt;
            font-weight: 700;
            line-height: 1.1;
            letter-spacing: 0.02em;
        }
        .category {
            font-size: 8pt;
            line-height: 1.2;
            color: #444;
        }
        .qr svg {
            display: block;
            width: 18mm;
            height: 18mm;
        }
        @media print {
            body { margin: 0; }
            .label { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="label">
        <div class="tag">${assetTag}</div>
        <div class="category">${categoryName}</div>
        <div class="qr">${qrSvg}</div>
    </div>
    <script>
        window.addEventListener('load', function () {
            window.focus();
            window.print();
        });
    <\/script>
</body>
</html>`);
                printWindow.document.close();
            },
            resolveHistoryAction(action) {
                const labels = {
                    created: window.__i18n.history_action_created,
                    assigned: window.__i18n.history_action_assigned,
                    unassigned: window.__i18n.history_action_unassigned,
                    status_change: window.__i18n.history_action_status_change,
                    updated: window.__i18n.history_action_updated,
                    offboarded: window.__i18n.history_action_offboarded,
                    returned: window.__i18n.history_action_returned,
                    transferred: window.__i18n.history_action_transferred,
                    location_moved: window.__i18n.history_action_location_moved,
                };

                return labels[action] || action;
            },
            formatHistoryDate(value) {
                if (!value) {
                    return '';
                }

                const date = new Date(value);

                if (Number.isNaN(date.getTime())) {
                    return value;
                }

                return date.toLocaleString(window.__i18n.locale === 'en' ? 'en-US' : 'tr-TR');
            },
            resetDynamicFields() {
                this.dynamicFields = [];
                this.dynamicValues = {};
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

                const result = await this.fetchPersonnelSearchOptions(this.userSearchQuery);
                this.userSearchResults = result.data;
                this.userSearchError = result.error;
                this.showUserResults = true;
                this.userSearchLoading = false;
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
            loadCategoryFields(categoryId, existingProperties = null) {
                const normalizedId = String(categoryId || '');
                this.resetDynamicFields();

                const defaultMacFields = [
                    { name: 'mac_adresi_1', label: window.__i18n.label_mac_address_1 || 'MAC Adresi 1', type: 'text' },
                    { name: 'mac_adresi_2', label: window.__i18n.label_mac_address_2 || 'MAC Adresi 2', type: 'text' },
                ];
                const globalFields = Array.isArray(this.globalCustomFields) ? this.globalCustomFields : [];
                const categorySpecificFields = normalizedId === ''
                    ? []
                    : (this.categoryFields[normalizedId] || this.categoryFields[Number(normalizedId)] || []);

                const mergedFields = [
                    ...defaultMacFields,
                    ...(Array.isArray(categorySpecificFields) ? categorySpecificFields : []),
                    ...globalFields,
                ];

                const seen = new Set();
                this.dynamicFields = mergedFields.filter((field) => {
                    if (!field || !field.name || seen.has(field.name)) {
                        return false;
                    }

                    seen.add(field.name);
                    return true;
                });

                this.dynamicFields.forEach((field) => {
                    const existingValue = existingProperties && Object.prototype.hasOwnProperty.call(existingProperties, field.name)
                        ? existingProperties[field.name]
                        : null;

                    this.dynamicValues[field.name] = existingValue === null || existingValue === undefined
                        ? ''
                        : String(existingValue);
                });
            },
            resolveFieldLabel(field) {
                if (window.__i18n.locale === 'en' && field.label_en) {
                    return field.label_en;
                }

                return field.label || field.name;
            },
            formatLocationLabel(location) {
                if (!location) {
                    return '';
                }

                const name = String(location.name || '').trim();
                const building = String(location.building || '').trim();

                if (name === '') {
                    return '';
                }

                if (building === '') {
                    return name;
                }

                return `${building} / ${name}`;
            },
            buildAddPayload() {
                const payload = {
                    name: this.form.name.trim(),
                    status: this.form.status,
                };

                ['model', 'brand', 'serial_number', 'type', 'location', 'building', 'mac_address_1', 'mac_address_2'].forEach((field) => {
                    const value = String(this.form[field] || '').trim();

                    if (value !== '') {
                        payload[field] = value;
                    }
                });

                if (this.selectedUser?.id) {
                    payload.personnel_id = Number(this.selectedUser.id);
                }

                return payload;
            },
            buildEditPayload() {
                const payload = {
                    name: this.editForm.name.trim(),
                    status: this.editForm.status,
                };

                ['model', 'brand', 'serial_number', 'type', 'location', 'building', 'mac_address_1', 'mac_address_2'].forEach((field) => {
                    payload[field] = String(this.editForm[field] || '').trim();
                });

                if (this.selectedUser?.id) {
                    payload.personnel_id = Number(this.selectedUser.id);
                } else {
                    payload.assigned_to = this.selectedUser
                        ? String(this.selectedUser.name || this.selectedUser.email || '').trim()
                        : '';
                }

                return payload;
            },
            async submitAddForm() {
                this.isSubmitting = true;
                this.addErrorMessage = '';

                try {
                    const response = await fetch('/api/assets', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(this.buildAddPayload()),
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        if (result.errors) {
                            this.addErrorMessage = Object.values(result.errors)
                                .flat()
                                .join(' ');
                        } else {
                            this.addErrorMessage = result.message || window.__i18n.create_error;
                        }

                        return;
                    }

                    window.location.reload();
                } catch (error) {
                    this.addErrorMessage = window.__i18n.network_error;
                } finally {
                    this.isSubmitting = false;
                }
            },
            async submitEditForm() {
                if (!this.editAsset?.id) {
                    return;
                }

                this.isSubmitting = true;
                this.editErrorMessage = '';

                try {
                    const response = await fetch(`/api/assets/${this.editAsset.id}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(this.buildEditPayload()),
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        if (result.errors) {
                            this.editErrorMessage = Object.values(result.errors)
                                .flat()
                                .join(' ');
                        } else {
                            this.editErrorMessage = result.message || window.__i18n.update_error;
                        }

                        return;
                    }

                    window.location.reload();
                } catch (error) {
                    this.editErrorMessage = window.__i18n.network_error;
                } finally {
                    this.isSubmitting = false;
                }
            },
            addCustomField() {
                this.settingsForm.custom_fields.push({
                    name: '',
                    label: '',
                    type: 'text',
                });
            },
            removeCustomField(index) {
                this.settingsForm.custom_fields.splice(index, 1);
            },
            generateCustomFieldCode(label) {
                const turkishMap = {
                    ç: 'c',
                    Ç: 'c',
                    ğ: 'g',
                    Ğ: 'g',
                    ı: 'i',
                    I: 'i',
                    İ: 'i',
                    ö: 'o',
                    Ö: 'o',
                    ş: 's',
                    Ş: 's',
                    ü: 'u',
                    Ü: 'u',
                };

                let normalized = String(label ?? '').trim();

                normalized = normalized
                    .split('')
                    .map((character) => turkishMap[character] ?? character)
                    .join('')
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9\s_]/g, '')
                    .replace(/\s+/g, '_')
                    .replace(/_+/g, '_')
                    .replace(/^_|_$/g, '');

                if (normalized === '') {
                    return 'field';
                }

                if (/^[0-9]/.test(normalized)) {
                    normalized = `field_${normalized}`;
                }

                return normalized;
            },
            syncCustomFieldCode(index) {
                const field = this.settingsForm.custom_fields[index];

                if (!field) {
                    return;
                }

                field.name = this.generateCustomFieldCode(field.label);
            },
            prepareCustomFieldsForSave() {
                let nextId = 1;

                this.settingsForm.custom_fields.forEach((field) => {
                    if (field?.id && Number.isFinite(Number(field.id))) {
                        nextId = Math.max(nextId, Number(field.id) + 1);
                    }
                });

                return this.settingsForm.custom_fields
                    .filter((field) => String(field?.label ?? '').trim() !== '')
                    .map((field) => {
                        const id = field?.id && Number.isFinite(Number(field.id)) ? Number(field.id) : nextId++;

                        return {
                            id,
                            name: field?.name && String(field.name).trim() !== ''
                                ? String(field.name).trim()
                                : this.generateCustomFieldCode(field.label),
                            label: String(field.label).trim(),
                            type: field.type || 'text',
                        };
                    });
            },
            async fetchCategories() {
                if (!this.canAccessSettings) {
                    return;
                }

                this.categoriesLoading = true;
                this.categoriesError = '';

                try {
                    const response = await fetch('/api/categories', {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.categoriesError = result.message || window.__i18n.categories_fetch_error;
                        this.categories = [];
                        return;
                    }

                    this.categories = Array.isArray(result.data) ? result.data : [];
                    this.syncCategoryFieldMap();
                } catch (error) {
                    this.categoriesError = window.__i18n.categories_network_error;
                    this.categories = [];
                } finally {
                    this.categoriesLoading = false;
                }
            },
            syncCategoryFieldMap() {
                const map = {};

                this.categories.forEach((category) => {
                    if (!category?.id) {
                        return;
                    }

                    map[String(category.id)] = Array.isArray(category.fields) ? category.fields : [];
                });

                this.categoryFields = map;
                window.__categoryFields = map;
            },
            resolveCategoryFieldCount(category) {
                const count = Array.isArray(category?.fields) ? category.fields.length : 0;

                return (window.__i18n.category_field_count || '%d alan').replace('%d', String(count));
            },
            openCategoryModal(category = null) {
                this.categoryFormError = '';
                this.categoriesSuccessMessage = '';

                const categoryId = category?.id != null ? Number(category.id) : null;
                this.editingCategoryId = Number.isInteger(categoryId) && categoryId > 0 ? categoryId : null;

                if (this.editingCategoryId) {
                    this.categoryForm.id = this.editingCategoryId;
                    this.categoryForm.name = category?.name || '';
                    this.categoryForm.fields = Array.isArray(category?.fields)
                        ? JSON.parse(JSON.stringify(category.fields)).map((field) => ({
                            label: field?.label || '',
                            type: field?.type || 'text',
                            label_en: field?.label_en || '',
                            optionsText: Array.isArray(field?.options)
                                ? field.options.join(', ')
                                : (typeof field?.options === 'string' ? field.options : ''),
                        }))
                        : [];
                } else {
                    this.categoryForm.id = null;
                    this.categoryForm.name = '';
                    this.categoryForm.fields = [];
                }

                this.isCategoryModalOpen = true;
            },
            closeCategoryModal() {
                if (this.isCategorySubmitting) {
                    return;
                }

                this.isCategoryModalOpen = false;
                this.editingCategoryId = null;
                this.categoryForm.id = null;
                this.categoryFormError = '';
            },
            addCategoryField() {
                this.categoryForm.fields.push({
                    label: '',
                    type: 'text',
                    optionsText: '',
                });
            },
            removeCategoryField(index) {
                this.categoryForm.fields.splice(index, 1);
            },
            buildCategoryPayload() {
                const payload = {
                    name: this.categoryForm.name.trim(),
                    fields: this.categoryForm.fields
                        .filter((field) => field && field.label?.trim())
                        .map((field) => {
                            const entry = {
                                label: String(field.label || '').trim(),
                                type: field.type || 'text',
                            };

                            if (field.type === 'dropdown') {
                                const optionsText = String(field.optionsText || '').trim();

                                if (optionsText !== '') {
                                    entry.options = optionsText;
                                }
                            }

                            return entry;
                        }),
                };

                const categoryId = this.editingCategoryId != null
                    ? Number(this.editingCategoryId)
                    : Number(this.categoryForm.id);

                if (Number.isInteger(categoryId) && categoryId > 0) {
                    payload.id = categoryId;
                }

                return payload;
            },
            async submitCategoryForm() {
                this.isCategorySubmitting = true;
                this.categoryFormError = '';
                this.categoriesSuccessMessage = '';

                const payload = this.buildCategoryPayload();
                const categoryId = this.editingCategoryId != null
                    ? Number(this.editingCategoryId)
                    : Number(this.categoryForm.id);
                const isEdit = Number.isInteger(categoryId) && categoryId > 0;
                const url = isEdit ? `/api/categories/${categoryId}` : '/api/categories';
                const method = isEdit ? 'PUT' : 'POST';

                try {
                    const response = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });
                    const result = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        this.categoryFormError = this.apiErrorMessage(
                            result,
                            isEdit ? window.__i18n.category_update_error : window.__i18n.category_create_error
                        );
                        return;
                    }

                    this.isCategoryModalOpen = false;
                    this.editingCategoryId = null;
                    this.categoryForm.id = null;
                    this.categoriesSuccessMessage = this.apiErrorMessage(
                        result,
                        isEdit ? window.__i18n.category_update_success : window.__i18n.category_create_success
                    );
                    await this.fetchCategories();
                    this.persistDashboardView();
                } catch (error) {
                    this.categoryFormError = window.__i18n.categories_network_error;
                } finally {
                    this.isCategorySubmitting = false;
                }
            },
            async deleteCategory(category) {
                const categoryId = Number(category?.id);

                if (!Number.isInteger(categoryId) || categoryId <= 0) {
                    return;
                }

                if (!window.confirm(window.__i18n.category_delete_confirm)) {
                    return;
                }

                this.categoriesSuccessMessage = '';
                this.categoriesError = '';

                try {
                    const response = await fetch(`/api/categories/${categoryId}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const result = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        this.categoriesError = this.apiErrorMessage(
                            result,
                            window.__i18n.category_delete_error
                        );
                        return;
                    }

                    this.categoriesSuccessMessage = this.apiErrorMessage(
                        result,
                        window.__i18n.category_delete_success
                    );
                    await this.fetchCategories();
                    this.persistDashboardView();
                } catch (error) {
                    this.categoriesError = window.__i18n.categories_network_error;
                }
            },
            async fetchLocations() {
                if (!this.canManageAssets) {
                    return;
                }

                this.locationsLoading = true;
                this.locationsError = '';

                try {
                    const response = await fetch('/api/locations', {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.locationsError = result.error || result.message || window.__i18n.locations_fetch_error;
                        this.locations = [];
                        return;
                    }

                    this.locations = Array.isArray(result.data) ? result.data : [];
                } catch (error) {
                    this.locationsError = window.__i18n.locations_network_error;
                    this.locations = [];
                } finally {
                    this.locationsLoading = false;
                }
            },
            openLocationModal(location = null) {
                this.locationFormError = '';
                this.locationsSuccessMessage = '';

                if (location) {
                    this.locationForm = {
                        id: location.id,
                        name: location.name || '',
                        building: location.building || '',
                        description: location.description || '',
                    };
                } else {
                    this.locationForm = {
                        id: null,
                        name: '',
                        building: '',
                        description: '',
                    };
                }

                this.isLocationModalOpen = true;
            },
            closeLocationModal() {
                if (this.isLocationSubmitting) {
                    return;
                }

                this.isLocationModalOpen = false;
                this.locationFormError = '';
            },
            buildLocationPayload() {
                return {
                    name: this.locationForm.name.trim(),
                    building: this.locationForm.building.trim(),
                    description: this.locationForm.description.trim(),
                };
            },
            async submitLocationForm() {
                this.isLocationSubmitting = true;
                this.locationFormError = '';
                this.locationsSuccessMessage = '';

                const payload = this.buildLocationPayload();
                const isEdit = Boolean(this.locationForm.id);
                const url = isEdit ? `/api/locations/${this.locationForm.id}` : '/api/locations';
                const method = isEdit ? 'PUT' : 'POST';

                try {
                    const response = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.locationFormError = result.message || (isEdit
                            ? window.__i18n.location_update_error
                            : window.__i18n.location_create_error);
                        return;
                    }

                    this.isLocationModalOpen = false;
                    this.locationsSuccessMessage = result.message || (isEdit
                        ? window.__i18n.location_update_success
                        : window.__i18n.location_create_success);
                    await this.fetchLocations();
                } catch (error) {
                    this.locationFormError = window.__i18n.locations_network_error;
                } finally {
                    this.isLocationSubmitting = false;
                }
            },
            async deleteLocation(location) {
                if (!location?.id || !window.confirm(window.__i18n.location_delete_confirm)) {
                    return;
                }

                this.locationsSuccessMessage = '';
                this.locationsError = '';

                try {
                    const response = await fetch(`/api/locations/${location.id}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                        },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.locationsError = result.error || result.message || window.__i18n.location_delete_error;
                        return;
                    }

                    this.locationsSuccessMessage = result.message || window.__i18n.location_delete_success;
                    await this.fetchLocations();
                } catch (error) {
                    this.locationsError = window.__i18n.locations_network_error;
                }
            },
            async fetchTicketCategories() {
                if (!this.canManageAssets) {
                    return;
                }

                this.ticketCategoriesLoading = true;
                this.ticketCategoriesError = '';

                try {
                    const response = await fetch('/api/ticket-categories', {
                        headers: { Accept: 'application/json' },
                    });
                    const result = await this.parseApiResponse(response);

                    if (!response.ok) {
                        this.ticketCategoriesError = this.apiErrorMessage(result, window.__i18n.ticket_category_create_error);
                        return;
                    }

                    this.ticketCategories = Array.isArray(result.data) ? result.data : [];
                } catch (error) {
                    this.ticketCategoriesError = window.__i18n.helpdesk_network_error;
                } finally {
                    this.ticketCategoriesLoading = false;
                }
            },
            openTicketCategoryModal(category = null) {
                this.ticketCategoryForm = category
                    ? {
                        id: category.id,
                        name: category.name || '',
                        color_code: category.color_code || '#6366f1',
                    }
                    : {
                        id: null,
                        name: '',
                        color_code: '#6366f1',
                    };
                this.ticketCategoryFormError = '';
                this.ticketCategoriesSuccessMessage = '';
                this.isTicketCategoryModalOpen = true;
            },
            closeTicketCategoryModal() {
                if (this.isTicketCategorySubmitting) {
                    return;
                }

                this.isTicketCategoryModalOpen = false;
            },
            async submitTicketCategoryForm() {
                this.isTicketCategorySubmitting = true;
                this.ticketCategoryFormError = '';
                this.ticketCategoriesSuccessMessage = '';

                const isEdit = Boolean(this.ticketCategoryForm.id);
                const url = isEdit
                    ? `/api/ticket-categories/${this.ticketCategoryForm.id}`
                    : '/api/ticket-categories';
                const method = isEdit ? 'PUT' : 'POST';

                try {
                    const response = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            name: this.ticketCategoryForm.name,
                            color_code: this.ticketCategoryForm.color_code,
                        }),
                    });
                    const result = await this.parseApiResponse(response);

                    if (!response.ok) {
                        this.ticketCategoryFormError = this.apiErrorMessage(
                            result,
                            isEdit ? window.__i18n.ticket_category_update_error : window.__i18n.ticket_category_create_error
                        );
                        return;
                    }

                    this.isTicketCategoryModalOpen = false;
                    this.ticketCategoriesSuccessMessage = this.apiErrorMessage(
                        result,
                        isEdit ? window.__i18n.ticket_category_update_success : window.__i18n.ticket_category_create_success
                    );
                    await this.fetchTicketCategories();
                } catch (error) {
                    this.ticketCategoryFormError = window.__i18n.helpdesk_network_error;
                } finally {
                    this.isTicketCategorySubmitting = false;
                }
            },
            async deleteTicketCategory(category) {
                if (!category?.id || !window.confirm(window.__i18n.ticket_category_delete_confirm)) {
                    return;
                }

                this.ticketCategoriesSuccessMessage = '';
                this.ticketCategoriesError = '';

                try {
                    const response = await fetch(`/api/ticket-categories/${category.id}`, {
                        method: 'DELETE',
                        headers: { Accept: 'application/json' },
                    });
                    const result = await this.parseApiResponse(response);

                    if (!response.ok) {
                        this.ticketCategoriesError = this.apiErrorMessage(result, window.__i18n.ticket_category_update_error);
                        return;
                    }

                    this.ticketCategoriesSuccessMessage = this.apiErrorMessage(result, window.__i18n.ticket_category_delete_success);
                    await this.fetchTicketCategories();
                } catch (error) {
                    this.ticketCategoriesError = window.__i18n.helpdesk_network_error;
                }
            },
            async fetchReports() {
                if (!this.canAccessSettings) {
                    return;
                }

                this.reportsLoading = true;
                this.reportsError = '';

                try {
                    const response = await fetch('/api/reports/helpdesk', this.apiFetchInit('GET'));
                    const result = await this.parseApiResponse(response);

                    if (!response.ok) {
                        this.reportsError = this.apiErrorMessage(result, window.__i18n.reports_fetch_error);
                        return;
                    }

                    this.reportsStats = result.data || null;
                } catch (error) {
                    this.reportsError = window.__i18n.helpdesk_network_error;
                } finally {
                    this.reportsLoading = false;
                }
            },
            async fetchLicenses(resetPage = false) {
                if (!this.canManageAssets || this.licensesLoading) {
                    return;
                }

                if (resetPage) {
                    this.licensesPage = 1;
                }

                this.licensesLoading = true;
                this.licensesError = '';

                const query = this.buildLicenseFilterQueryString(this.licensesPage);
                const url = query ? `/api/licenses?${query}` : '/api/licenses';

                try {
                    const response = await fetch(url, this.apiFetchInit('GET'));
                    const result = await response.json();

                    if (!response.ok) {
                        const msg = result.message || window.__i18n.licenses_filter_error;
                        this.licensesError = msg;
                        this.licenses = [];
                        if (response.status === 500) {
                            alert('Hata: ' + msg);
                        }
                        return;
                    }

                    this.licenses = Array.isArray(result.data) ? result.data : [];
                    this.licensesPagination = result.pagination || this.defaultListPagination();
                    this.licensesPage = Number(this.licensesPagination.page || 1);
                } catch (error) {
                    this.licensesError = window.__i18n.licenses_network_error;
                    this.licenses = [];
                    alert(window.__i18n.licenses_network_error || 'Lisanslar yüklenemedi.');
                } finally {
                    this.licensesLoading = false;
                }
            },
            resolveLicenseFilterLabel(field) {
                if (field?.label_key && window.__i18n[field.label_key]) {
                    return window.__i18n[field.label_key];
                }

                return field?.label || field?.name || '';
            },
            resolveLicenseFilterOptionLabel(option) {
                if (option?.label_key && window.__i18n[option.label_key]) {
                    return window.__i18n[option.label_key];
                }

                return option?.label || option?.value || '';
            },
            hasActiveLicenseFilters() {
                return Object.values(this.licenseFilters || {}).some((value) => String(value || '').trim() !== '');
            },
            buildLicenseFilterQueryString(page = null) {
                const params = new URLSearchParams();
                params.set('page', String(page ?? this.licensesPage ?? 1));

                Object.entries(this.licenseFilters || {}).forEach(([name, value]) => {
                    const trimmed = String(value || '').trim();

                    if (trimmed !== '') {
                        params.append(`filter[${name}]`, trimmed);
                    }
                });

                return params.toString();
            },
            async applyLicenseFilters() {
                await this.fetchLicenses(true);
            },
            resetLicenseFilters() {
                const cleared = {};

                (this.licenseFilterFields || []).forEach((field) => {
                    cleared[field.name] = '';
                });

                this.licenseFilters = cleared;
                this.fetchLicenses(true);
            },
            licensesPageNumbers() {
                return this.listPaginationWindow(this.licensesPagination);
            },
            resolveLicensesPaginationLabel() {
                return this.resolveListPaginationLabel(this.licensesPagination);
            },
            goToLicensesPage(page) {
                const targetPage = Number(page);

                if (
                    Number.isNaN(targetPage)
                    || targetPage < 1
                    || targetPage > Number(this.licensesPagination.total_pages || 1)
                    || targetPage === this.licensesPagination.page
                ) {
                    return;
                }

                this.licensesPage = targetPage;
                this.fetchLicenses();
            },
            async fetchIpNetworks() {
                if (!this.canManageAssets) {
                    return;
                }

                this.ipNetworksLoading = true;
                this.ipNetworksError = '';

                try {
                    const response = await fetch('/api/ip-networks', {
                        headers: { Accept: 'application/json' },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.ipNetworksError = result.message || window.__i18n.ipam_fetch_error;
                        this.ipNetworks = [];
                        return;
                    }

                    this.ipNetworks = Array.isArray(result.data) ? result.data : [];
                } catch (error) {
                    this.ipNetworksError = window.__i18n.ipam_network_error;
                    this.ipNetworks = [];
                } finally {
                    this.ipNetworksLoading = false;
                }
            },
            async openIpNetworkAddresses(network) {
                this.selectedIpNetwork = network;
                this.ipamSubView = 'addresses';
                this.ipAddressStatusFilter = 'all';
                this.selectedIpAddressIds = [];
                await this.fetchIpAddresses(network.id);
            },
            backToIpNetworks() {
                this.ipamSubView = 'networks';
                this.selectedIpNetwork = null;
                this.ipAddresses = [];
                this.ipAddressesError = '';
                this.selectedIpAddressIds = [];
            },
            async fetchIpAddresses(networkId) {
                this.ipAddressesLoading = true;
                this.ipAddressesError = '';

                try {
                    const response = await fetch(`/api/ip-networks/${networkId}/addresses`, {
                        headers: { Accept: 'application/json' },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.ipAddressesError = result.message || window.__i18n.ipam_fetch_error;
                        this.ipAddresses = [];
                        return;
                    }

                    const payload = result.data || {};
                    this.selectedIpNetwork = payload.network || this.selectedIpNetwork;
                    this.ipAddresses = Array.isArray(payload.addresses) ? payload.addresses : [];
                } catch (error) {
                    this.ipAddressesError = window.__i18n.ipam_network_error;
                    this.ipAddresses = [];
                } finally {
                    this.ipAddressesLoading = false;
                }
            },
            formatIpUtilization(network) {
                const template = window.__i18n.ipam_utilization || ':used/:capacity';
                return template
                    .replace(':used', String(network.used_ips ?? 0))
                    .replace(':capacity', String(network.capacity_ips ?? 0));
            },
            setIpAddressStatusFilter(value) {
                this.ipAddressStatusFilter = value;
            },
            ipAddressStatusLabel(status) {
                const map = {
                    available: window.__i18n.ipam_status_available,
                    reserved: window.__i18n.ipam_status_reserved,
                    assigned: window.__i18n.ipam_status_assigned,
                    dhcp: window.__i18n.ipam_status_dhcp,
                    broken: window.__i18n.ipam_status_broken,
                };

                return map[status] || status;
            },
            ipAddressStatusClass(status) {
                const map = {
                    available: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                    assigned: 'bg-rose-50 text-rose-700 ring-rose-600/20',
                    reserved: 'bg-amber-50 text-amber-700 ring-amber-600/20',
                    dhcp: 'bg-sky-50 text-sky-700 ring-sky-600/20',
                    broken: 'bg-zinc-200 text-zinc-700 ring-zinc-400/30',
                };

                return map[status] || 'bg-zinc-100 text-zinc-700 ring-zinc-300';
            },
            ipAddressGridClass(status) {
                const map = {
                    available: 'border-emerald-200 bg-emerald-50 text-emerald-800',
                    assigned: 'border-rose-200 bg-rose-50 text-rose-800',
                    reserved: 'border-amber-200 bg-amber-50 text-amber-800',
                    dhcp: 'border-sky-200 bg-sky-50 text-sky-800',
                    broken: 'border-zinc-300 bg-zinc-100 text-zinc-700',
                };

                return map[status] || 'border-zinc-200 bg-white text-zinc-700';
            },
            isIpAddressSelected(addressId) {
                return (this.selectedIpAddressIds || []).includes(Number(addressId));
            },
            toggleIpAddressSelection(addressId, checked) {
                const id = Number(addressId);
                const current = new Set((this.selectedIpAddressIds || []).map(Number));

                if (checked) {
                    current.add(id);
                } else {
                    current.delete(id);
                }

                this.selectedIpAddressIds = Array.from(current);
            },
            toggleSelectAllIpAddresses(checked) {
                const visibleIds = (this.filteredIpAddresses || []).map((address) => Number(address.id));
                const current = new Set((this.selectedIpAddressIds || []).map(Number));

                if (checked) {
                    visibleIds.forEach((id) => current.add(id));
                } else {
                    visibleIds.forEach((id) => current.delete(id));
                }

                this.selectedIpAddressIds = Array.from(current);
            },
            bulkIpEditSelectedCountLabel() {
                const template = window.__i18n.ipam_bulk_edit_selected_count || '%d IP selected';

                return template.replace('%d', String(this.selectedIpAddressCount || 0));
            },
            openBulkIpEditModal() {
                if (this.selectedIpAddressCount < 2) {
                    this.ipAddressesError = window.__i18n.ipam_bulk_edit_select_minimum;
                    return;
                }

                const confirmTemplate = window.__i18n.ipam_bulk_edit_confirm
                    || '%d adet seçili IP adresi toplu olarak güncellenecektir. Devam etmek istiyor musunuz?';
                const confirmMessage = confirmTemplate.replace('%d', String(this.selectedIpAddressCount || 0));

                if (!window.confirm(confirmMessage)) {
                    return;
                }

                this.ipBulkEditFormError = '';
                this.ipBulkEditForm = {
                    applyStatus: true,
                    status: 'available',
                    applyNotes: false,
                    notes: '',
                    applyDepartment: false,
                    department: '',
                };
                this.isIpBulkEditOpen = true;
            },
            closeBulkIpEditModal() {
                if (this.isIpBulkEditSubmitting) {
                    return;
                }

                this.isIpBulkEditOpen = false;
                this.ipBulkEditFormError = '';
            },
            async submitBulkIpEdit() {
                if (this.selectedIpAddressCount < 2) {
                    this.ipBulkEditFormError = window.__i18n.ipam_bulk_edit_select_minimum;
                    return;
                }

                if (!this.ipBulkEditForm.applyStatus && !this.ipBulkEditForm.applyNotes && !this.ipBulkEditForm.applyDepartment) {
                    this.ipBulkEditFormError = window.__i18n.ipam_bulk_edit_no_fields;
                    return;
                }

                this.isIpBulkEditSubmitting = true;
                this.ipBulkEditFormError = '';

                try {
                    const response = await fetch('/api/ip-addresses/bulk-update', this.apiFetchJsonInit('POST', {
                        ids: this.selectedIpAddressIds,
                        network_id: this.selectedIpNetwork?.id ?? null,
                        fields: {
                            status: {
                                enabled: this.ipBulkEditForm.applyStatus,
                                value: this.ipBulkEditForm.status,
                            },
                            notes: {
                                enabled: this.ipBulkEditForm.applyNotes,
                                value: this.ipBulkEditForm.notes,
                            },
                            department: {
                                enabled: this.ipBulkEditForm.applyDepartment,
                                value: this.ipBulkEditForm.department,
                            },
                        },
                    }));
                    const result = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        this.ipBulkEditFormError = this.apiErrorMessage(result, window.__i18n.ipam_bulk_update_error);
                        return;
                    }

                    this.isIpBulkEditOpen = false;
                    this.selectedIpAddressIds = [];
                    this.ipNetworksSuccessMessage = result.message || window.__i18n.ipam_bulk_update_success;

                    if (this.selectedIpNetwork?.id) {
                        await this.fetchIpAddresses(this.selectedIpNetwork.id);
                        await this.fetchIpNetworks();
                    }
                } catch (error) {
                    this.ipBulkEditFormError = window.__i18n.ipam_network_error;
                } finally {
                    this.isIpBulkEditSubmitting = false;
                }
            },
            openIpNetworkModal(network = null) {
                this.ipNetworkFormError = '';
                this.ipNetworkForm = network ? {
                    id: network.id,
                    name: network.name || '',
                    network_address: network.network_address || '',
                    cidr: network.cidr || 24,
                    gateway: network.gateway || '',
                    vlan_id: network.vlan_id || '',
                    description: network.description || '',
                    auto_generate: false,
                } : {
                    id: null,
                    name: '',
                    network_address: '',
                    cidr: 24,
                    gateway: '',
                    vlan_id: '',
                    description: '',
                    auto_generate: true,
                };
                this.isIpNetworkModalOpen = true;
            },
            closeIpNetworkModal() {
                if (this.isIpNetworkSubmitting) {
                    return;
                }

                this.isIpNetworkModalOpen = false;
            },
            async submitIpNetworkForm() {
                this.isIpNetworkSubmitting = true;
                this.ipNetworkFormError = '';

                const isEdit = Boolean(this.ipNetworkForm.id);
                const url = isEdit ? `/api/ip-networks/${this.ipNetworkForm.id}` : '/api/ip-networks';
                const method = isEdit ? 'PUT' : 'POST';
                const payload = isEdit ? {
                    name: this.ipNetworkForm.name,
                    gateway: this.ipNetworkForm.gateway,
                    vlan_id: this.ipNetworkForm.vlan_id,
                    description: this.ipNetworkForm.description,
                } : {
                    name: this.ipNetworkForm.name,
                    network_address: this.ipNetworkForm.network_address,
                    cidr: Number(this.ipNetworkForm.cidr),
                    gateway: this.ipNetworkForm.gateway,
                    vlan_id: this.ipNetworkForm.vlan_id,
                    description: this.ipNetworkForm.description,
                    auto_generate: Boolean(this.ipNetworkForm.auto_generate),
                };

                try {
                    const response = await fetch(url, {
                        method,
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.ipNetworkFormError = result.message || window.__i18n.ipam_network_error;
                        return;
                    }

                    this.isIpNetworkModalOpen = false;
                    this.ipNetworksSuccessMessage = result.message || (isEdit
                        ? window.__i18n.ipam_network_update_success
                        : window.__i18n.ipam_network_create_success);
                    await this.fetchIpNetworks();
                } catch (error) {
                    this.ipNetworkFormError = window.__i18n.ipam_network_error;
                } finally {
                    this.isIpNetworkSubmitting = false;
                }
            },
            async deleteIpNetwork(network) {
                if (!network?.id || !window.confirm(window.__i18n.ipam_network_delete_confirm)) {
                    return;
                }

                try {
                    const response = await fetch(`/api/ip-networks/${network.id}`, {
                        method: 'DELETE',
                        headers: { Accept: 'application/json' },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.ipNetworksError = result.message || window.__i18n.ipam_network_error;
                        return;
                    }

                    this.ipNetworksSuccessMessage = result.message || window.__i18n.ipam_network_delete_success;

                    if (this.selectedIpNetwork?.id === network.id) {
                        this.backToIpNetworks();
                    }

                    await this.fetchIpNetworks();
                } catch (error) {
                    this.ipNetworksError = window.__i18n.ipam_network_error;
                }
            },
            openIpAddressModal(address) {
                this.ipAddressFormError = '';
                this.ipAddressForm = {
                    id: address.id,
                    ip_address: address.ip_address,
                    status: address.status,
                    hostname: address.hostname || '',
                    mac_address: address.mac_address || '',
                    notes: address.notes || '',
                };
                this.isIpAddressModalOpen = true;
            },
            closeIpAddressModal() {
                if (this.isIpAddressSubmitting) {
                    return;
                }

                this.isIpAddressModalOpen = false;
            },
            async submitIpAddressForm() {
                this.isIpAddressSubmitting = true;
                this.ipAddressFormError = '';

                try {
                    const response = await fetch(`/api/ip-addresses/${this.ipAddressForm.id}`, {
                        method: 'PUT',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            status: this.ipAddressForm.status,
                            hostname: this.ipAddressForm.hostname,
                            mac_address: this.ipAddressForm.mac_address,
                            notes: this.ipAddressForm.notes,
                        }),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.ipAddressFormError = result.message || window.__i18n.ipam_network_error;
                        return;
                    }

                    this.isIpAddressModalOpen = false;
                    this.ipNetworksSuccessMessage = result.message || window.__i18n.ipam_address_update_success;

                    if (this.selectedIpNetwork?.id) {
                        await this.fetchIpAddresses(this.selectedIpNetwork.id);
                        await this.fetchIpNetworks();
                    }
                } catch (error) {
                    this.ipAddressFormError = window.__i18n.ipam_network_error;
                } finally {
                    this.isIpAddressSubmitting = false;
                }
            },
            openAssetFromIpam(address) {
                if (!address?.asset_id) {
                    return;
                }

                this.openDetailModal({
                    id: address.asset_id,
                    asset_tag: address.asset_tag,
                    name: address.asset_name,
                    category_name: '',
                });
            },
            openIpamImportModal() {
                this.ipamImportFile = null;
                this.ipamImportFileName = '';
                this.ipamImportErrorMessage = '';
                this.ipamImportSuccessMessage = '';
                this.ipamImportResultErrors = [];
                this.isIpamImportOpen = true;
            },
            closeIpamImportModal() {
                if (this.isIpamImportSubmitting) {
                    return;
                }

                this.isIpamImportOpen = false;
            },
            onIpamImportFileSelected(event) {
                const file = event.target.files?.[0] ?? null;
                this.ipamImportFile = file;
                this.ipamImportFileName = file ? file.name : '';
                this.ipamImportErrorMessage = '';
                this.ipamImportSuccessMessage = '';
                this.ipamImportResultErrors = [];
            },
            async submitIpamImport() {
                if (!this.ipamImportFile) {
                    this.ipamImportErrorMessage = window.__i18n.import_file_missing;
                    return;
                }

                this.isIpamImportSubmitting = true;
                this.ipamImportErrorMessage = '';
                this.ipamImportSuccessMessage = '';
                this.ipamImportResultErrors = [];

                const formData = new FormData();
                formData.append('file', this.ipamImportFile);
                const endpoint = this.ipamImportType === 'networks'
                    ? '/api/ip-networks/import'
                    : '/api/ip-addresses/import';

                try {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: { Accept: 'application/json' },
                        body: formData,
                    });
                    const result = await response.json();
                    const data = result?.data ?? {};
                    const errors = Array.isArray(data.errors) ? data.errors : [];

                    if (!response.ok) {
                        this.ipamImportErrorMessage = result.message || window.__i18n.import_all_failed;
                        this.ipamImportResultErrors = errors;
                        return;
                    }

                    this.ipamImportSuccessMessage = result.message || window.__i18n.ipam_import_success.replace('%d', String(data.imported ?? 0));
                    this.ipamImportResultErrors = errors;
                    await this.fetchIpNetworks();

                    if (this.selectedIpNetwork?.id && this.ipamImportType === 'addresses') {
                        await this.fetchIpAddresses(this.selectedIpNetwork.id);
                    }
                } catch (error) {
                    this.ipamImportErrorMessage = window.__i18n.import_network_error;
                } finally {
                    this.isIpamImportSubmitting = false;
                }
            },
            async fetchConsumables(resetPage = false) {
                if (!this.canManageAssets || this.consumablesLoading) {
                    return;
                }

                if (resetPage) {
                    this.consumablesPage = 1;
                }

                this.consumablesLoading = true;
                this.consumablesError = '';

                const query = this.buildConsumableFilterQueryString(this.consumablesPage);
                const url = query ? `/api/consumables?${query}` : '/api/consumables';

                try {
                    const response = await fetch(url, this.apiFetchInit('GET'));
                    const result = await response.json();

                    if (!response.ok) {
                        this.consumablesError = result.message || window.__i18n.consumables_filter_error;
                        this.consumables = [];
                        return;
                    }

                    this.consumables = Array.isArray(result.data) ? result.data : [];
                    this.consumablesPagination = result.pagination || this.defaultListPagination();
                    this.consumablesPage = Number(this.consumablesPagination.page || 1);
                } catch (error) {
                    this.consumablesError = window.__i18n.consumables_network_error;
                    this.consumables = [];
                } finally {
                    this.consumablesLoading = false;
                }
            },
            resolveConsumableFilterLabel(field) {
                if (field?.label_key && window.__i18n[field.label_key]) {
                    return window.__i18n[field.label_key];
                }

                return field?.label || field?.name || '';
            },
            resolveConsumableFilterOptionLabel(option) {
                if (option?.label_key && window.__i18n[option.label_key]) {
                    return window.__i18n[option.label_key];
                }

                return option?.label || option?.value || '';
            },
            hasActiveConsumableFilters() {
                return Object.values(this.consumableFilters || {}).some((value) => String(value || '').trim() !== '');
            },
            buildConsumableFilterQueryString(page = null) {
                const params = new URLSearchParams();
                params.set('page', String(page ?? this.consumablesPage ?? 1));

                Object.entries(this.consumableFilters || {}).forEach(([name, value]) => {
                    const trimmed = String(value || '').trim();

                    if (trimmed !== '') {
                        params.append(`filter[${name}]`, trimmed);
                    }
                });

                return params.toString();
            },
            async applyConsumableFilters() {
                await this.fetchConsumables(true);
            },
            resetConsumableFilters() {
                const cleared = {};

                (this.consumableFilterFields || []).forEach((field) => {
                    cleared[field.name] = '';
                });

                this.consumableFilters = cleared;
                this.fetchConsumables(true);
            },
            consumablesPageNumbers() {
                return this.listPaginationWindow(this.consumablesPagination);
            },
            resolveConsumablesPaginationLabel() {
                return this.resolveListPaginationLabel(this.consumablesPagination);
            },
            goToConsumablesPage(page) {
                const targetPage = Number(page);

                if (
                    Number.isNaN(targetPage)
                    || targetPage < 1
                    || targetPage > Number(this.consumablesPagination.total_pages || 1)
                    || targetPage === this.consumablesPagination.page
                ) {
                    return;
                }

                this.consumablesPage = targetPage;
                this.fetchConsumables();
            },
            consumableIsLowStock(item) {
                if (!item) {
                    return false;
                }

                if (typeof item.is_low_stock === 'boolean') {
                    return item.is_low_stock;
                }

                return Number(item.quantity || 0) <= Number(item.min_stock_level || 0);
            },
            openConsumableModal(item = null) {
                this.consumableForm = {
                    id: item?.id ? Number(item.id) : null,
                    name: item?.name || '',
                    quantity: item?.quantity ?? 0,
                    min_stock_level: item?.min_stock_level ?? 0,
                    location_id: item?.location_id ? String(item.location_id) : '',
                };
                this.consumableFormError = '';
                this.consumablesSuccessMessage = '';
                this.isConsumableModalOpen = true;
            },
            closeConsumableModal() {
                if (this.isConsumableSubmitting) {
                    return;
                }

                this.isConsumableModalOpen = false;
            },
            async submitConsumableForm() {
                this.isConsumableSubmitting = true;
                this.consumableFormError = '';
                this.consumablesSuccessMessage = '';

                const payload = {
                    name: this.consumableForm.name,
                    quantity: Number(this.consumableForm.quantity) || 0,
                    min_stock_level: Number(this.consumableForm.min_stock_level) || 0,
                    location_id: this.consumableForm.location_id ? Number(this.consumableForm.location_id) : null,
                };

                const isEdit = Number(this.consumableForm.id) > 0;
                const url = isEdit ? `/api/consumables/${this.consumableForm.id}` : '/api/consumables';
                const method = isEdit ? 'PUT' : 'POST';

                try {
                    const response = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.consumableFormError = result.message || (isEdit
                            ? window.__i18n.consumable_update_error
                            : window.__i18n.consumable_create_error);
                        return;
                    }

                    this.consumablesSuccessMessage = result.message || (isEdit
                        ? window.__i18n.consumable_update_success
                        : window.__i18n.consumable_create_success);
                    this.isConsumableModalOpen = false;
                    await this.fetchConsumables();
                } catch (error) {
                    this.consumableFormError = window.__i18n.consumables_network_error;
                } finally {
                    this.isConsumableSubmitting = false;
                }
            },
            openConsumableAdjustModal(item, mode) {
                this.consumableAdjustTarget = item;
                this.consumableAdjustMode = mode === 'restock' ? 'restock' : 'checkout';
                this.consumableAdjustQuantity = 1;
                this.consumableAdjustError = '';
                this.isConsumableAdjustModalOpen = true;
            },
            closeConsumableAdjustModal() {
                if (this.isConsumableAdjustSubmitting) {
                    return;
                }

                this.isConsumableAdjustModalOpen = false;
                this.consumableAdjustTarget = null;
            },
            async submitConsumableAdjustForm() {
                if (!this.consumableAdjustTarget?.id) {
                    return;
                }

                this.isConsumableAdjustSubmitting = true;
                this.consumableAdjustError = '';
                this.consumablesSuccessMessage = '';

                const endpoint = this.consumableAdjustMode === 'restock' ? 'restock' : 'checkout';

                try {
                    const response = await fetch(`/api/consumables/${this.consumableAdjustTarget.id}/${endpoint}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            quantity: Number(this.consumableAdjustQuantity) || 1,
                        }),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.consumableAdjustError = result.message || (endpoint === 'restock'
                            ? window.__i18n.consumable_restock_error
                            : window.__i18n.consumable_checkout_error);
                        return;
                    }

                    this.consumablesSuccessMessage = result.message || (endpoint === 'restock'
                        ? window.__i18n.consumable_restock_success
                        : window.__i18n.consumable_checkout_success);
                    this.isConsumableAdjustModalOpen = false;
                    this.consumableAdjustTarget = null;
                    await this.fetchConsumables();
                } catch (error) {
                    this.consumableAdjustError = window.__i18n.consumables_network_error;
                } finally {
                    this.isConsumableAdjustSubmitting = false;
                }
            },
            async deleteConsumable(item) {
                if (!item?.id || !window.confirm(window.__i18n.consumable_delete_confirm)) {
                    return;
                }

                try {
                    const response = await fetch(`/api/consumables/${item.id}`, {
                        method: 'DELETE',
                        headers: { Accept: 'application/json' },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.consumablesError = result.message || window.__i18n.consumable_update_error;
                        return;
                    }

                    this.consumablesSuccessMessage = result.message || window.__i18n.consumable_delete_success;
                    await this.fetchConsumables();
                } catch (error) {
                    this.consumablesError = window.__i18n.consumables_network_error;
                }
            },
            async fetchKnowledgeBaseArticles() {
                if (!this.canManageAssets) {
                    return;
                }

                this.knowledgeBaseLoading = true;
                this.knowledgeBaseError = '';

                try {
                    const response = await fetch('/api/knowledge-base', {
                        headers: { Accept: 'application/json' },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.knowledgeBaseError = result.message || window.__i18n.kb_fetch_error;
                        this.knowledgeBaseArticles = [];
                        return;
                    }

                    this.knowledgeBaseArticles = Array.isArray(result.data) ? result.data : [];
                } catch (error) {
                    this.knowledgeBaseError = window.__i18n.kb_network_error;
                    this.knowledgeBaseArticles = [];
                } finally {
                    this.knowledgeBaseLoading = false;
                }
            },
            async fetchPublishedKnowledgeBase() {
                if (!this.isEndUser) {
                    return;
                }

                this.publishedKnowledgeBaseLoading = true;
                this.publishedKnowledgeBaseError = '';

                try {
                    const response = await fetch('/api/knowledge-base/published', {
                        headers: { Accept: 'application/json' },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.publishedKnowledgeBaseError = result.message || window.__i18n.portal_knowledge_base_error;
                        this.publishedKnowledgeBase = [];
                        return;
                    }

                    this.publishedKnowledgeBase = Array.isArray(result.data) ? result.data : [];
                    this.publishedKnowledgeBaseSearchQuery = '';
                } catch (error) {
                    this.publishedKnowledgeBaseError = window.__i18n.portal_knowledge_base_error;
                    this.publishedKnowledgeBase = [];
                } finally {
                    this.publishedKnowledgeBaseLoading = false;
                }
            },
            formatKnowledgeBaseDate(value) {
                if (!value) {
                    return '—';
                }

                const date = new Date(String(value).replace(' ', 'T'));

                if (Number.isNaN(date.getTime())) {
                    return value;
                }

                return date.toLocaleString();
            },
            matchesPublishedKnowledgeBaseArticle(article) {
                const query = String(this.publishedKnowledgeBaseSearchQuery || '').trim().toLocaleLowerCase('tr-TR');

                if (query === '') {
                    return true;
                }

                const title = String(article?.title || '').toLocaleLowerCase('tr-TR');
                const content = String(article?.content || '').toLocaleLowerCase('tr-TR');

                return title.includes(query) || content.includes(query);
            },
            filteredPublishedKnowledgeBase() {
                return (this.publishedKnowledgeBase || []).filter((article) => this.matchesPublishedKnowledgeBaseArticle(article));
            },
            openKnowledgeBaseModal(article = null) {
                this.knowledgeBaseForm = {
                    id: article?.id ? Number(article.id) : null,
                    title: article?.title || '',
                    content: article?.content || '',
                    is_published: Boolean(article?.is_published),
                };
                this.knowledgeBaseFormError = '';
                this.knowledgeBaseSuccessMessage = '';
                this.isKnowledgeBaseModalOpen = true;
            },
            closeKnowledgeBaseModal() {
                if (this.isKnowledgeBaseSubmitting) {
                    return;
                }

                this.isKnowledgeBaseModalOpen = false;
            },
            async submitKnowledgeBaseForm() {
                this.isKnowledgeBaseSubmitting = true;
                this.knowledgeBaseFormError = '';
                this.knowledgeBaseSuccessMessage = '';

                const payload = {
                    title: this.knowledgeBaseForm.title,
                    content: this.knowledgeBaseForm.content,
                    is_published: Boolean(this.knowledgeBaseForm.is_published),
                };

                const isEdit = Number(this.knowledgeBaseForm.id) > 0;
                const url = isEdit ? `/api/knowledge-base/${this.knowledgeBaseForm.id}` : '/api/knowledge-base';
                const method = isEdit ? 'PUT' : 'POST';

                try {
                    const response = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.knowledgeBaseFormError = result.message || (isEdit
                            ? window.__i18n.kb_update_error
                            : window.__i18n.kb_create_error);
                        return;
                    }

                    this.knowledgeBaseSuccessMessage = result.message || (isEdit
                        ? window.__i18n.kb_update_success
                        : window.__i18n.kb_create_success);
                    this.isKnowledgeBaseModalOpen = false;
                    await this.fetchKnowledgeBaseArticles();
                } catch (error) {
                    this.knowledgeBaseFormError = window.__i18n.kb_network_error;
                } finally {
                    this.isKnowledgeBaseSubmitting = false;
                }
            },
            async deleteKnowledgeBaseArticle(article) {
                if (!article?.id || !window.confirm(window.__i18n.kb_delete_confirm)) {
                    return;
                }

                try {
                    const response = await fetch(`/api/knowledge-base/${article.id}`, {
                        method: 'DELETE',
                        headers: { Accept: 'application/json' },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.knowledgeBaseError = result.message || window.__i18n.kb_delete_error;
                        return;
                    }

                    this.knowledgeBaseSuccessMessage = result.message || window.__i18n.kb_delete_success;
                    await this.fetchKnowledgeBaseArticles();
                } catch (error) {
                    this.knowledgeBaseError = window.__i18n.kb_network_error;
                }
            },
            get filteredTickets() {
                if (this.ticketStatusFilter === 'all') {
                    return this.tickets;
                }

                return this.tickets.filter((ticket) => ticket.status === this.ticketStatusFilter);
            },
            get filteredIpAddresses() {
                if (this.ipAddressStatusFilter === 'all') {
                    return this.ipAddresses;
                }

                return this.ipAddresses.filter((address) => address.status === this.ipAddressStatusFilter);
            },
            get selectedIpAddressCount() {
                return (this.selectedIpAddressIds || []).length;
            },
            get areAllFilteredIpAddressesSelected() {
                const visible = this.filteredIpAddresses || [];

                if (visible.length === 0) {
                    return false;
                }

                return visible.every((address) => this.isIpAddressSelected(address.id));
            },
            setTicketStatusFilter(value) {
                this.ticketStatusFilter = value;
                this.ticketsPage = 1;
                this.fetchTickets();
            },
            ticketsPageNumbers() {
                return this.listPaginationWindow(this.ticketsPagination);
            },
            resolveTicketsPaginationLabel() {
                return this.resolveListPaginationLabel(this.ticketsPagination);
            },
            goToTicketsPage(page) {
                const targetPage = Number(page);

                if (
                    Number.isNaN(targetPage)
                    || targetPage < 1
                    || targetPage > Number(this.ticketsPagination.total_pages || 1)
                    || targetPage === this.ticketsPagination.page
                ) {
                    return;
                }

                this.ticketsPage = targetPage;
                this.fetchTickets();
            },
            ticketsForStatus(status) {
                return this.filteredTickets.filter((ticket) => ticket.status === status);
            },
            resolveTicketStatus(status) {
                const map = {
                    open: window.__i18n.ticket_status_open,
                    in_progress: window.__i18n.ticket_status_in_progress,
                    resolved: window.__i18n.ticket_status_resolved,
                    closed: window.__i18n.ticket_status_closed,
                };

                return map[status] || status;
            },
            resolveTicketPriority(priority) {
                const map = {
                    low: window.__i18n.ticket_priority_low,
                    medium: window.__i18n.ticket_priority_medium,
                    high: window.__i18n.ticket_priority_high,
                    critical: window.__i18n.ticket_priority_critical,
                };

                return map[priority] || priority;
            },
            ticketStatusClass(status) {
                const classes = {
                    open: 'bg-sky-50 text-sky-700 ring-sky-600/20',
                    in_progress: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
                    resolved: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                    closed: 'bg-zinc-100 text-zinc-700 ring-zinc-500/20',
                };

                return classes[status] || 'bg-zinc-100 text-zinc-700 ring-zinc-500/20';
            },
            ticketPriorityClass(priority) {
                const classes = {
                    low: 'bg-zinc-100 text-zinc-700 ring-zinc-500/20',
                    medium: 'bg-sky-50 text-sky-700 ring-sky-600/20',
                    high: 'bg-amber-50 text-amber-800 ring-amber-600/20',
                    critical: 'bg-rose-50 text-rose-700 ring-rose-600/20',
                };

                return classes[priority] || 'bg-zinc-100 text-zinc-700 ring-zinc-500/20';
            },
            formatTicketDate(value) {
                if (!value) {
                    return '—';
                }

                const date = new Date(String(value).replace(' ', 'T'));

                if (Number.isNaN(date.getTime())) {
                    return value;
                }

                return date.toLocaleString(window.__i18n.locale || 'tr');
            },
            async fetchTickets(options = {}) {
                if (!this.canManageAssets) {
                    return;
                }

                const silent = options.silent === true;

                if (!silent) {
                    this.ticketsLoading = true;
                }
                this.ticketsError = '';

                try {
                    const params = new URLSearchParams({
                        page: String(this.ticketsPage),
                    });

                    if (this.ticketStatusFilter !== 'all') {
                        params.set('status', this.ticketStatusFilter);
                    }

                    const response = await fetch(`/api/tickets?${params.toString()}`, {
                        headers: { Accept: 'application/json' },
                    });
                    const result = await this.parseApiResponse(response);

                    if (!response.ok) {
                        this.ticketsError = this.apiErrorMessage(result, window.__i18n.helpdesk_fetch_error);
                        this.tickets = [];
                        return;
                    }

                    this.tickets = Array.isArray(result.data) ? result.data : [];
                    this.ticketsPagination = result.pagination || this.defaultListPagination();
                    this.ticketsPage = Number(this.ticketsPagination.page || 1);
                    this.maybeOpenTicketFromUrl();
                } catch (error) {
                    this.ticketsError = window.__i18n.helpdesk_network_error;
                    this.tickets = [];
                } finally {
                    if (!silent) {
                        this.ticketsLoading = false;
                    }
                }
            },
            mergeTicketIntoList(ticket) {
                if (!ticket?.id) {
                    return;
                }

                const index = this.tickets.findIndex((item) => Number(item.id) === Number(ticket.id));

                if (index >= 0) {
                    this.tickets[index] = { ...this.tickets[index], ...ticket };
                } else {
                    this.tickets.unshift(ticket);
                }
            },
            async fetchAuditLogs() {
                if (!this.canAccessSettings) {
                    return;
                }

                this.auditLogsLoading = true;
                this.auditLogsError = '';

                try {
                    const params = new URLSearchParams({
                        page: String(this.auditLogPage),
                        per_page: '50',
                    });

                    if (this.auditLogFilters.user_id) {
                        params.set('user_id', this.auditLogFilters.user_id);
                    }

                    if (this.auditLogFilters.action_type) {
                        params.set('action_type', this.auditLogFilters.action_type);
                    }

                    if (this.auditLogFilters.entity_type) {
                        params.set('entity_type', this.auditLogFilters.entity_type);
                    }

                    if (this.auditLogFilters.date_from) {
                        params.set('date_from', this.auditLogFilters.date_from);
                    }

                    if (this.auditLogFilters.date_to) {
                        params.set('date_to', this.auditLogFilters.date_to);
                    }

                    const response = await fetch('/api/audit-logs?' + params.toString(), {
                        headers: { Accept: 'application/json' },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.auditLogsError = result.message || window.__i18n.audit_logs_fetch_error;
                        this.auditLogs = [];
                        return;
                    }

                    this.auditLogs = Array.isArray(result.data) ? result.data : [];
                    this.auditLogPagination = result.pagination || this.auditLogPagination;

                    if (result.filters) {
                        this.auditLogFilterUsers = Array.isArray(result.filters.users) ? result.filters.users : [];
                        this.auditLogFilterActions = Array.isArray(result.filters.action_types) ? result.filters.action_types : [];
                        this.auditLogFilterEntities = Array.isArray(result.filters.entity_types) ? result.filters.entity_types : [];
                    }
                } catch (error) {
                    this.auditLogsError = window.__i18n.audit_logs_network_error;
                    this.auditLogs = [];
                } finally {
                    this.auditLogsLoading = false;
                }
            },
            resetAuditLogFilters() {
                this.auditLogFilters = {
                    user_id: '',
                    action_type: '',
                    entity_type: '',
                    date_from: '',
                    date_to: '',
                };
                this.auditLogPage = 1;
                this.fetchAuditLogs();
            },
            changeAuditLogPage(page) {
                if (page < 1 || page > this.auditLogPagination.total_pages) {
                    return;
                }

                this.auditLogPage = page;
                this.fetchAuditLogs();
            },
            auditActionLabel(action) {
                const key = 'audit_action_' + action;
                return window.__i18n[key] || action;
            },
            auditEntityLabel(entity) {
                const key = 'audit_entity_' + entity;
                return window.__i18n[key] || entity;
            },
            auditActionBadgeClass(action) {
                const classes = {
                    created: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                    updated: 'bg-sky-50 text-sky-700 ring-sky-600/20',
                    deleted: 'bg-rose-50 text-rose-700 ring-rose-600/20',
                    login: 'bg-violet-50 text-violet-700 ring-violet-600/20',
                    assigned: 'bg-amber-50 text-amber-700 ring-amber-600/20',
                    returned: 'bg-zinc-100 text-zinc-700 ring-zinc-300',
                    transferred: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
                };

                return classes[action] || 'bg-zinc-100 text-zinc-700 ring-zinc-300';
            },
            formatAuditTimestamp(value) {
                return this.formatActivityTimestamp(value, {
                    includeTime: true,
                    includeYear: true,
                    fallback: '—',
                });
            },
            auditLogPaginationLabel() {
                const template = window.__i18n.audit_pagination_info || ':from–:to / :total';
                const page = this.auditLogPagination.page || 1;
                const perPage = this.auditLogPagination.per_page || 50;
                const total = this.auditLogPagination.total || 0;
                const from = total === 0 ? 0 : ((page - 1) * perPage) + 1;
                const to = Math.min(page * perPage, total);

                return template
                    .replace(':from', String(from))
                    .replace(':to', String(to))
                    .replace(':total', String(total));
            },
            maybeOpenTicketFromUrl() {
                const params = new URLSearchParams(window.location.search);
                const ticketId = params.get('ticket');

                if (!ticketId) {
                    return;
                }

                this.activeView = 'helpdesk';
                const ticket = this.tickets.find((item) => String(item.id) === String(ticketId));

                if (ticket) {
                    this.openTicketDetail(ticket);
                    return;
                }

                this.openTicketDetail({ id: Number(ticketId) });
            },
            openTicketModal() {
                this.ticketForm = {
                    subject: '',
                    description: '',
                    asset_id: '',
                    priority: 'medium',
                };
                this.ticketFormError = '';
                this.ticketsSuccessMessage = '';
                this.ticketSelectedPersonnel = null;
                this.ticketUserSearchQuery = '';
                this.ticketUserSearchResults = [];
                this.ticketUserSearchError = '';
                this.showTicketUserResults = false;
                this.isTicketModalOpen = true;
            },
            closeTicketModal() {
                this.isTicketSubmitting = false;
                this.isTicketModalOpen = false;
            },
            clearTicketSelectedPersonnel() {
                this.ticketSelectedPersonnel = null;
            },
            selectTicketPersonnel(user) {
                this.ticketSelectedPersonnel = user;
                this.ticketUserSearchQuery = '';
                this.ticketUserSearchResults = [];
                this.ticketUserSearchError = '';
                this.showTicketUserResults = false;
            },
            async searchTicketPersonnel() {
                this.ticketUserSearchLoading = true;
                this.ticketUserSearchError = '';

                const result = await this.fetchPersonnelSearchOptions(this.ticketUserSearchQuery);
                this.ticketUserSearchResults = result.data;
                this.ticketUserSearchError = result.error;
                this.showTicketUserResults = true;
                this.ticketUserSearchLoading = false;
            },
            async submitTicketForm() {
                if (!this.ticketSelectedPersonnel?.id) {
                    this.ticketFormError = window.__i18n.ticket_personnel_required;
                    return;
                }

                if (this.isTicketSubmitting) {
                    return;
                }

                this.isTicketSubmitting = true;
                this.ticketFormError = '';
                this.ticketsSuccessMessage = '';

                const payload = {
                    subject: this.ticketForm.subject,
                    description: this.ticketForm.description,
                    personnel_id: Number(this.ticketSelectedPersonnel.id),
                    priority: this.ticketForm.priority,
                    asset_id: this.ticketForm.asset_id ? Number(this.ticketForm.asset_id) : null,
                };

                try {
                    const response = await fetch('/api/tickets', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });
                    const result = await this.parseApiResponse(response);

                    if (!response.ok) {
                        this.ticketFormError = this.apiErrorMessage(result, window.__i18n.ticket_create_error);
                        return;
                    }

                    this.ticketsSuccessMessage = this.apiErrorMessage(result, window.__i18n.ticket_create_success);
                    this.isTicketModalOpen = false;
                    this.mergeTicketIntoList(result.data);
                    this.fetchTickets({ silent: true });
                } catch (error) {
                    this.ticketFormError = window.__i18n.helpdesk_network_error;
                } finally {
                    this.isTicketSubmitting = false;
                }
            },
            async openTicketDetail(ticket) {
                this.isTicketDetailOpen = true;
                this.ticketDetailLoading = true;
                this.ticketDetail = ticket;
                this.ticketDetailForm = {
                    status: ticket.status,
                    priority: ticket.priority,
                    category_id: ticket.category_id ? String(ticket.category_id) : '',
                };
                this.ticketComments = [];
                this.ticketCommentBody = '';
                this.ticketCommentError = '';

                try {
                    const response = await fetch(`/api/tickets/${ticket.id}`, {
                        headers: { Accept: 'application/json' },
                    });
                    const result = await this.parseApiResponse(response);

                    if (!response.ok) {
                        this.ticketsError = this.apiErrorMessage(result, window.__i18n.helpdesk_fetch_error);
                        return;
                    }

                    this.ticketDetail = result.data;
                    this.ticketDetailForm = {
                        status: result.data.status,
                        priority: result.data.priority,
                        category_id: result.data.category_id ? String(result.data.category_id) : '',
                    };
                    this.ticketComments = Array.isArray(result.data?.comments) ? result.data.comments : [];
                } catch (error) {
                    this.ticketsError = window.__i18n.helpdesk_network_error;
                } finally {
                    this.ticketDetailLoading = false;
                }
            },
            closeTicketDetail() {
                this.isTicketDetailSubmitting = false;
                this.isTicketCommentSubmitting = false;
                this.isTicketDetailOpen = false;
                this.ticketDetail = null;
            },
            openTicketLinkedAsset() {
                const ticket = this.ticketDetail;
                if (!ticket?.asset_id) {
                    return;
                }

                this.closeTicketDetail();
                this.activeView = 'assets';
                this.openDetailModal({
                    id: ticket.asset_id,
                    asset_tag: ticket.asset_tag || '',
                    name: ticket.asset_name || '',
                    status: 'ready',
                    category_name: '',
                    user_id: null,
                    user_name: null,
                    location_id: null,
                    location_name: null,
                    location_building: null,
                });
            },
            async updateTicketDetail() {
                if (!this.ticketDetail?.id || this.isTicketDetailSubmitting) {
                    return;
                }

                this.isTicketDetailSubmitting = true;

                try {
                    const response = await fetch(`/api/tickets/${this.ticketDetail.id}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            status: this.ticketDetailForm.status,
                            priority: this.ticketDetailForm.priority,
                            category_id: this.ticketDetailForm.category_id ? Number(this.ticketDetailForm.category_id) : null,
                        }),
                    });
                    const result = await this.parseApiResponse(response);

                    if (!response.ok) {
                        this.ticketsError = this.apiErrorMessage(result, window.__i18n.ticket_update_error);
                        return;
                    }

                    this.ticketsSuccessMessage = this.apiErrorMessage(result, window.__i18n.ticket_update_success);
                    this.ticketDetail = result.data;
                    this.ticketDetailForm = {
                        status: result.data.status,
                        priority: result.data.priority,
                        category_id: result.data.category_id ? String(result.data.category_id) : '',
                    };
                    this.mergeTicketIntoList(result.data);
                    this.fetchTickets({ silent: true });
                } catch (error) {
                    this.ticketsError = window.__i18n.helpdesk_network_error;
                } finally {
                    this.isTicketDetailSubmitting = false;
                }
            },
            async submitTicketComment() {
                if (!this.ticketDetail?.id || this.isTicketCommentSubmitting) {
                    return;
                }

                const body = this.ticketCommentBody.trim();

                if (body === '') {
                    this.ticketCommentError = window.__i18n.ticket_comment_create_error;
                    return;
                }

                this.isTicketCommentSubmitting = true;
                this.ticketCommentError = '';

                try {
                    const response = await fetch(`/api/tickets/${this.ticketDetail.id}/comments`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({ body }),
                    });
                    const result = await this.parseApiResponse(response);

                    if (!response.ok) {
                        this.ticketCommentError = this.apiErrorMessage(result, window.__i18n.ticket_comment_create_error);
                        return;
                    }

                    this.ticketCommentBody = '';
                    this.ticketComments = this.appendTicketComment(this.ticketComments, result.data);
                    this.ticketsSuccessMessage = this.apiErrorMessage(result, window.__i18n.ticket_comment_create_success);
                } catch (error) {
                    this.ticketCommentError = window.__i18n.helpdesk_network_error;
                } finally {
                    this.isTicketCommentSubmitting = false;
                }
            },
            async deleteTicket() {
                if (!this.ticketDetail?.id) {
                    return;
                }

                if (!window.confirm(window.__i18n.ticket_delete_confirm)) {
                    return;
                }

                try {
                    const response = await fetch(`/api/tickets/${this.ticketDetail.id}`, {
                        method: 'DELETE',
                        headers: { Accept: 'application/json' },
                    });
                    const result = await this.parseApiResponse(response);

                    if (!response.ok) {
                        this.ticketsError = this.apiErrorMessage(result, window.__i18n.ticket_delete_error);
                        return;
                    }

                    this.ticketsSuccessMessage = this.apiErrorMessage(result, window.__i18n.ticket_delete_success);
                    this.isTicketDetailOpen = false;
                    this.ticketDetail = null;
                    this.fetchTickets({ silent: true });
                } catch (error) {
                    this.ticketsError = window.__i18n.helpdesk_network_error;
                }
            },
            openLicenseModal() {
                this.licenseForm = {
                    name: '',
                    vendor: '',
                    license_key: '',
                    seats: 1,
                    expiration_date: '',
                    notes: '',
                };
                this.licenseFormError = '';
                this.licensesSuccessMessage = '';
                this.isLicenseModalOpen = true;
            },
            closeLicenseModal() {
                if (this.isLicenseSubmitting) {
                    return;
                }

                this.isLicenseModalOpen = false;
            },
            async submitLicenseForm() {
                this.isLicenseSubmitting = true;
                this.licenseFormError = '';
                this.licensesSuccessMessage = '';

                const payload = {
                    name: this.licenseForm.name,
                    vendor: this.licenseForm.vendor,
                    seats: Number(this.licenseForm.seats) || 1,
                    license_key: this.licenseForm.license_key || null,
                    expiration_date: this.licenseForm.expiration_date || null,
                    notes: this.licenseForm.notes || null,
                };

                try {
                    const response = await fetch('/api/licenses', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        const msg = result.message || window.__i18n.license_create_error;
                        this.licenseFormError = msg;
                        if (response.status === 500) {
                            alert('Hata: ' + msg);
                        }
                        return;
                    }

                    this.isLicenseModalOpen = false;
                    this.licensesSuccessMessage = result.message || window.__i18n.license_create_success;
                    await this.fetchLicenses();
                } catch (error) {
                    this.licenseFormError = window.__i18n.licenses_network_error;
                    alert(window.__i18n.licenses_network_error || 'Ağ hatası: Lisans oluşturulamadı.');
                } finally {
                    this.isLicenseSubmitting = false;
                }
            },
            openAssignLicenseModal(license = null) {
                this.assignLicenseForm = {
                    license_id: license?.id ? String(license.id) : '',
                    assign_type: 'asset',
                    asset_id: '',
                };
                this.assignLicenseFormError = '';
                this.assignLicenseSuccessMessage = '';
                this.assignLicenseSelectedPersonnel = null;
                this.assignLicensePersonnelSearchQuery = '';
                this.assignLicensePersonnelSearchResults = [];
                this.assignLicensePersonnelSearchError = '';
                this.showAssignLicensePersonnelResults = false;
                this.assignLicenseAssignments = [];
                this.isAssignLicenseModalOpen = true;

                if (license?.id) {
                    this.loadLicenseAssignments();
                }
            },
            closeAssignLicenseModal() {
                if (this.isAssignLicenseSubmitting) {
                    return;
                }

                this.isAssignLicenseModalOpen = false;
                // ensure clean closed state for the assign modal (prevents stale UI from legacy user/personnel state)
                this.assignLicenseForm = { license_id: '', assign_type: 'asset', asset_id: '' };
                this.assignLicenseFormError = '';
                this.assignLicenseSuccessMessage = '';
                this.assignLicenseSelectedPersonnel = null;
                this.assignLicensePersonnelSearchQuery = '';
                this.assignLicensePersonnelSearchResults = [];
                this.assignLicensePersonnelSearchError = '';
                this.showAssignLicensePersonnelResults = false;
                this.assignLicenseAssignments = [];
                this.assignLicenseAssignmentsLoading = false;
                this.isAssignLicenseSubmitting = false;
            },
            async loadLicenseAssignments() {
                const licenseId = Number(this.assignLicenseForm.license_id);

                if (!licenseId) {
                    this.assignLicenseAssignments = [];
                    return;
                }

                this.assignLicenseAssignmentsLoading = true;

                try {
                    const response = await fetch(`/api/licenses/${licenseId}/assignments`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        this.assignLicenseAssignments = [];
                        if (response.status === 500) {
                            const msg = result.message || window.__i18n.license_assign_error || 'Lisans atamaları yüklenirken sunucu hatası oluştu.';
                            this.assignLicenseFormError = msg;
                            alert('Hata: ' + msg);
                        }
                        return;
                    }

                    this.assignLicenseAssignments = Array.isArray(result.data) ? result.data : [];
                } catch (error) {
                    this.assignLicenseAssignments = [];
                    this.assignLicenseFormError = window.__i18n.licenses_network_error;
                    alert(window.__i18n.licenses_network_error || 'Ağ hatası oluştu. Lütfen bağlantınızı kontrol edin.');
                } finally {
                    this.assignLicenseAssignmentsLoading = false;
                }
            },
            async searchAssignLicensePersonnel() {
                this.assignLicensePersonnelSearchLoading = true;
                this.assignLicensePersonnelSearchError = '';

                const result = await this.fetchPersonnelSearchOptions(this.assignLicensePersonnelSearchQuery);
                this.assignLicensePersonnelSearchResults = result.data;
                this.assignLicensePersonnelSearchError = result.error;
                this.showAssignLicensePersonnelResults = true;
                this.assignLicensePersonnelSearchLoading = false;
            },
            selectAssignLicensePersonnel(personnel) {
                this.assignLicenseSelectedPersonnel = personnel;
                this.assignLicensePersonnelSearchQuery = '';
                this.assignLicensePersonnelSearchResults = [];
                this.assignLicensePersonnelSearchError = '';
                this.showAssignLicensePersonnelResults = false;
            },
            clearAssignLicensePersonnel() {
                this.assignLicenseSelectedPersonnel = null;
            },
            formatLicenseExpiration(value) {
                if (!value) {
                    return window.__i18n.license_no_expiration;
                }

                return value;
            },
            formatLicenseSeatUsage(license) {
                const assigned = Number(license?.assigned_seats ?? 0);
                const total = Number(license?.seats ?? 0);

                return `${assigned} / ${total} Kullanılıyor`;
            },
            licenseSeatUsagePercent(license) {
                const assigned = Number(license?.assigned_seats ?? 0);
                const total = Number(license?.seats ?? 0);

                if (total <= 0) {
                    return 0;
                }

                return Math.max(0, Math.min(100, (assigned / total) * 100));
            },
            licenseSeatBarColor(license) {
                const percent = this.licenseSeatUsagePercent(license);

                if (percent >= 100) {
                    return 'bg-rose-500';
                }

                if (percent >= 80) {
                    return 'bg-amber-500';
                }

                return 'bg-emerald-500';
            },
            formatLicenseAssignmentTarget(assignment) {
                if (assignment?.asset_id) {
                    const tag = assignment.asset_tag || assignment.asset_id;
                    const name = assignment.asset_name ? ` — ${assignment.asset_name}` : '';

                    return `${tag}${name}`;
                }

                const personnelId = assignment?.personnel_id ?? assignment?.user_id;
                if (personnelId) {
                    const name = assignment?.personnel_name ?? assignment?.user_name ?? personnelId;
                    const emailVal = assignment?.personnel_email ?? assignment?.user_email;
                    const email = emailVal ? ` (${emailVal})` : '';

                    return `${name}${email}`;
                }

                return '—';
            },
            async submitAssignLicenseForm() {
                const licenseId = Number(this.assignLicenseForm.license_id);

                if (!licenseId) {
                    this.assignLicenseFormError = window.__i18n.license_invalid_id || window.__i18n.license_assign_error;
                    return;
                }

                const payload = { };

                if (this.assignLicenseForm.assign_type === 'asset') {
                    if (!this.assignLicenseForm.asset_id) {
                        this.assignLicenseFormError = window.__i18n.license_assign_error;
                        return;
                    }

                    payload.asset_id = Number(this.assignLicenseForm.asset_id);
                } else if (this.assignLicenseSelectedPersonnel?.id) {
                    payload.personnel_id = Number(this.assignLicenseSelectedPersonnel.id);
                } else {
                    this.assignLicenseFormError = window.__i18n.license_assign_error;
                    return;
                }

                this.isAssignLicenseSubmitting = true;
                this.assignLicenseFormError = '';
                this.assignLicenseSuccessMessage = '';

                try {
                    const response = await fetch(`/api/licenses/${licenseId}/assign`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        const msg = result.message || window.__i18n.license_assign_error;
                        this.assignLicenseFormError = msg;
                        if (response.status === 500) {
                            alert('Hata: ' + msg);
                        }
                        return;
                    }

                    this.assignLicenseSuccessMessage = result.message || window.__i18n.license_assign_success;
                    this.assignLicenseForm.asset_id = '';
                    this.assignLicenseSelectedPersonnel = null;
                    await Promise.all([this.fetchLicenses(), this.loadLicenseAssignments()]);
                } catch (error) {
                    this.assignLicenseFormError = window.__i18n.licenses_network_error;
                    alert(window.__i18n.licenses_network_error || 'Ağ hatası: Lisans atanamadı.');
                } finally {
                    this.isAssignLicenseSubmitting = false;
                }
            },
            async unassignLicenseSeat(assignment) {
                if (!assignment?.id || !this.assignLicenseForm.license_id) {
                    return;
                }

                if (!window.confirm(window.__i18n.license_unassign_confirm)) {
                    return;
                }

                const licenseId = Number(this.assignLicenseForm.license_id);

                try {
                    const response = await fetch(`/api/licenses/${licenseId}/unassign`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ assignment_id: assignment.id }),
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        const msg = result.message || window.__i18n.license_unassign_error;
                        this.assignLicenseFormError = msg;
                        if (response.status === 500) {
                            alert('Hata: ' + msg);
                        }
                        return;
                    }

                    this.assignLicenseSuccessMessage = result.message || window.__i18n.license_unassign_success;
                    await Promise.all([this.fetchLicenses(), this.loadLicenseAssignments()]);
                } catch (error) {
                    this.assignLicenseFormError = window.__i18n.licenses_network_error;
                    alert(window.__i18n.licenses_network_error || 'Ağ hatası: Lisans kaldırılamadı.');
                }
            },
            initQuillEditor() {
                if (typeof Quill === 'undefined') {
                    return;
                }

                const container = document.getElementById('zimmet-quill-editor');

                if (!container) {
                    return;
                }

                if (this.quillEditor) {
                    return;
                }

                this.quillEditor = new Quill(container, {
                    theme: 'snow',
                    placeholder: 'Zimmet formu metnini buraya yazın…',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline'],
                            [{ list: 'ordered' }, { list: 'bullet' }],
                            [{ align: [] }],
                            ['clean'],
                        ],
                    },
                });

                const template = this.settingsForm.zimmet_template || '';

                if (template.includes('<')) {
                    this.quillEditor.root.innerHTML = template;
                } else if (template !== '') {
                    this.quillEditor.setText(template);
                }
            },
            syncZimmetTemplate() {
                if (!this.quillEditor) {
                    return;
                }

                const html = this.quillEditor.root.innerHTML.trim();
                const isEmpty = html === '' || html === '<p><br></p>';
                this.settingsForm.zimmet_template = isEmpty ? '' : html;

                if (this.$refs.zimmetTemplateInput) {
                    this.$refs.zimmetTemplateInput.value = this.settingsForm.zimmet_template;
                }
            },
            async saveSettings() {
                this.syncZimmetTemplate();
                this.isSavingSettings = true;
                this.settingsErrorMessage = '';
                this.settingsSuccessMessage = '';

                try {
                    const response = await fetch('/api/settings', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            active_auth_driver: this.settingsForm.active_auth_driver,
                            zimmet_template: this.settingsForm.zimmet_template,
                            custom_fields: this.prepareCustomFieldsForSave(),
                            ldap_config: this.settingsForm.ldap_config,
                            google_config: this.settingsForm.google_config,
                            login_config: this.settingsForm.login_config,
                        }),
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        if (result.errors) {
                            this.settingsErrorMessage = Object.values(result.errors)
                                .flat()
                                .join(' ');
                        } else {
                            this.settingsErrorMessage = result.message || window.__i18n.settings_save_error;
                        }

                        return;
                    }

                    this.settingsSuccessMessage = window.__i18n.settings_save_success;
                    this.globalCustomFields = Array.isArray(result.data?.custom_fields)
                        ? result.data.custom_fields
                        : this.settingsForm.custom_fields;
                    window.__globalCustomFields = this.globalCustomFields;

                    if (result.data?.ldap_config) {
                        this.settingsForm.ldap_config = {
                            ...result.data.ldap_config,
                            bind_password: '',
                        };
                    }

                    if (result.data?.google_config) {
                        this.settingsForm.google_config = {
                            ...result.data.google_config,
                            service_account_json: '',
                            oauth_token_json: '',
                        };
                    }

                    if (result.data?.login_config) {
                        this.settingsForm.login_config = {
                            ...result.data.login_config,
                            google_sso: {
                                ...result.data.login_config.google_sso,
                                client_secret: '',
                            },
                            microsoft_sso: {
                                ...result.data.login_config.microsoft_sso,
                                client_secret: '',
                            },
                        };
                    }
                } catch (error) {
                    this.settingsErrorMessage = window.__i18n.settings_network_error;
                } finally {
                    this.isSavingSettings = false;
                }
            },
            async saveSmtpSettings() {
                this.isSavingSettings = true;
                this.settingsErrorMessage = '';
                this.settingsSuccessMessage = '';

                try {
                    const response = await fetch('/api/settings', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            smtp_config: this.settingsForm.smtp_config,
                        }),
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        if (result.errors) {
                            this.settingsErrorMessage = Object.values(result.errors)
                                .flat()
                                .join(' ');
                        } else {
                            this.settingsErrorMessage = result.message || window.__i18n.settings_save_error;
                        }

                        return;
                    }

                    this.settingsSuccessMessage = window.__i18n.settings_save_success;

                    if (result.data?.smtp_config) {
                        this.settingsForm.smtp_config = {
                            ...result.data.smtp_config,
                            pass: '',
                        };
                    }
                } catch (error) {
                    this.settingsErrorMessage = window.__i18n.settings_network_error;
                } finally {
                    this.isSavingSettings = false;
                }
            },
            async sendSmtpTestEmail() {
                this.isSendingSmtpTest = true;
                this.settingsErrorMessage = '';
                this.settingsSuccessMessage = '';

                try {
                    const response = await fetch('/api/settings/smtp/test', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            recipient: this.smtpTestRecipient,
                            smtp_config: this.settingsForm.smtp_config,
                        }),
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        if (result.errors) {
                            this.settingsErrorMessage = Object.values(result.errors)
                                .flat()
                                .join(' ');
                        } else {
                            this.settingsErrorMessage = result.message || window.__i18n.settings_smtp_test_failed;
                        }

                        return;
                    }

                    this.settingsSuccessMessage = result.message || window.__i18n.settings_smtp_test_success;
                } catch (error) {
                    this.settingsErrorMessage = window.__i18n.settings_network_error;
                } finally {
                    this.isSendingSmtpTest = false;
                }
            },
            async fetchBackups() {
                if (!this.canAccessSettings) {
                    return;
                }

                this.isLoadingBackups = true;
                this.backupErrorMessage = '';
                this.backupSuccessMessage = '';

                try {
                    const response = await fetch('/api/backups', {
                        headers: {
                            Accept: 'application/json',
                        },
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        this.backupErrorMessage = result.message || window.__i18n.backup_fetch_error;
                        this.backups = [];
                        return;
                    }

                    this.backups = Array.isArray(result.data?.backups) ? result.data.backups : [];
                    this.backupRetentionDays = Number(result.data?.retention_days || 7);
                } catch (error) {
                    this.backupErrorMessage = window.__i18n.backup_network_error;
                    this.backups = [];
                } finally {
                    this.isLoadingBackups = false;
                }
            },
            async createBackup() {
                if (!this.canAccessSettings) {
                    return;
                }

                this.isCreatingBackup = true;
                this.backupErrorMessage = '';
                this.backupSuccessMessage = '';

                try {
                    const response = await fetch('/api/backups', {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                        },
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        this.backupErrorMessage = result.message || window.__i18n.backup_create_error;
                        return;
                    }

                    this.backupSuccessMessage = result.message || window.__i18n.backup_create_success;
                    this.backups = Array.isArray(result.data?.backups) ? result.data.backups : [];
                    this.backupRetentionDays = Number(result.data?.retention_days || this.backupRetentionDays);
                } catch (error) {
                    this.backupErrorMessage = window.__i18n.backup_network_error;
                } finally {
                    this.isCreatingBackup = false;
                }
            },
            downloadBackup(filename) {
                if (!filename) {
                    return;
                }

                this.backupErrorMessage = '';
                this.backupSuccessMessage = '';

                fetch('/api/backups/' + encodeURIComponent(filename) + '/download', {
                    headers: {
                        Accept: 'application/json',
                    },
                })
                    .then(async (response) => {
                        const result = await response.json();

                        if (!response.ok || !result.data?.download_url) {
                            this.backupErrorMessage = result.message || window.__i18n.backup_download_error;
                            return;
                        }

                        window.open(result.data.download_url, '_blank', 'noopener,noreferrer');
                    })
                    .catch(() => {
                        this.backupErrorMessage = window.__i18n.backup_network_error;
                    });
            },
        };
    }
</script>
