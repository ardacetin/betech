<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use JsonException;
use Medoo\Medoo;

class Asset
{
    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }

    /**
     * Fetch all assets with decoded JSON properties.
     *
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $rows = $this->db()->select('assets', '*', [
            'ORDER' => ['id' => 'DESC'],
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
    }

    /**
     * Fetch assets with category names for dashboard display.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllForDashboard(): array
    {
        $rows = $this->db()->select('assets', [
            '[>]categories' => ['category_id' => 'id'],
            '[>]users' => ['user_id' => 'id'],
        ], [
            'assets.id',
            'assets.asset_tag',
            'assets.serial_number',
            'assets.name',
            'assets.category_id',
            'assets.status',
            'assets.user_id',
            'assets.properties',
            'assets.created_at',
            'assets.updated_at',
            'category_name' => 'categories.name',
            'user_name' => 'users.name',
        ], [
            'ORDER' => ['assets.id' => 'DESC'],
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
    }

    public function findById(int $assetId): ?array
    {
        $row = $this->db()->get('assets', '*', ['id' => $assetId]);

        return $row === null ? null : $this->normalizeRow($row);
    }

    public function findByIdForView(int $assetId): ?array
    {
        $row = $this->db()->get('assets', [
            '[>]categories' => ['category_id' => 'id'],
            '[>]users' => ['user_id' => 'id'],
        ], [
            'assets.id',
            'assets.asset_tag',
            'assets.serial_number',
            'assets.name',
            'assets.category_id',
            'assets.status',
            'assets.user_id',
            'assets.properties',
            'assets.created_at',
            'assets.updated_at',
            'category_name' => 'categories.name',
            'user_name' => 'users.name',
        ], [
            'assets.id' => $assetId,
        ]);

        return $row === null ? null : $this->normalizeRow($row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllByUserId(int $userId): array
    {
        $rows = $this->db()->select('assets', '*', [
            'user_id' => $userId,
            'ORDER' => ['id' => 'ASC'],
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
    }

    /**
     * Fetch assigned assets with category names for end-user dashboard display.
     *
     * @return list<array<string, mixed>>
     */
    public function findForDashboardByUserId(int $userId): array
    {
        $rows = $this->db()->select('assets', [
            '[>]categories' => ['category_id' => 'id'],
            '[>]users' => ['user_id' => 'id'],
        ], [
            'assets.id',
            'assets.asset_tag',
            'assets.serial_number',
            'assets.name',
            'assets.category_id',
            'assets.status',
            'assets.user_id',
            'assets.properties',
            'assets.created_at',
            'assets.updated_at',
            'category_name' => 'categories.name',
            'user_name' => 'users.name',
        ], [
            'assets.user_id' => $userId,
            'ORDER' => ['assets.id' => 'DESC'],
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
    }

    public function isAssignedToUser(int $assetId, int $userId): bool
    {
        return $this->db()->has('assets', [
            'id' => $assetId,
            'user_id' => $userId,
        ]);
    }

    public function deletePermanently(int $assetId): bool
    {
        if (!$this->db()->has('assets', ['id' => $assetId])) {
            return false;
        }

        $this->db()->delete('asset_histories', ['asset_id' => $assetId]);
        $this->db()->delete('assets', ['id' => $assetId]);

        return true;
    }

    /**
     * @return array{total: int, deployed: int, in_storage: int, broken: int}
     */
    public function getMetrics(): array
    {
        return [
            'total' => $this->db()->count('assets'),
            'deployed' => $this->db()->count('assets', ['status' => 'deployed']),
            'in_storage' => $this->db()->count('assets', ['status' => ['storage', 'ready']]),
            'broken' => $this->db()->count('assets', ['status' => 'broken']),
        ];
    }

    /**
     * Create a new asset with core columns and hybrid JSON properties.
     *
     * @param array<string, mixed> $coreFields
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    public function create(array $coreFields, array $properties): array
    {
        $assetTag = trim((string) $coreFields['asset_tag']);
        $name = trim((string) $coreFields['name']);
        $categoryId = (int) $coreFields['category_id'];
        $status = trim((string) ($coreFields['status'] ?? 'ready'));
        $serialNumber = array_key_exists('serial_number', $coreFields) && $coreFields['serial_number'] !== null
            ? trim((string) $coreFields['serial_number'])
            : null;

        if ($serialNumber === '') {
            $serialNumber = null;
        }

        $encodedProperties = $properties === []
            ? null
            : $this->encodeProperties($properties);

        $this->db()->insert('assets', [
            'asset_tag' => $assetTag,
            'serial_number' => $serialNumber,
            'name' => $name,
            'category_id' => $categoryId,
            'status' => $status !== '' ? $status : 'ready',
            'user_id' => array_key_exists('user_id', $coreFields) ? $coreFields['user_id'] : null,
            'properties' => $encodedProperties,
        ]);

        $insertedId = $this->db()->id();
        $row = $this->db()->get('assets', '*', ['id' => $insertedId]);

        if ($row === null) {
            throw new \RuntimeException('Asset was inserted but could not be retrieved.');
        }

        return $this->normalizeRow($row);
    }

    /**
     * Update an existing asset with core columns and optional hybrid JSON properties.
     *
     * @param array<string, mixed> $coreFields
     * @param array<string, mixed>|null $properties Null keeps existing properties unchanged.
     *
     * @return array<string, mixed>|null
     */
    public function update(int $assetId, array $coreFields, ?array $properties = null): ?array
    {
        $existing = $this->db()->get('assets', '*', ['id' => $assetId]);

        if ($existing === null) {
            return null;
        }

        $updateData = [];

        if (array_key_exists('asset_tag', $coreFields)) {
            $updateData['asset_tag'] = trim((string) $coreFields['asset_tag']);
        }

        if (array_key_exists('name', $coreFields)) {
            $updateData['name'] = trim((string) $coreFields['name']);
        }

        if (array_key_exists('serial_number', $coreFields)) {
            $serialNumber = $coreFields['serial_number'] !== null
                ? trim((string) $coreFields['serial_number'])
                : null;
            $updateData['serial_number'] = $serialNumber === '' ? null : $serialNumber;
        }

        if (array_key_exists('category_id', $coreFields)) {
            $updateData['category_id'] = (int) $coreFields['category_id'];
        }

        if (array_key_exists('status', $coreFields)) {
            $status = trim((string) $coreFields['status']);
            $updateData['status'] = $status !== '' ? $status : 'ready';
        }

        if (array_key_exists('user_id', $coreFields)) {
            $updateData['user_id'] = $coreFields['user_id'];
        }

        if ($properties !== null) {
            $updateData['properties'] = $properties === []
                ? null
                : $this->encodeProperties($properties);
        }

        if ($updateData === []) {
            return $this->normalizeRow($existing);
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $this->db()->update('assets', $updateData, ['id' => $assetId]);

        $row = $this->db()->get('assets', '*', ['id' => $assetId]);

        return $row === null ? null : $this->normalizeRow($row);
    }

    public function assetTagExists(string $assetTag, ?int $ignoreAssetId = null): bool
    {
        $conditions = ['asset_tag' => $assetTag];

        if ($ignoreAssetId !== null) {
            $conditions['id[!]'] = $ignoreAssetId;
        }

        return $this->db()->has('assets', $conditions);
    }

    public function categoryExists(int $categoryId): bool
    {
        return $this->db()->has('categories', ['id' => $categoryId]);
    }

    /**
     * Decode the properties JSON column into an associative array.
     *
     * @return array<string, mixed>
     */
    public function decodeProperties(mixed $properties): array
    {
        if ($properties === null || $properties === '') {
            return [];
        }

        if (is_array($properties)) {
            return $properties;
        }

        if (!is_string($properties)) {
            return [];
        }

        try {
            $decoded = json_decode($properties, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Set or update a single key inside the properties JSON column.
     */
    public function setProperty(int $assetId, string $key, mixed $value): bool
    {
        $row = $this->db()->get('assets', ['id', 'properties'], ['id' => $assetId]);

        if ($row === null) {
            return false;
        }

        $properties = $this->decodeProperties($row['properties']);
        $properties[$key] = $value;

        $this->db()->update('assets', [
            'properties' => $this->encodeProperties($properties),
            'updated_at' => date('Y-m-d H:i:s'),
        ], [
            'id' => $assetId,
        ]);

        return true;
    }

    /**
     * Merge multiple keys into the properties JSON column.
     *
     * @param array<string, mixed> $values
     */
    public function mergeProperties(int $assetId, array $values): bool
    {
        $row = $this->db()->get('assets', ['id', 'properties'], ['id' => $assetId]);

        if ($row === null) {
            return false;
        }

        $properties = array_merge(
            $this->decodeProperties($row['properties']),
            $values
        );

        $this->db()->update('assets', [
            'properties' => $this->encodeProperties($properties),
            'updated_at' => date('Y-m-d H:i:s'),
        ], [
            'id' => $assetId,
        ]);

        return true;
    }

    /**
     * Encode properties for persistence in MySQL JSON column.
     *
     * @param array<string, mixed> $properties
     *
     * @throws JsonException
     */
    public function encodeProperties(array $properties): string
    {
        return json_encode($properties, JSON_THROW_ON_ERROR);
    }

    /**
     * Normalize a database row and decode its properties field.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $row['properties'] = $this->decodeProperties($row['properties'] ?? null);

        if (isset($row['id'])) {
            $row['id'] = (int) $row['id'];
        }

        if (isset($row['category_id'])) {
            $row['category_id'] = (int) $row['category_id'];
        }

        if (array_key_exists('category_name', $row) && $row['category_name'] === null) {
            $row['category_name'] = null;
        }

        if (array_key_exists('user_name', $row) && $row['user_name'] === null) {
            $row['user_name'] = null;
        }

        if (array_key_exists('user_id', $row) && $row['user_id'] !== null) {
            $row['user_id'] = (int) $row['user_id'];
        }

        return $row;
    }
}
