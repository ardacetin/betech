<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
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
    ];

    public function __construct(
        private readonly Asset $assetModel,
        private readonly UserIntegrationFactory $userIntegrationFactory
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
        $user = $driver->getUserById((string) $normalizedUserId);

        if ($user === null) {
            return ['The selected user_id does not exist.'];
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
