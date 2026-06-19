<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Services\AuditLogger;
use App\Services\Auth\SessionAuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CategoryController
{
    private const ALLOWED_FIELD_TYPES = ['text', 'number', 'textarea'];

    public function __construct(
        private readonly Category $categoryModel,
        private readonly SessionAuthService $sessionAuthService,
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $categories = array_map(
            function (array $category): array {
                $category['asset_count'] = $this->categoryModel->countAssets((int) $category['id']);

                return $category;
            },
            $this->categoryModel->findAll()
        );

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('category_invalid_payload'),
            ]);
        }

        $payloadId = (int) ($payload['id'] ?? 0);

        if ($payloadId > 0) {
            return $this->update($request, $response, ['id' => (string) $payloadId]);
        }

        $errors = $this->validatePayload($payload, true);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('category_validation_failed'),
                'errors' => $errors,
            ]);
        }

        $name = trim((string) $payload['name']);
        $fields = $this->normalizeFields($payload['fields'] ?? []);

        try {
            $category = $this->categoryModel->create($name, $fields);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('category_create_error'),
            ]);
        }

        $category['asset_count'] = 0;

        $this->auditLogger->logFromRequest(
            $request,
            $this->sessionAuthService->userId(),
            AuditLog::ACTION_CREATED,
            AuditLog::ENTITY_CATEGORY,
            (int) ($category['id'] ?? 0),
            null,
            ['name' => (string) ($category['name'] ?? '')]
        );

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('category_create_success'),
            'data' => $category,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $categoryId = (int) ($args['id'] ?? 0);

        if ($categoryId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('category_invalid_id'),
            ]);
        }

        $existing = $this->categoryModel->findById($categoryId);

        if ($existing === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('category_not_found'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('category_invalid_payload'),
            ]);
        }

        $errors = $this->validatePayload($payload, false);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('category_validation_failed'),
                'errors' => $errors,
            ]);
        }

        $name = array_key_exists('name', $payload)
            ? trim((string) $payload['name'])
            : (string) ($existing['name'] ?? '');

        $fields = array_key_exists('fields', $payload)
            ? $this->normalizeFields($payload['fields'])
            : ($existing['fields'] ?? []);

        try {
            $category = $this->categoryModel->update($categoryId, $name, is_array($fields) ? $fields : []);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('category_update_error'),
            ]);
        }

        if ($category === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('category_not_found'),
            ]);
        }

        $category['asset_count'] = $this->categoryModel->countAssets($categoryId);

        $this->auditLogger->logFromRequest(
            $request,
            $this->sessionAuthService->userId(),
            AuditLog::ACTION_UPDATED,
            AuditLog::ENTITY_CATEGORY,
            $categoryId,
            ['name' => (string) ($existing['name'] ?? '')],
            ['name' => (string) ($category['name'] ?? '')]
        );

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('category_update_success'),
            'data' => $category,
        ]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $categoryId = (int) ($args['id'] ?? 0);

        if ($categoryId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('category_invalid_id'),
            ]);
        }

        $existing = $this->categoryModel->findById($categoryId);

        if ($existing === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('category_not_found'),
            ]);
        }

        $assetCount = $this->categoryModel->countAssets($categoryId);

        if ($assetCount > 0) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('category_delete_in_use'),
                'asset_count' => $assetCount,
            ]);
        }

        try {
            $deleted = $this->categoryModel->delete($categoryId);
        } catch (\RuntimeException) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('category_delete_in_use'),
                'asset_count' => $assetCount > 0 ? $assetCount : $this->categoryModel->countAssets($categoryId),
            ]);
        } catch (\Throwable) {
            if ($this->categoryModel->countAssets($categoryId) > 0) {
                return $this->jsonResponse($response, 422, [
                    'status' => 'error',
                    'message' => __('category_delete_in_use'),
                    'asset_count' => $this->categoryModel->countAssets($categoryId),
                ]);
            }

            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('category_delete_error'),
            ]);
        }

        if (!$deleted) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('category_not_found'),
            ]);
        }

        $this->auditLogger->logFromRequest(
            $request,
            $this->sessionAuthService->userId(),
            AuditLog::ACTION_DELETED,
            AuditLog::ENTITY_CATEGORY,
            $categoryId,
            ['name' => (string) ($existing['name'] ?? '')],
            null
        );

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('category_delete_success'),
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
     *
     * @return array<string, list<string>>
     */
    private function validatePayload(array $payload, bool $isCreate): array
    {
        $errors = [];

        if ($isCreate || array_key_exists('name', $payload)) {
            $name = trim((string) ($payload['name'] ?? ''));

            if ($name === '') {
                $errors['name'][] = __('category_name_required');
            }
        }

        if (array_key_exists('fields', $payload) && !is_array($payload['fields'])) {
            $errors['fields'][] = __('category_fields_invalid');
        }

        if (is_array($payload['fields'] ?? null)) {
            $seenNames = [];

            foreach ($payload['fields'] as $index => $field) {
                if (!is_array($field)) {
                    $errors['fields'][] = sprintf(__('category_field_object_required'), $index);
                    continue;
                }

                $name = trim((string) ($field['name'] ?? ''));
                $label = trim((string) ($field['label'] ?? ''));

                if ($name === '' && $label === '') {
                    $errors['fields'][] = sprintf(__('category_field_name_or_label_required'), $index);
                    continue;
                }

                if ($name === '' && $label !== '') {
                    $name = $this->fieldNameFromLabel($label);
                }

                if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
                    $errors['fields'][] = sprintf(__('category_field_name_invalid'), $index);
                }

                if (isset($seenNames[$name])) {
                    $errors['fields'][] = sprintf(__('category_field_name_duplicate'), $name);
                }

                $seenNames[$name] = true;

                if ($label === '') {
                    $errors['fields'][] = sprintf(__('category_field_label_required'), $index);
                }

                $type = trim((string) ($field['type'] ?? 'text'));

                if (!in_array($type, self::ALLOWED_FIELD_TYPES, true)) {
                    $errors['fields'][] = sprintf(__('category_field_type_invalid'), $index);
                }
            }
        }

        return $errors;
    }

    /**
     * @param mixed $fields
     *
     * @return list<array<string, string>>
     */
    private function normalizeFields(mixed $fields): array
    {
        if (!is_array($fields)) {
            return [];
        }

        $normalized = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));
            $label = trim((string) ($field['label'] ?? ''));

            if ($name === '' && $label !== '') {
                $name = $this->fieldNameFromLabel($label);
            }

            if ($name === '' || $label === '') {
                continue;
            }

            $type = trim((string) ($field['type'] ?? 'text'));

            if (!in_array($type, self::ALLOWED_FIELD_TYPES, true)) {
                $type = 'text';
            }

            $entry = [
                'name' => $name,
                'label' => $label,
                'type' => $type,
            ];

            $labelEn = trim((string) ($field['label_en'] ?? ''));

            if ($labelEn !== '') {
                $entry['label_en'] = $labelEn;
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    private function fieldNameFromLabel(string $label): string
    {
        $normalized = mb_strtolower(trim($label), 'UTF-8');
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);

        if ($transliterated === false) {
            $transliterated = $normalized;
        }

        $name = preg_replace('/[^a-z0-9]+/', '_', $transliterated) ?? '';
        $name = trim($name, '_');

        if ($name === '') {
            return 'field';
        }

        if (preg_match('/^[0-9]/', $name)) {
            $name = 'field_' . $name;
        }

        return $name;
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
