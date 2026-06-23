<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Location;
use App\Models\Personnel;
use App\Models\Setting;
use App\Models\User;
use App\Services\AssetCsvImportService;
use App\Services\AssetFilterSchemaService;
use App\Services\InventoryImportService;
use App\Services\ListPagination;
use App\Services\AuditLogger;
use App\Services\Auth\SessionAuthService;
use App\Services\Auth\UserIntegrationFactory;
use App\Services\ClientIpResolver;
use App\Services\EndUserContextService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AssetController
{
    private const CORE_FIELDS = [
        'asset_tag',
        'name',
        'model',
        'brand',
        'serial_number',
        'type',
        'status',
        'location',
        'building',
        'assigned_to',
        'mac_address_1',
        'mac_address_2',
    ];

    public function __construct(
        private readonly Asset $assetModel,
        private readonly AssetHistory $assetHistoryModel,
        private readonly UserIntegrationFactory $userIntegrationFactory,
        private readonly Personnel $personnelModel,
        private readonly User $userModel,
        private readonly Location $locationModel,
        private readonly Category $categoryModel,
        private readonly AssetCsvImportService $assetCsvImportService,
        private readonly InventoryImportService $inventoryImportService,
        private readonly SessionAuthService $sessionAuthService,
        private readonly ClientIpResolver $clientIpResolver,
        private readonly EndUserContextService $endUserContextService,
        private readonly AuditLogger $auditLogger,
        private readonly AssetFilterSchemaService $assetFilterSchemaService,
        private readonly Setting $settingModel,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $categories = $this->categoryModel->findAll();
        $locations = $this->locationModel->findAll();
        $settings = $this->settingModel->getAdminBundle();
        $globalCustomFields = is_array($settings['custom_fields'] ?? null) ? $settings['custom_fields'] : [];

        $filterDefinitions = $this->assetFilterSchemaService->buildDefinitions($categories, $globalCustomFields);
        $filterDefinitions = $this->assetFilterSchemaService->resolveOptions(
            $filterDefinitions,
            $this->assetModel,
            $categories,
            $locations
        );

        $activeFilters = $this->assetFilterSchemaService->parseRequestFilters($request->getQueryParams());
        $page = ListPagination::parsePage($request->getQueryParams());
        $result = $this->assetModel->findPaginatedForDashboard($activeFilters, $filterDefinitions, $page);

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $result['data'],
            'pagination' => $result['pagination'],
            'meta' => [
                'total' => $result['pagination']['total'],
                'filters' => $activeFilters,
            ],
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => 'Invalid JSON payload. Send a valid JSON object in the request body.',
            ]);
        }

        [$coreFields] = $this->separatePayload($payload);
        $coreFields['asset_tag'] = $this->assetModel->generateNextAssetTag();
        $errors = $this->validateCoreFields($coreFields);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $errors,
            ]);
        }

        $coreFields = $this->normalizeCoreFields($coreFields);

        try {
            $asset = $this->assetModel->create($coreFields);
            $this->logAssetCreation($request, $asset, $coreFields);
            $this->auditLogger->logFromRequest(
                $request,
                $this->actorUserId(),
                AuditLog::ACTION_CREATED,
                AuditLog::ENTITY_ASSET,
                (int) ($asset['id'] ?? 0),
                null,
                [
                    'asset_tag' => (string) ($asset['asset_tag'] ?? ''),
                    'name' => (string) ($asset['name'] ?? ''),
                    'status' => (string) ($asset['status'] ?? ''),
                ]
            );
        } catch (\RuntimeException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => 'Asset created successfully.',
            'data' => $asset,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $assetId = (int) ($args['id'] ?? 0);

        if ($assetId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => 'A valid asset id is required.',
            ]);
        }

        $existingAsset = $this->assetModel->findById($assetId);

        if ($existingAsset === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => 'Asset not found.',
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => 'Invalid JSON payload. Send a valid JSON object in the request body.',
            ]);
        }

        [$coreFields] = $this->separatePayload($payload);

        $errors = $this->validateCoreFields($coreFields, $assetId);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $errors,
            ]);
        }

        $coreFields = $this->normalizeCoreFields($coreFields, allowPartial: true);

        if ($coreFields === []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => 'No updatable fields were provided.',
            ]);
        }

        try {
            $asset = $this->assetModel->update($assetId, $coreFields);
            $this->logAssetUpdates($request, $assetId, $existingAsset, $coreFields);
            $this->auditLogger->logFromRequest(
                $request,
                $this->actorUserId(),
                AuditLog::ACTION_UPDATED,
                AuditLog::ENTITY_ASSET,
                $assetId,
                $this->snapshotAssetAudit($existingAsset, $coreFields),
                $this->snapshotAssetAudit($asset ?? [], $coreFields)
            );
        } catch (\RuntimeException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        if ($asset === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => 'Asset not found.',
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => 'Asset updated successfully.',
            'data' => $asset,
        ]);
    }

    public function history(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $assetId = (int) ($args['id'] ?? 0);

        if ($assetId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => 'A valid asset id is required.',
            ]);
        }

        $asset = $this->assetModel->findById($assetId);

        if ($asset === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => 'Asset not found.',
            ]);
        }

        if (!$this->canAccessAsset($asset)) {
            return $this->jsonResponse($response, 403, [
                'status' => 'error',
                'message' => 'Bu varlığa erişim yetkiniz bulunmuyor.',
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->assetHistoryModel->findByAssetId($assetId),
        ]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $assetId = (int) ($args['id'] ?? 0);

        if ($assetId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => 'A valid asset id is required.',
            ]);
        }

        $existingAsset = $this->assetModel->findById($assetId);

        if ($existingAsset === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => 'Asset not found.',
            ]);
        }

        if (!$this->assetModel->deletePermanently($assetId)) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => 'Asset not found.',
            ]);
        }

        $this->auditLogger->logFromRequest(
            $request,
            $this->actorUserId(),
            AuditLog::ACTION_DELETED,
            AuditLog::ENTITY_ASSET,
            $assetId,
            [
                'asset_tag' => (string) ($existingAsset['asset_tag'] ?? ''),
                'name' => (string) ($existingAsset['name'] ?? ''),
            ],
            null
        );

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => 'Asset permanently deleted.',
        ]);
    }

    public function assign(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $assetId = (int) ($args['id'] ?? 0);

        if ($assetId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('assign_invalid_asset'),
            ]);
        }

        $existingAsset = $this->assetModel->findById($assetId);

        if ($existingAsset === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('assign_asset_not_found'),
            ]);
        }

        if (trim((string) ($existingAsset['assigned_to'] ?? '')) !== '') {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('assign_already_assigned'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null || !array_key_exists('personnel_id', $payload)) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('assign_missing_user'),
            ]);
        }

        $userErrors = $this->validatePersonnelId($payload['personnel_id']);

        if ($userErrors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('assign_invalid_user'),
                'errors' => ['personnel_id' => $userErrors],
            ]);
        }

        $personnelId = (int) $payload['personnel_id'];
        $person = $this->personnelModel->findById($personnelId);

        if ($person !== null && ($person['status'] ?? '') === Personnel::STATUS_OFFBOARDED) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('assign_offboarded_user'),
            ]);
        }

        $assignedTo = $this->resolveAssignedToReference($personnelId) ?? $this->resolvePersonnelName($personnelId) ?? ('personnel #' . $personnelId);

        $updateFields = [
            'assigned_to' => $assignedTo,
            'status' => 'deployed',
        ];

        if (array_key_exists('location', $payload)) {
            $updateFields['location'] = trim((string) $payload['location']);
        }

        if (array_key_exists('building', $payload)) {
            $updateFields['building'] = trim((string) $payload['building']);
        }

        if (array_key_exists('location_id', $payload) && $payload['location_id'] !== null && $payload['location_id'] !== '') {
            $location = $this->locationModel->findById((int) $payload['location_id']);

            if ($location !== null) {
                $updateFields['location'] = trim((string) ($location['name'] ?? ''));
                $updateFields['building'] = trim((string) ($location['building'] ?? ''));
            }
        }

        $previousStatus = (string) ($existingAsset['status'] ?? 'ready');
        $previousLocation = trim((string) ($existingAsset['location'] ?? ''));
        $previousBuilding = trim((string) ($existingAsset['building'] ?? ''));

        try {
            $asset = $this->assetModel->update($assetId, $updateFields);
        } catch (\RuntimeException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        if ($asset === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('assign_asset_not_found'),
            ]);
        }

        $personnelName = $this->resolvePersonnelName($personnelId) ?? ('personnel #' . $personnelId);

        $this->logAssetHistory(
            $request,
            $assetId,
            'assigned',
            $this->actorUserId(),
            $personnelId,
            sprintf(__('asset_history_assigned_to'), $personnelName)
        );

        if ($previousStatus !== 'deployed') {
            $this->logAssetHistory(
                $request,
                $assetId,
                'status_change',
                $this->actorUserId(),
                null,
                sprintf(__('asset_history_status_changed'), $previousStatus, 'deployed')
            );
        }

        if (
            array_key_exists('location', $updateFields)
            || array_key_exists('building', $updateFields)
        ) {
            $this->logLocationChange(
                $request,
                $assetId,
                $previousLocation !== '' || $previousBuilding !== ''
                    ? trim($previousBuilding . ' / ' . $previousLocation, ' /')
                    : null,
                trim(
                    (string) ($updateFields['building'] ?? $previousBuilding) . ' / ' . (string) ($updateFields['location'] ?? $previousLocation),
                    ' /'
                )
            );
        }

        $this->auditLogger->logFromRequest(
            $request,
            $this->actorUserId(),
            AuditLog::ACTION_ASSIGNED,
            AuditLog::ENTITY_ASSET,
            $assetId,
            [
                'asset_tag' => (string) ($existingAsset['asset_tag'] ?? ''),
                'assigned_to' => (string) ($existingAsset['assigned_to'] ?? ''),
                'status' => $previousStatus,
            ],
            [
                'asset_tag' => (string) ($asset['asset_tag'] ?? ''),
                'assigned_to' => $assignedTo,
                'personnel_name' => $personnelName,
                'status' => 'deployed',
            ]
        );

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('assign_success'),
            'data' => $asset,
        ]);
    }

    public function returnToStorage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $assetId = (int) ($args['id'] ?? 0);

        if ($assetId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('return_invalid_asset'),
            ]);
        }

        $existingAsset = $this->assetModel->findById($assetId);

        if ($existingAsset === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('return_asset_not_found'),
            ]);
        }

        if (trim((string) ($existingAsset['assigned_to'] ?? '')) === '') {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('return_not_assigned'),
            ]);
        }

        $previousAssignedTo = (string) ($existingAsset['assigned_to'] ?? '');
        $previousStatus = (string) ($existingAsset['status'] ?? 'ready');

        try {
            $asset = $this->assetModel->update($assetId, [
                'assigned_to' => '',
                'status' => 'ready',
            ]);
        } catch (\RuntimeException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        if ($asset === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('return_asset_not_found'),
            ]);
        }

        $this->logAssetHistory(
            $request,
            $assetId,
            'returned',
            $this->actorUserId(),
            null,
            sprintf(__('asset_history_returned') . ' (%s)', $previousAssignedTo)
        );

        if ($previousStatus !== 'ready') {
            $this->logAssetHistory(
                $request,
                $assetId,
                'status_change',
                $this->actorUserId(),
                null,
                sprintf(
                    __('asset_history_status_changed'),
                    $previousStatus,
                    'ready'
                )
            );
        }

        $this->auditLogger->logFromRequest(
            $request,
            $this->actorUserId(),
            AuditLog::ACTION_RETURNED,
            AuditLog::ENTITY_ASSET,
            $assetId,
            [
                'asset_tag' => (string) ($existingAsset['asset_tag'] ?? ''),
                'assigned_to' => $previousAssignedTo,
                'status' => $previousStatus,
            ],
            [
                'asset_tag' => (string) ($asset['asset_tag'] ?? ''),
                'status' => 'ready',
            ]
        );

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('return_success'),
            'data' => $asset,
        ]);
    }

    public function transfer(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $assetId = (int) ($args['id'] ?? 0);

        if ($assetId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('transfer_invalid_asset'),
            ]);
        }

        $existingAsset = $this->assetModel->findById($assetId);

        if ($existingAsset === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('transfer_asset_not_found'),
            ]);
        }

        if (trim((string) ($existingAsset['assigned_to'] ?? '')) === '') {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('transfer_not_assigned'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null || !array_key_exists('personnel_id', $payload)) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('transfer_missing_user'),
            ]);
        }

        $userErrors = $this->validatePersonnelId($payload['personnel_id']);

        if ($userErrors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('transfer_invalid_user'),
                'errors' => ['personnel_id' => $userErrors],
            ]);
        }

        $previousAssignedTo = (string) ($existingAsset['assigned_to'] ?? '');
        $newUserId = (int) $payload['personnel_id'];
        $newAssignedTo = $this->resolveAssignedToReference($newUserId)
            ?? $this->resolvePersonnelName($newUserId)
            ?? ('personnel #' . $newUserId);
        $previousStatus = (string) ($existingAsset['status'] ?? 'ready');

        if (strcasecmp($previousAssignedTo, $newAssignedTo) === 0) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('transfer_same_user'),
            ]);
        }

        $person = $this->personnelModel->findById($newUserId);

        if ($person !== null && ($person['status'] ?? '') === Personnel::STATUS_OFFBOARDED) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('transfer_offboarded_user'),
            ]);
        }

        $updateFields = [
            'assigned_to' => $newAssignedTo,
        ];

        if ($previousStatus !== 'deployed') {
            $updateFields['status'] = 'deployed';
        }

        try {
            $asset = $this->assetModel->update($assetId, $updateFields);
        } catch (\RuntimeException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        if ($asset === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('transfer_asset_not_found'),
            ]);
        }

        $oldUserName = $previousAssignedTo !== '' ? $previousAssignedTo : __('not_assigned');
        $newUserName = $this->resolvePersonnelName($newUserId) ?? ('personnel #' . $newUserId);

        $this->logAssetHistory(
            $request,
            $assetId,
            'transferred',
            $this->actorUserId(),
            $newUserId,
            sprintf(
                __('asset_history_transferred'),
                $oldUserName,
                $newUserName
            )
        );

        if ($previousStatus !== 'deployed') {
            $this->logAssetHistory(
                $request,
                $assetId,
                'status_change',
                $this->actorUserId(),
                null,
                sprintf(__('asset_history_status_changed'), $previousStatus, 'deployed')
            );
        }

        $this->auditLogger->logFromRequest(
            $request,
            $this->actorUserId(),
            AuditLog::ACTION_TRANSFERRED,
            AuditLog::ENTITY_ASSET,
            $assetId,
            [
                'asset_tag' => (string) ($existingAsset['asset_tag'] ?? ''),
                'assigned_to' => $previousAssignedTo,
                'personnel_name' => $oldUserName,
            ],
            [
                'asset_tag' => (string) ($asset['asset_tag'] ?? ''),
                'assigned_to' => $newAssignedTo,
                'personnel_name' => $newUserName,
            ]
        );

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('transfer_success'),
            'data' => $asset,
        ]);
    }

    public function importTemplate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $csv = AssetCsvImportService::templateCsvContent();

        $response->getBody()->write($csv);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="asset_import_template.csv"');
    }

    public function exportCsv(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $assets = $this->assetModel->findAllForDashboard();
        $csv = $this->assetCsvImportService->exportToCsv($assets);
        $filename = 'standart_envanter_export_' . date('Y-m-d') . '.csv';

        $response->getBody()->write($csv);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function importCsv(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['file'] ?? $uploadedFiles['csv'] ?? null;

        if ($file === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('import_file_missing'),
            ]);
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('import_file_upload_error'),
            ]);
        }

        $stream = $file->getStream();
        $csvContent = (string) $stream->getContents();

        if (trim($csvContent) === '') {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('import_csv_empty'),
            ]);
        }

        $maxBytes = 5 * 1024 * 1024;

        if (strlen($csvContent) > $maxBytes) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('import_file_too_large'),
            ]);
        }

        $originalFilename = $file->getClientFilename() ?? 'import.csv';
        $result = $this->inventoryImportService->importFromUploadedFile($csvContent, $originalFilename);
        $actorUserId = $this->actorUserId();

        foreach ($result['created_assets'] as $assetId) {
            $this->logAssetHistory(
                $request,
                $assetId,
                'created',
                $actorUserId,
                null,
                __('asset_history_imported_csv')
            );
        }

        foreach ($result['updated_assets'] ?? [] as $assetId) {
            $this->logAssetHistory(
                $request,
                $assetId,
                'updated',
                $actorUserId,
                null,
                __('asset_history_updated_inventory_import')
            );
        }

        $imported = (int) $result['imported'];
        $updated = (int) ($result['updated'] ?? 0);
        $failed = (int) $result['failed'];
        $processed = $imported + $updated;

        if ($processed === 0 && $failed > 0) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('import_all_failed'),
                'data' => $result,
            ]);
        }

        $message = $failed > 0
            ? sprintf(__('inventory_import_partial_success'), $imported, $updated, $failed)
            : ($updated > 0
                ? sprintf(__('inventory_import_mixed_success'), $imported, $updated)
                : sprintf(__('import_success'), $imported));

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => $message,
            'data' => $result,
        ]);
    }

    /**
     * @param array<string, mixed> $asset
     */
    private function canAccessAsset(array $asset): bool
    {
        $role = $this->sessionAuthService->role();

        if ($this->userModel->isOperationalRole($role)) {
            return true;
        }

        return $this->endUserContextService->ownsAsset($asset);
    }

    /**
     * @param array<string, mixed> $asset
     * @param array<string, mixed> $coreFields
     */
    private function logAssetCreation(ServerRequestInterface $request, array $asset, array $coreFields): void
    {
        $assetId = (int) $asset['id'];

        $this->logAssetHistory(
            $request,
            $assetId,
            'created',
            null,
            null,
            sprintf('Asset created with tag %s', (string) $asset['asset_tag'])
        );

        $assignedTo = trim((string) ($coreFields['assigned_to'] ?? ''));

        if ($assignedTo !== '') {
            $this->logAssetHistory(
                $request,
                $assetId,
                'assigned',
                null,
                null,
                sprintf(__('asset_history_assigned_to'), $assignedTo)
            );
        }

        $location = trim((string) ($coreFields['location'] ?? ''));
        $building = trim((string) ($coreFields['building'] ?? ''));

        if ($location !== '' || $building !== '') {
            $this->logAssetHistory(
                $request,
                $assetId,
                'location_moved',
                null,
                null,
                sprintf(
                    __('asset_history_assigned_to_location'),
                    trim($building . ' / ' . $location, ' /')
                )
            );
        }
    }

    /**
     * @param array<string, mixed> $existingAsset
     * @param array<string, mixed> $coreFields
     */
    private function logAssetUpdates(ServerRequestInterface $request, int $assetId, array $existingAsset, array $coreFields): void
    {
        if (array_key_exists('assigned_to', $coreFields)) {
            $this->logAssignmentChange(
                $request,
                $assetId,
                trim((string) ($existingAsset['assigned_to'] ?? '')),
                trim((string) ($coreFields['assigned_to'] ?? ''))
            );
        }

        if (array_key_exists('location', $coreFields) || array_key_exists('building', $coreFields)) {
            $previousLocation = trim(
                (string) ($existingAsset['building'] ?? '') . ' / ' . (string) ($existingAsset['location'] ?? ''),
                ' /'
            );
            $nextLocation = trim(
                (string) ($coreFields['building'] ?? $existingAsset['building'] ?? '') . ' / '
                . (string) ($coreFields['location'] ?? $existingAsset['location'] ?? ''),
                ' /'
            );

            $this->logLocationChange(
                $request,
                $assetId,
                $previousLocation !== '' ? $previousLocation : null,
                $nextLocation !== '' ? $nextLocation : null
            );
        }

        if (array_key_exists('status', $coreFields)) {
            $oldStatus = (string) ($existingAsset['status'] ?? 'ready');
            $newStatus = (string) $coreFields['status'];

            if ($oldStatus !== $newStatus) {
                $this->logAssetHistory(
                    $request,
                    $assetId,
                    'status_change',
                    $this->actorUserId(),
                    null,
                    sprintf(__('asset_history_status_changed'), $oldStatus, $newStatus)
                );
            }
        }
    }

    private function logAssignmentChange(ServerRequestInterface $request, int $assetId, string $previousAssignedTo, string $nextAssignedTo): void
    {
        $oldAssignedTo = trim($previousAssignedTo);
        $newAssignedTo = trim($nextAssignedTo);
        $actorUserId = $this->actorUserId();

        if ($oldAssignedTo === $newAssignedTo) {
            return;
        }

        if ($newAssignedTo === '') {
            $this->logAssetHistory(
                $request,
                $assetId,
                'unassigned',
                $actorUserId,
                null,
                sprintf(
                    __('asset_history_unassigned_from'),
                    $oldAssignedTo !== '' ? $oldAssignedTo : __('not_assigned')
                )
            );

            return;
        }

        if ($oldAssignedTo === '') {
            $this->logAssetHistory(
                $request,
                $assetId,
                'assigned',
                $actorUserId,
                null,
                sprintf(__('asset_history_assigned_to'), $newAssignedTo)
            );

            return;
        }

        $this->logAssetHistory(
            $request,
            $assetId,
            'assigned',
            $actorUserId,
            null,
            sprintf(
                __('asset_history_reassigned'),
                $oldAssignedTo,
                $newAssignedTo
            )
        );
    }

    private function logLocationChange(ServerRequestInterface $request, int $assetId, ?string $previousLocation, ?string $nextLocation): void
    {
        $oldLocation = $previousLocation !== null ? trim($previousLocation) : '';
        $newLocation = $nextLocation !== null ? trim($nextLocation) : '';

        if ($oldLocation === $newLocation) {
            return;
        }

        if ($newLocation === '') {
            $this->logAssetHistory(
                $request,
                $assetId,
                'location_moved',
                null,
                null,
                sprintf(
                    __('asset_history_removed_from_location'),
                    $oldLocation !== '' ? $oldLocation : '-'
                )
            );

            return;
        }

        if ($oldLocation === '') {
            $this->logAssetHistory(
                $request,
                $assetId,
                'location_moved',
                null,
                null,
                sprintf(__('asset_history_assigned_to_location'), $newLocation)
            );

            return;
        }

        $this->logAssetHistory(
            $request,
            $assetId,
            'location_moved',
            null,
            null,
            sprintf(
                __('asset_history_moved_to_location'),
                $oldLocation,
                $newLocation
            )
        );
    }

    private function resolvePersonnelName(?int $personnelId): ?string
    {
        if ($personnelId === null) {
            return null;
        }

        $person = $this->personnelModel->findById($personnelId);

        if ($person !== null) {
            return (string) ($person['name'] ?? null);
        }

        $directoryUser = $this->userIntegrationFactory->make()->getUserById((string) $personnelId);

        return $directoryUser['name'] ?? null;
    }

    private function resolveLocationName(?int $locationId): ?string
    {
        if ($locationId === null) {
            return null;
        }

        $location = $this->locationModel->findById($locationId);

        if ($location === null) {
            return null;
        }

        $name = trim((string) ($location['name'] ?? ''));
        $building = trim((string) ($location['building'] ?? ''));

        if ($name === '') {
            return null;
        }

        if ($building === '') {
            return $name;
        }

        return $building . ' / ' . $name;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePayload(ServerRequestInterface $request): ?array
    {
        $parsedBody = $request->getParsedBody();

        if (is_array($parsedBody)) {
            return $parsedBody;
        }

        $rawBody = (string) $request->getBody();

        if ($rawBody === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{0: array<string, mixed>}
     */
    private function separatePayload(array $payload): array
    {
        $coreFields = [];
        $legacyKeys = ['category_id', 'personnel_id', 'location_id'];

        foreach ($payload as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (in_array($key, self::CORE_FIELDS, true) || in_array($key, $legacyKeys, true)) {
                $coreFields[$key] = $value;
            }
        }

        return [$coreFields];
    }

    private function resolveAssignedToReference(int $personnelId): ?string
    {
        $person = $this->personnelModel->findById($personnelId);

        if ($person === null) {
            return null;
        }

        $email = trim((string) ($person['email'] ?? ''));

        if ($email !== '') {
            return $email;
        }

        $name = trim((string) ($person['name'] ?? ''));

        return $name !== '' ? $name : null;
    }

    /**
     * @param array<string, mixed> $coreFields
     *
     * @return array<string, list<string>>
     */
    private function validateCoreFields(array $coreFields, ?int $ignoreAssetId = null): array
    {
        $errors = [];

        if (array_key_exists('asset_tag', $coreFields)) {
            $assetTag = trim((string) $coreFields['asset_tag']);

            if ($assetTag === '') {
                $errors['asset_tag'][] = 'The asset_tag field is required.';
            } elseif ($this->assetModel->assetTagExists($assetTag, $ignoreAssetId)) {
                $errors['asset_tag'][] = 'The asset_tag has already been taken.';
            }
        }

        if (array_key_exists('name', $coreFields)) {
            $name = trim((string) $coreFields['name']);

            if ($name === '') {
                $errors['name'][] = 'The name field is required.';
            }
        }

        if (array_key_exists('category_id', $coreFields) && !array_key_exists('type', $coreFields)) {
            $category = $this->categoryModel->findById((int) $coreFields['category_id']);
            $coreFields['type'] = trim((string) ($category['name'] ?? ''));
            unset($coreFields['category_id']);
        }

        if (array_key_exists('personnel_id', $coreFields) && !array_key_exists('assigned_to', $coreFields)) {
            $personnelId = (int) $coreFields['personnel_id'];
            $assignedTo = $this->resolveAssignedToReference($personnelId);

            if ($assignedTo !== null) {
                $coreFields['assigned_to'] = $assignedTo;
            }

            unset($coreFields['personnel_id']);
        }

        if (array_key_exists('location_id', $coreFields) && !array_key_exists('location', $coreFields)) {
            $locationId = (int) $coreFields['location_id'];
            $location = $this->locationModel->findById($locationId);

            if ($location !== null) {
                $coreFields['location'] = trim((string) ($location['name'] ?? ''));
                $coreFields['building'] = trim((string) ($location['building'] ?? ''));
            }

            unset($coreFields['location_id']);
        }

        if (array_key_exists('type', $coreFields) && trim((string) $coreFields['type']) === '') {
            $errors['type'][] = 'The type field is required.';
        }

        if (array_key_exists('status', $coreFields) && trim((string) $coreFields['status']) === '') {
            $errors['status'][] = 'The status field cannot be empty when provided.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $coreFields
     *
     * @return array<string, mixed>
     */
    private function normalizeCoreFields(array $coreFields, bool $allowPartial = false): array
    {
        if (array_key_exists('category_id', $coreFields) && !array_key_exists('type', $coreFields)) {
            $category = $this->categoryModel->findById((int) $coreFields['category_id']);
            $coreFields['type'] = trim((string) ($category['name'] ?? ''));
            unset($coreFields['category_id']);
        }

        if (array_key_exists('personnel_id', $coreFields) && !array_key_exists('assigned_to', $coreFields)) {
            $assignedTo = $this->resolveAssignedToReference((int) $coreFields['personnel_id']);

            if ($assignedTo !== null) {
                $coreFields['assigned_to'] = $assignedTo;
            }

            unset($coreFields['personnel_id']);
        }

        if (array_key_exists('location_id', $coreFields) && !array_key_exists('location', $coreFields)) {
            $location = $this->locationModel->findById((int) $coreFields['location_id']);

            if ($location !== null) {
                $coreFields['location'] = trim((string) ($location['name'] ?? ''));
                $coreFields['building'] = trim((string) ($location['building'] ?? ''));
            }

            unset($coreFields['location_id']);
        }

        $normalized = [];

        foreach (self::CORE_FIELDS as $field) {
            if (!array_key_exists($field, $coreFields)) {
                continue;
            }

            $value = $coreFields[$field];

            if ($field === 'serial_number') {
                $serialNumber = $value !== null ? trim((string) $value) : '';
                $normalized[$field] = $serialNumber === '' ? null : $serialNumber;

                continue;
            }

            if ($field === 'status') {
                $status = trim((string) ($value ?? 'ready'));
                $normalized[$field] = $status !== '' ? $status : 'ready';

                continue;
            }

            $normalized[$field] = trim((string) ($value ?? ''));
        }

        if (!$allowPartial) {
            foreach (['asset_tag', 'name'] as $requiredField) {
                if (!array_key_exists($requiredField, $normalized)) {
                    $normalized[$requiredField] = trim((string) ($coreFields[$requiredField] ?? ''));
                }
            }
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function validatePersonnelId(mixed $personnelId): array
    {
        if ($personnelId === null || $personnelId === '') {
            return [];
        }

        if (!is_numeric($personnelId)) {
            return ['The personnel_id must be a valid integer.'];
        }

        $normalizedPersonnelId = (int) $personnelId;

        if ($normalizedPersonnelId <= 0) {
            return ['The personnel_id must be a positive integer.'];
        }

        if ($this->personnelModel->findById($normalizedPersonnelId) !== null) {
            return [];
        }

        $driver = $this->userIntegrationFactory->make();
        $directoryUser = $driver->getUserById((string) $normalizedPersonnelId);

        if ($directoryUser === null) {
            return ['The selected personnel_id does not exist.'];
        }

        $this->personnelModel->syncFromDirectory($directoryUser);

        return [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(ResponseInterface $response, int $statusCode, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }

    private function logAssetHistory(
        ServerRequestInterface $request,
        int $assetId,
        string $action,
        ?int $userId,
        ?int $targetPersonnelId,
        ?string $notes
    ): void {
        $this->assetHistoryModel->log(
            $assetId,
            $action,
            $userId,
            $targetPersonnelId,
            $this->appendClientIpToNotes($notes, $request)
        );
    }

    /**
     * @param array<string, mixed> $asset
     * @param array<string, mixed> $changedFields
     *
     * @return array<string, mixed>
     */
    private function snapshotAssetAudit(array $asset, array $changedFields = []): array
    {
        $snapshot = [
            'asset_tag' => (string) ($asset['asset_tag'] ?? ''),
            'name' => (string) ($asset['name'] ?? ''),
        ];

        foreach ($changedFields as $field => $value) {
            if (in_array($field, self::CORE_FIELDS, true)) {
                $snapshot[$field] = $asset[$field] ?? $value;
            }
        }

        return $snapshot;
    }

    private function actorUserId(): ?int
    {
        return $this->endUserContextService->resolveLegacyUserId();
    }

    private function appendClientIpToNotes(?string $notes, ServerRequestInterface $request): ?string
    {
        $clientIp = $this->clientIpResolver->resolveFromRequest($request);

        if ($clientIp === '') {
            return $notes;
        }

        $suffix = sprintf('[client_ip: %s]', $clientIp);

        if ($notes === null || trim($notes) === '') {
            return $suffix;
        }

        return $notes . ' ' . $suffix;
    }
}
