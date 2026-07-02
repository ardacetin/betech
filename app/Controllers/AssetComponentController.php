<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AssetComponent;
use App\Models\AssetType;
use App\Models\AuditLog;
use App\Services\AssetTypeTableService;
use App\Services\AuditLogger;
use App\Services\Auth\SessionAuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AssetComponentController
{
    public function __construct(
        private readonly AssetComponent $assetComponentModel,
        private readonly AssetType $assetTypeModel,
        private readonly AssetTypeTableService $assetTypeTableService,
        private readonly SessionAuthService $sessionAuthService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $assetTypeId = $this->resolveAssetTypeId($args, $request);

        if ($assetTypeId === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('asset_type_invalid_id'),
            ]);
        }

        if ($this->assetTypeModel->findById($assetTypeId) === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('asset_type_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->assetComponentModel->findByAssetTypeId($assetTypeId),
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $assetTypeId = $this->resolveAssetTypeId($args, $request);

        if ($assetTypeId === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('asset_type_invalid_id'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('asset_component_invalid_payload'),
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
                'message' => __('asset_component_validation_failed'),
            ]);
        }

        try {
            $component = $this->assetComponentModel->create(
                $assetTypeId,
                $name,
                (string) ($payload['description'] ?? ''),
                (int) ($payload['sort_order'] ?? 0)
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('asset_component_create_error'),
            ]);
        }

        $this->auditLogger->logFromRequest(
            $request,
            $this->sessionAuthService->userId(),
            AuditLog::ACTION_CREATED,
            AuditLog::ENTITY_ASSET_COMPONENT,
            (int) ($component['id'] ?? 0),
            null,
            ['name' => (string) ($component['name'] ?? ''), 'asset_type_id' => $assetTypeId]
        );

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('asset_component_create_success'),
            'data' => $component,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $componentId = (int) ($args['id'] ?? 0);

        if ($componentId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('asset_component_invalid_id'),
            ]);
        }

        $existing = $this->assetComponentModel->findById($componentId);

        if ($existing === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('asset_component_not_found'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('asset_component_invalid_payload'),
            ]);
        }

        $name = array_key_exists('name', $payload)
            ? trim((string) $payload['name'])
            : (string) ($existing['name'] ?? '');

        if ($name === '') {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('asset_component_validation_failed'),
            ]);
        }

        try {
            $component = $this->assetComponentModel->update(
                $componentId,
                $name,
                (string) ($payload['description'] ?? ($existing['description'] ?? '')),
                array_key_exists('sort_order', $payload) ? (int) ($payload['sort_order'] ?? 0) : null
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('asset_component_update_error'),
            ]);
        }

        if ($component === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('asset_component_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('asset_component_update_success'),
            'data' => $component,
        ]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $componentId = (int) ($args['id'] ?? 0);

        if ($componentId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('asset_component_invalid_id'),
            ]);
        }

        $existing = $this->assetComponentModel->findById($componentId);

        if ($existing === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('asset_component_not_found'),
            ]);
        }

        try {
            $deleted = $this->assetComponentModel->delete($componentId);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('asset_component_delete_error'),
            ]);
        }

        if (!$deleted) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('asset_component_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('asset_component_delete_success'),
        ]);
    }

    /**
     * @param array<string, mixed> $args
     */
    private function resolveAssetTypeId(array $args, ServerRequestInterface $request): ?int
    {
        $typeArg = trim((string) ($args['typeId'] ?? $args['assetTypeId'] ?? ''));

        if ($typeArg !== '') {
            $resolved = $this->assetTypeTableService->resolveTypeIdFromIdentifier($typeArg);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        $queryType = trim((string) ($request->getQueryParams()['type'] ?? ''));

        if ($queryType !== '') {
            return $this->assetTypeTableService->resolveTypeIdFromIdentifier($queryType);
        }

        $payload = $this->resolvePayload($request);

        if (is_array($payload)) {
            $payloadTypeId = (int) ($payload['asset_type_id'] ?? 0);

            if ($payloadTypeId > 0) {
                return $payloadTypeId;
            }
        }

        return null;
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
