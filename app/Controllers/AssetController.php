<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
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
    ];

    public function __construct(
        private readonly Asset $assetModel
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
    private function validateCoreFields(array $coreFields): array
    {
        $errors = [];

        $assetTag = trim((string) ($coreFields['asset_tag'] ?? ''));

        if ($assetTag === '') {
            $errors['asset_tag'][] = 'The asset_tag field is required.';
        } elseif ($this->assetModel->assetTagExists($assetTag)) {
            $errors['asset_tag'][] = 'The asset_tag has already been taken.';
        }

        $name = trim((string) ($coreFields['name'] ?? ''));

        if ($name === '') {
            $errors['name'][] = 'The name field is required.';
        }

        if (!array_key_exists('category_id', $coreFields) || $coreFields['category_id'] === '' || $coreFields['category_id'] === null) {
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
    private function normalizeCoreFields(array $coreFields): array
    {
        $serialNumber = null;

        if (array_key_exists('serial_number', $coreFields) && $coreFields['serial_number'] !== null) {
            $serialNumber = trim((string) $coreFields['serial_number']);
            $serialNumber = $serialNumber === '' ? null : $serialNumber;
        }

        $status = trim((string) ($coreFields['status'] ?? 'ready'));

        return [
            'asset_tag' => trim((string) $coreFields['asset_tag']),
            'serial_number' => $serialNumber,
            'name' => trim((string) $coreFields['name']),
            'category_id' => (int) $coreFields['category_id'],
            'status' => $status !== '' ? $status : 'ready',
        ];
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
