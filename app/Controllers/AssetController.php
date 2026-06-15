<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\Location;
use App\Models\User;
use App\Services\Auth\SessionAuthService;
use App\Services\Auth\UserIntegrationFactory;
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
        'user_id',
        'location_id',
    ];

    public function __construct(
        private readonly Asset $assetModel,
        private readonly AssetHistory $assetHistoryModel,
        private readonly UserIntegrationFactory $userIntegrationFactory,
        private readonly User $userModel,
        private readonly Location $locationModel,
        private readonly SessionAuthService $sessionAuthService
    ) {
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
            $this->logAssetCreation($asset, $coreFields);
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
            $this->logAssetUpdates($assetId, $existingAsset, $coreFields);
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

        if (!$this->assetModel->deletePermanently($assetId)) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => 'Asset not found.',
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => 'Asset permanently deleted.',
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

        if (($existingAsset['user_id'] ?? null) === null) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('return_not_assigned'),
            ]);
        }

        $previousUserId = (int) $existingAsset['user_id'];
        $previousStatus = (string) ($existingAsset['status'] ?? 'ready');

        try {
            $asset = $this->assetModel->update($assetId, [
                'user_id' => null,
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

        $this->assetHistoryModel->log(
            $assetId,
            'returned',
            $this->sessionAuthService->userId(),
            $previousUserId,
            __('asset_history_returned')
        );

        if ($previousStatus !== 'ready') {
            $this->assetHistoryModel->log(
                $assetId,
                'status_change',
                $this->sessionAuthService->userId(),
                null,
                sprintf(
                    __('asset_history_status_changed'),
                    $previousStatus,
                    'ready'
                )
            );
        }

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

        if (($existingAsset['user_id'] ?? null) === null) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('transfer_not_assigned'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null || !array_key_exists('user_id', $payload)) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('transfer_missing_user'),
            ]);
        }

        $userErrors = $this->validateUserId($payload['user_id']);

        if ($userErrors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('transfer_invalid_user'),
                'errors' => ['user_id' => $userErrors],
            ]);
        }

        $previousUserId = (int) $existingAsset['user_id'];
        $newUserId = (int) $payload['user_id'];

        if ($newUserId === $previousUserId) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('transfer_same_user'),
            ]);
        }

        try {
            $asset = $this->assetModel->update($assetId, [
                'user_id' => $newUserId,
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
                'message' => __('transfer_asset_not_found'),
            ]);
        }

        $oldUserName = $this->resolveUserName($previousUserId) ?? ('user #' . $previousUserId);
        $newUserName = $this->resolveUserName($newUserId) ?? ('user #' . $newUserId);

        $this->assetHistoryModel->log(
            $assetId,
            'transferred',
            $this->sessionAuthService->userId(),
            $newUserId,
            sprintf(
                __('asset_history_transferred'),
                $oldUserName,
                $newUserName
            )
        );

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('transfer_success'),
            'data' => $asset,
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

        $sessionUserId = $this->sessionAuthService->userId();

        if ($sessionUserId === null) {
            return false;
        }

        $assignedUserId = $asset['user_id'] ?? null;

        return $assignedUserId !== null && (int) $assignedUserId === $sessionUserId;
    }

    /**
     * @param array<string, mixed> $asset
     * @param array<string, mixed> $coreFields
     */
    private function logAssetCreation(array $asset, array $coreFields): void
    {
        $assetId = (int) $asset['id'];

        $this->assetHistoryModel->log(
            $assetId,
            'created',
            null,
            null,
            sprintf('Asset created with tag %s', (string) $asset['asset_tag'])
        );

        if (!array_key_exists('user_id', $coreFields) || $coreFields['user_id'] === null) {
            if (array_key_exists('location_id', $coreFields) && $coreFields['location_id'] !== null) {
                $locationId = (int) $coreFields['location_id'];
                $locationName = $this->resolveLocationName($locationId);

                $this->assetHistoryModel->log(
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

        $targetUserId = (int) $coreFields['user_id'];
        $targetUserName = $this->resolveUserName($targetUserId);

        $this->assetHistoryModel->log(
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

            $this->assetHistoryModel->log(
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
    private function logAssetUpdates(int $assetId, array $existingAsset, array $coreFields): void
    {
        if (array_key_exists('user_id', $coreFields)) {
            $this->logAssignmentChange(
                $assetId,
                $existingAsset['user_id'] ?? null,
                $coreFields['user_id']
            );
        }

        if (array_key_exists('location_id', $coreFields)) {
            $this->logLocationChange(
                $assetId,
                $existingAsset['location_id'] ?? null,
                $coreFields['location_id']
            );
        }

        if (array_key_exists('status', $coreFields)) {
            $oldStatus = (string) ($existingAsset['status'] ?? 'ready');
            $newStatus = (string) $coreFields['status'];

            if ($oldStatus !== $newStatus) {
                $this->assetHistoryModel->log(
                    $assetId,
                    'status_change',
                    null,
                    null,
                    sprintf('Status changed from %s to %s', $oldStatus, $newStatus)
                );
            }
        }
    }

    private function logAssignmentChange(int $assetId, mixed $previousUserId, mixed $nextUserId): void
    {
        $oldUserId = $previousUserId !== null ? (int) $previousUserId : null;
        $newUserId = $nextUserId !== null ? (int) $nextUserId : null;

        if ($oldUserId === $newUserId) {
            return;
        }

        if ($newUserId === null) {
            $oldUserName = $this->resolveUserName($oldUserId);

            $this->assetHistoryModel->log(
                $assetId,
                'unassigned',
                null,
                $oldUserId,
                sprintf(
                    'Assignment removed from %s',
                    $oldUserName ?? ('user #' . $oldUserId)
                )
            );

            return;
        }

        $newUserName = $this->resolveUserName($newUserId);

        if ($oldUserId === null) {
            $this->assetHistoryModel->log(
                $assetId,
                'assigned',
                null,
                $newUserId,
                sprintf(
                    'Assigned to %s',
                    $newUserName ?? ('user #' . $newUserId)
                )
            );

            return;
        }

        $oldUserName = $this->resolveUserName($oldUserId);

        $this->assetHistoryModel->log(
            $assetId,
            'assigned',
            null,
            $newUserId,
            sprintf(
                'Reassigned from %s to %s',
                $oldUserName ?? ('user #' . $oldUserId),
                $newUserName ?? ('user #' . $newUserId)
            )
        );
    }

    private function logLocationChange(int $assetId, mixed $previousLocationId, mixed $nextLocationId): void
    {
        $oldLocationId = $previousLocationId !== null ? (int) $previousLocationId : null;
        $newLocationId = $nextLocationId !== null ? (int) $nextLocationId : null;

        if ($oldLocationId === $newLocationId) {
            return;
        }

        if ($newLocationId === null) {
            $oldLocationName = $this->resolveLocationName($oldLocationId);

            $this->assetHistoryModel->log(
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
            $this->assetHistoryModel->log(
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

        $this->assetHistoryModel->log(
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

    private function resolveUserName(?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        $user = $this->userModel->findById($userId);

        if ($user !== null) {
            return (string) ($user['name'] ?? null);
        }

        $user = $this->userIntegrationFactory->make()->getUserById((string) $userId);

        return $user['name'] ?? null;
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

        if (array_key_exists('user_id', $coreFields)) {
            $userErrors = $this->validateUserId($coreFields['user_id']);

            if ($userErrors !== []) {
                $errors['user_id'] = $userErrors;
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
    private function validateUserId(mixed $userId): array
    {
        if ($userId === null || $userId === '') {
            return [];
        }

        if (!is_numeric($userId)) {
            return ['The user_id must be a valid integer.'];
        }

        $normalizedUserId = (int) $userId;

        if ($normalizedUserId <= 0) {
            return ['The user_id must be a positive integer.'];
        }

        $driver = $this->userIntegrationFactory->make();
        $user = $this->userModel->findById($normalizedUserId);

        if ($user === null) {
            $user = $driver->getUserById((string) $normalizedUserId);
        }

        if ($user === null) {
            return ['The selected user_id does not exist.'];
        }

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

        if (array_key_exists('user_id', $coreFields)) {
            if ($coreFields['user_id'] === null || $coreFields['user_id'] === '') {
                $normalized['user_id'] = null;
            } else {
                $normalized['user_id'] = (int) $coreFields['user_id'];
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
}
