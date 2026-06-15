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
            'properties' => $encodedProperties,
        ]);

        $insertedId = $this->db()->id();
        $row = $this->db()->get('assets', '*', ['id' => $insertedId]);

        if ($row === null) {
            throw new \RuntimeException('Asset was inserted but could not be retrieved.');
        }

        return $this->normalizeRow($row);
    }

    public function assetTagExists(string $assetTag): bool
    {
        return $this->db()->has('assets', ['asset_tag' => $assetTag]);
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

        if (array_key_exists('user_id', $row) && $row['user_id'] !== null) {
            $row['user_id'] = (int) $row['user_id'];
        }

        return $row;
    }
}
