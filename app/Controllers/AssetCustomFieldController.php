<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AssetComponent;
use App\Models\AssetCustomField;
use App\Models\AssetType;
use App\Models\AuditLog;
use App\Services\AssetTypeTableService;
use App\Services\AuditLogger;
use App\Services\Auth\SessionAuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AssetCustomFieldController
{
    public function __construct(
        private readonly AssetCustomField $assetCustomFieldModel,
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
            'data' => $this->assetCustomFieldModel->findByAssetTypeId($assetTypeId),
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
                'message' => __('asset_custom_field_invalid_payload'),
            ]);
        }

        $payloadId = (int) ($payload['id'] ?? 0);

        if ($payloadId > 0) {
            return $this->update($request, $response, ['id' => (string) $payloadId]);
        }

        $label = trim((string) ($payload['label'] ?? ''));

        if ($label === '') {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('asset_custom_field_validation_failed'),
                'errors' => ['label' => [__('asset_custom_field_label_required')]],
            ]);
        }

        try {
            $field = $this->assetCustomFieldModel->create(
                $assetTypeId,
                $label,
                (string) ($payload['field_type'] ?? 'varchar'),
                $this->normalizeOptions($payload['options'] ?? []),
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
                'message' => __('asset_custom_field_create_error'),
            ]);
        }

        $this->auditLogger->logFromRequest(
            $request,
            $this->sessionAuthService->userId(),
            AuditLog::ACTION_CREATED,
            AuditLog::ENTITY_ASSET_CUSTOM_FIELD,
            (int) ($field['id'] ?? 0),
            null,
            ['label' => (string) ($field['label'] ?? ''), 'asset_type_id' => $assetTypeId]
        );

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('asset_custom_field_create_success'),
            'data' => $field,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $fieldId = (int) ($args['id'] ?? 0);

        if ($fieldId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('asset_custom_field_invalid_id'),
            ]);
        }

        $existing = $this->assetCustomFieldModel->findById($fieldId);

        if ($existing === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('asset_custom_field_not_found'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('asset_custom_field_invalid_payload'),
            ]);
        }

        $label = array_key_exists('label', $payload)
            ? trim((string) $payload['label'])
            : (string) ($existing['label'] ?? '');

        if ($label === '') {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('asset_custom_field_validation_failed'),
            ]);
        }

        try {
            $field = $this->assetCustomFieldModel->update(
                $fieldId,
                $label,
                (string) ($payload['field_type'] ?? ($existing['field_type'] ?? 'varchar')),
                array_key_exists('options', $payload)
                    ? $this->normalizeOptions($payload['options'])
                    : (array) ($existing['options'] ?? []),
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
                'message' => __('asset_custom_field_update_error'),
            ]);
        }

        if ($field === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('asset_custom_field_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('asset_custom_field_update_success'),
            'data' => $field,
        ]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $fieldId = (int) ($args['id'] ?? 0);

        if ($fieldId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('asset_custom_field_invalid_id'),
            ]);
        }

        $existing = $this->assetCustomFieldModel->findById($fieldId);

        if ($existing === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('asset_custom_field_not_found'),
            ]);
        }

        try {
            $deleted = $this->assetCustomFieldModel->delete($fieldId);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('asset_custom_field_delete_error'),
            ]);
        }

        if (!$deleted) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('asset_custom_field_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('asset_custom_field_delete_success'),
        ]);
    }

    public function schema(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $assetTypeId = $this->resolveAssetTypeId($args, $request);

        if ($assetTypeId === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('asset_type_invalid_id'),
            ]);
        }

        $assetType = $this->assetTypeModel->findById($assetTypeId);

        if ($assetType === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('asset_type_not_found'),
            ]);
        }

        $customFields = $this->assetCustomFieldModel->findByAssetTypeId($assetTypeId);
        $components = $this->assetComponentModel->findByAssetTypeId($assetTypeId);
        $schema = $this->assetTypeTableService->buildSchemaDefinition($assetTypeId, $customFields, $components);
        $tableName = $this->assetTypeTableService->tableNameForTypeId($assetTypeId);

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => [
                'asset_type' => $assetType,
                'table' => $tableName ?? $this->assetTypeTableService->resolveFallbackTableName(),
                'columns' => $schema,
                'custom_fields' => $customFields,
                'components' => $components,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return int|null
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
     * @return list<string>
     */
    private function normalizeOptions(mixed $options): array
    {
        if (is_string($options)) {
            $parts = preg_split('/\s*,\s*/', $options) ?: [];
        } elseif (is_array($options)) {
            $parts = $options;
        } else {
            return [];
        }

        $normalized = [];

        foreach ($parts as $part) {
            $value = trim((string) $part);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized;
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
