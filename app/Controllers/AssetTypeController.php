<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AssetType;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Services\Auth\SessionAuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AssetTypeController
{
    public function __construct(
        private readonly AssetType $assetTypeModel,
        private readonly SessionAuthService $sessionAuthService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $assetTypes = [];

            foreach ($this->assetTypeModel->findAll() as $assetType) {
                try {
                    $assetType['asset_count'] = $this->assetTypeModel->countAssets((int) ($assetType['id'] ?? 0));
                } catch (\Throwable) {
                    $assetType['asset_count'] = 0;
                }

                $assetTypes[] = $assetType;
            }

            return $this->jsonResponse($response, 200, [
                'status' => 'success',
                'data' => $assetTypes,
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('asset_types_fetch_error'),
            ]);
        }
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('asset_type_invalid_payload'),
            ]);
        }

        $payloadId = (int) ($payload['id'] ?? 0);

        if ($payloadId > 0) {
            return $this->update($request, $response, ['id' => (string) $payloadId]);
        }

        $name = trim((string) ($payload['name'] ?? ''));

        if ($name === '') {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('asset_type_validation_failed'),
                'errors' => ['name' => [__('asset_type_name_required')]],
            ]);
        }

        $sortOrder = (int) ($payload['sort_order'] ?? 0);

        try {
            $assetType = $this->assetTypeModel->create($name, $sortOrder > 0 ? $sortOrder : 0);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('asset_type_create_error'),
            ]);
        }

        $assetType['asset_count'] = 0;

        $this->auditLogger->logFromRequest(
            $request,
            $this->sessionAuthService->userId(),
            AuditLog::ACTION_CREATED,
            AuditLog::ENTITY_ASSET_TYPE,
            (int) ($assetType['id'] ?? 0),
            null,
            ['name' => (string) ($assetType['name'] ?? '')]
        );

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('asset_type_create_success'),
            'data' => $assetType,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $assetTypeId = (int) ($args['id'] ?? 0);

        if ($assetTypeId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('asset_type_invalid_id'),
            ]);
        }

        $existing = $this->assetTypeModel->findById($assetTypeId);

        if ($existing === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('asset_type_not_found'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('asset_type_invalid_payload'),
            ]);
        }

        $name = array_key_exists('name', $payload)
            ? trim((string) $payload['name'])
            : (string) ($existing['name'] ?? '');

        if ($name === '') {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('asset_type_validation_failed'),
                'errors' => ['name' => [__('asset_type_name_required')]],
            ]);
        }

        $sortOrder = array_key_exists('sort_order', $payload)
            ? (int) ($payload['sort_order'] ?? 0)
            : null;

        try {
            $assetType = $this->assetTypeModel->update(
                $assetTypeId,
                $name,
                $sortOrder !== null && $sortOrder > 0 ? $sortOrder : null
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('asset_type_update_error'),
            ]);
        }

        if ($assetType === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('asset_type_not_found'),
            ]);
        }

        $assetType['asset_count'] = $this->assetTypeModel->countAssets($assetTypeId);

        $this->auditLogger->logFromRequest(
            $request,
            $this->sessionAuthService->userId(),
            AuditLog::ACTION_UPDATED,
            AuditLog::ENTITY_ASSET_TYPE,
            $assetTypeId,
            ['name' => (string) ($existing['name'] ?? '')],
            ['name' => (string) ($assetType['name'] ?? '')]
        );

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('asset_type_update_success'),
            'data' => $assetType,
        ]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $assetTypeId = (int) ($args['id'] ?? 0);

        if ($assetTypeId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('asset_type_invalid_id'),
            ]);
        }

        $existing = $this->assetTypeModel->findById($assetTypeId);

        if ($existing === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('asset_type_not_found'),
            ]);
        }

        $assetCount = $this->assetTypeModel->countAssets($assetTypeId);

        if ($assetCount > 0) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('asset_type_delete_in_use'),
                'asset_count' => $assetCount,
            ]);
        }

        try {
            $deleted = $this->assetTypeModel->delete($assetTypeId);
        } catch (\RuntimeException) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('asset_type_delete_in_use'),
                'asset_count' => $this->assetTypeModel->countAssets($assetTypeId),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('asset_type_delete_error'),
            ]);
        }

        if (!$deleted) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('asset_type_not_found'),
            ]);
        }

        $this->auditLogger->logFromRequest(
            $request,
            $this->sessionAuthService->userId(),
            AuditLog::ACTION_DELETED,
            AuditLog::ENTITY_ASSET_TYPE,
            $assetTypeId,
            ['name' => (string) ($existing['name'] ?? '')],
            null
        );

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('asset_type_delete_success'),
        ]);
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
     */
    private function jsonResponse(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
