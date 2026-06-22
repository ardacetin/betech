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
        'serial_number',
        'name',
        'category_id',
        'status',
        'personnel_id',
        'location_id',
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
        $assets = $this->assetModel->findAllForDashboard($activeFilters, $filterDefinitions);

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $assets,
            'meta' => [
                'total' => count($assets),
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

        [$coreFields, $properties] = $this->separatePayload($payload);
        $properties = $this->filterOptionalProperties($properties);
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
            $asset = $this->assetModel->create($coreFields, $properties);
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

        [$coreFields, $properties] = $this->separatePayload($payload);

        if (array_key_exists('properties', $payload)) {
            $properties = $this->filterOptionalProperties($properties);
        }

        $errors = $this->validateCoreFields($coreFields, $assetId);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $errors,
            ]);
        }

        $coreFields = $this->normalizeCoreFields($coreFields, allowPartial: true);

        if ($coreFields === [] && $properties === []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => 'No updatable fields were provided.',
            ]);
        }

        try {
            $asset = $this->assetModel->update(
                $assetId,
                $coreFields,
                array_key_exists('properties', $payload) ? $properties : null
            );
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

        if (($existingAsset['personnel_id'] ?? null) !== null) {
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

        $updateFields = [
            'personnel_id' => $personnelId,
            'status' => 'deployed',
        ];

        if (array_key_exists('location_id', $payload)) {
            $locationErrors = $this->validateLocationId($payload['location_id']);

            if ($locationErrors !== []) {
                return $this->jsonResponse($response, 422, [
                    'status' => 'error',
                    'message' => __('assign_invalid_location'),
                    'errors' => ['location_id' => $locationErrors],
                ]);
            }

            if ($payload['location_id'] !== null && $payload['location_id'] !== '') {
                $updateFields['location_id'] = (int) $payload['location_id'];
            }
        }

        $previousStatus = (string) ($existingAsset['status'] ?? 'ready');
        $previousLocationId = $existingAsset['location_id'] ?? null;

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

        if (array_key_exists('location_id', $updateFields)) {
            $this->logLocationChange(
                $request,
                $assetId,
                $previousLocationId,
                $updateFields['location_id']
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
                'personnel_id' => $existingAsset['personnel_id'] ?? null,
                'status' => $previousStatus,
            ],
            [
                'asset_tag' => (string) ($asset['asset_tag'] ?? ''),
                'personnel_id' => $personnelId,
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

        if (($existingAsset['personnel_id'] ?? null) === null) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('return_not_assigned'),
            ]);
        }

        $previousUserId = (int) $existingAsset['personnel_id'];
        $previousStatus = (string) ($existingAsset['status'] ?? 'ready');

        try {
            $asset = $this->assetModel->update($assetId, [
                'personnel_id' => null,
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
            $previousUserId,
            __('asset_history_returned')
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
                'personnel_id' => $previousUserId,
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

        if (($existingAsset['personnel_id'] ?? null) === null) {
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

        $previousUserId = (int) $existingAsset['personnel_id'];
        $newUserId = (int) $payload['personnel_id'];
        $previousStatus = (string) ($existingAsset['status'] ?? 'ready');

        if ($newUserId === $previousUserId) {
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
            'personnel_id' => $newUserId,
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

        $oldUserName = $this->resolvePersonnelName($previousUserId) ?? ('personnel #' . $previousUserId);
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
                'personnel_id' => $previousUserId,
                'personnel_name' => $oldUserName,
            ],
            [
                'asset_tag' => (string) ($asset['asset_tag'] ?? ''),
                'personnel_id' => $newUserId,
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
        $filename = 'assets_export_' . date('Y-m-d') . '.csv';

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

        $result = $this->assetCsvImportService->importFromString($csvContent);
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

        $imported = (int) $result['imported'];
        $failed = (int) $result['failed'];

        if ($imported === 0 && $failed > 0) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('import_all_failed'),
                'data' => $result,
            ]);
        }

        $message = $failed > 0
            ? sprintf(__('import_partial_success'), $imported, $failed)
            : sprintf(__('import_success'), $imported);

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

        if (!array_key_exists('personnel_id', $coreFields) || $coreFields['personnel_id'] === null) {
            if (array_key_exists('location_id', $coreFields) && $coreFields['location_id'] !== null) {
                $locationId = (int) $coreFields['location_id'];
                $locationName = $this->resolveLocationName($locationId);

                $this->logAssetHistory(
                    $request,
                    $assetId,
                    'location_moved',
                    null,
                    null,
                    sprintf(
                        __('asset_history_assigned_to_location'),
                        $locationName ?? ('lokasyon #' . $locationId)
                    )
                );
            }

            return;
        }

        $targetUserId = (int) $coreFields['personnel_id'];
        $targetUserName = $this->resolvePersonnelName($targetUserId);

        $this->logAssetHistory(
            $request,
            $assetId,
            'assigned',
            null,
            $targetUserId,
            sprintf(
                'Assigned to %s on creation',
                $targetUserName ?? ('user #' . $targetUserId)
            )
        );

        if (array_key_exists('location_id', $coreFields) && $coreFields['location_id'] !== null) {
            $locationId = (int) $coreFields['location_id'];
            $locationName = $this->resolveLocationName($locationId);

            $this->logAssetHistory(
                $request,
                $assetId,
                'location_moved',
                null,
                null,
                sprintf(
                    __('asset_history_assigned_to_location'),
                    $locationName ?? ('lokasyon #' . $locationId)
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
        if (array_key_exists('personnel_id', $coreFields)) {
            $this->logAssignmentChange(
                $request,
                $assetId,
                $existingAsset['personnel_id'] ?? null,
                $coreFields['personnel_id']
            );
        }

        if (array_key_exists('location_id', $coreFields)) {
            $this->logLocationChange(
                $request,
                $assetId,
                $existingAsset['location_id'] ?? null,
                $coreFields['location_id']
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

    private function logAssignmentChange(ServerRequestInterface $request, int $assetId, mixed $previousUserId, mixed $nextUserId): void
    {
        $oldUserId = $previousUserId !== null ? (int) $previousUserId : null;
        $newUserId = $nextUserId !== null ? (int) $nextUserId : null;
        $actorUserId = $this->actorUserId();

        if ($oldUserId === $newUserId) {
            return;
        }

        if ($newUserId === null) {
            $oldUserName = $this->resolvePersonnelName($oldUserId);

            $this->logAssetHistory(
                $request,
                $assetId,
                'unassigned',
                $actorUserId,
                $oldUserId,
                sprintf(
                    __('asset_history_unassigned_from'),
                    $oldUserName ?? ('user #' . $oldUserId)
                )
            );

            return;
        }

        $newUserName = $this->resolvePersonnelName($newUserId);

        if ($oldUserId === null) {
            $this->logAssetHistory(
                $request,
                $assetId,
                'assigned',
                $actorUserId,
                $newUserId,
                sprintf(
                    __('asset_history_assigned_to'),
                    $newUserName ?? ('user #' . $newUserId)
                )
            );

            return;
        }

        $oldUserName = $this->resolvePersonnelName($oldUserId);

        $this->logAssetHistory(
            $request,
            $assetId,
            'assigned',
            $actorUserId,
            $newUserId,
            sprintf(
                __('asset_history_reassigned'),
                $oldUserName ?? ('user #' . $oldUserId),
                $newUserName ?? ('user #' . $newUserId)
            )
        );
    }

    private function logLocationChange(ServerRequestInterface $request, int $assetId, mixed $previousLocationId, mixed $nextLocationId): void
    {
        $oldLocationId = $previousLocationId !== null ? (int) $previousLocationId : null;
        $newLocationId = $nextLocationId !== null ? (int) $nextLocationId : null;

        if ($oldLocationId === $newLocationId) {
            return;
        }

        if ($newLocationId === null) {
            $oldLocationName = $this->resolveLocationName($oldLocationId);

            $this->logAssetHistory(
                $request,
                $assetId,
                'location_moved',
                null,
                null,
                sprintf(
                    __('asset_history_removed_from_location'),
                    $oldLocationName ?? ('lokasyon #' . $oldLocationId)
                )
            );

            return;
        }

        $newLocationName = $this->resolveLocationName($newLocationId);

        if ($oldLocationId === null) {
            $this->logAssetHistory(
                $request,
                $assetId,
                'location_moved',
                null,
                null,
                sprintf(
                    __('asset_history_assigned_to_location'),
                    $newLocationName ?? ('lokasyon #' . $newLocationId)
                )
            );

            return;
        }

        $oldLocationName = $this->resolveLocationName($oldLocationId);

        $this->logAssetHistory(
            $request,
            $assetId,
            'location_moved',
            null,
            null,
            sprintf(
                __('asset_history_moved_to_location'),
                $oldLocationName ?? ('lokasyon #' . $oldLocationId),
                $newLocationName ?? ('lokasyon #' . $newLocationId)
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
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function separatePayload(array $payload): array
    {
        $coreFields = [];
        $properties = [];

        foreach ($payload as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (in_array($key, self::CORE_FIELDS, true)) {
                $coreFields[$key] = $value;
                continue;
            }

            $properties[$key] = $value;
        }

        return [$coreFields, $properties];
    }

    /**
     * Category-driven and global custom properties are optional; omit empty values.
     *
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    private function filterOptionalProperties(array $properties): array
    {
        $filtered = [];

        foreach ($properties as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
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

        if (array_key_exists('category_id', $coreFields)) {
            if ($coreFields['category_id'] === '' || $coreFields['category_id'] === null) {
                $errors['category_id'][] = 'The category_id field is required.';
            } elseif (!is_numeric($coreFields['category_id'])) {
                $errors['category_id'][] = 'The category_id must be a valid integer.';
            } else {
                $categoryId = (int) $coreFields['category_id'];

                if ($categoryId <= 0) {
                    $errors['category_id'][] = 'The category_id must be a positive integer.';
                } elseif (!$this->assetModel->categoryExists($categoryId)) {
                    $errors['category_id'][] = 'The selected category_id does not exist.';
                }
            }
        }

        if (array_key_exists('status', $coreFields) && trim((string) $coreFields['status']) === '') {
            $errors['status'][] = 'The status field cannot be empty when provided.';
        }

        if (array_key_exists('personnel_id', $coreFields)) {
            $userErrors = $this->validatePersonnelId($coreFields['personnel_id']);

            if ($userErrors !== []) {
                $errors['personnel_id'] = $userErrors;
            }
        }

        if (array_key_exists('location_id', $coreFields)) {
            $locationErrors = $this->validateLocationId($coreFields['location_id']);

            if ($locationErrors !== []) {
                $errors['location_id'] = $locationErrors;
            }
        }

        return $errors;
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
     * @return list<string>
     */
    private function validateLocationId(mixed $locationId): array
    {
        if ($locationId === null || $locationId === '') {
            return [];
        }

        if (!is_numeric($locationId)) {
            return [__('location_invalid_id')];
        }

        $normalizedLocationId = (int) $locationId;

        if ($normalizedLocationId <= 0) {
            return [__('location_invalid_id')];
        }

        if (!$this->assetModel->locationExists($normalizedLocationId)) {
            return [__('location_not_found')];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $coreFields
     *
     * @return array<string, mixed>
     */
    private function normalizeCoreFields(array $coreFields, bool $allowPartial = false): array
    {
        $normalized = [];

        if (array_key_exists('asset_tag', $coreFields)) {
            $normalized['asset_tag'] = trim((string) $coreFields['asset_tag']);
        } elseif (!$allowPartial) {
            $normalized['asset_tag'] = trim((string) ($coreFields['asset_tag'] ?? ''));
        }

        if (array_key_exists('name', $coreFields)) {
            $normalized['name'] = trim((string) $coreFields['name']);
        } elseif (!$allowPartial) {
            $normalized['name'] = trim((string) ($coreFields['name'] ?? ''));
        }

        if (array_key_exists('serial_number', $coreFields)) {
            $serialNumber = $coreFields['serial_number'] !== null
                ? trim((string) $coreFields['serial_number'])
                : null;
            $normalized['serial_number'] = $serialNumber === '' ? null : $serialNumber;
        } elseif (!$allowPartial) {
            $serialNumber = array_key_exists('serial_number', $coreFields) && $coreFields['serial_number'] !== null
                ? trim((string) $coreFields['serial_number'])
                : null;
            $normalized['serial_number'] = $serialNumber === '' ? null : $serialNumber;
        }

        if (array_key_exists('category_id', $coreFields)) {
            $normalized['category_id'] = (int) $coreFields['category_id'];
        } elseif (!$allowPartial) {
            $normalized['category_id'] = (int) ($coreFields['category_id'] ?? 0);
        }

        if (array_key_exists('status', $coreFields)) {
            $status = trim((string) $coreFields['status']);
            $normalized['status'] = $status !== '' ? $status : 'ready';
        } elseif (!$allowPartial) {
            $status = trim((string) ($coreFields['status'] ?? 'ready'));
            $normalized['status'] = $status !== '' ? $status : 'ready';
        }

        if (array_key_exists('personnel_id', $coreFields)) {
            if ($coreFields['personnel_id'] === null || $coreFields['personnel_id'] === '') {
                $normalized['personnel_id'] = null;
            } else {
                $normalized['personnel_id'] = (int) $coreFields['personnel_id'];
            }
        }

        if (array_key_exists('location_id', $coreFields)) {
            if ($coreFields['location_id'] === null || $coreFields['location_id'] === '') {
                $normalized['location_id'] = null;
            } else {
                $normalized['location_id'] = (int) $coreFields['location_id'];
            }
        }

        return $normalized;
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
        return $this->sessionAuthService->userId();
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
