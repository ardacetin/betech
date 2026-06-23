<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use App\Services\ListPagination;
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
     * @param array<string, string> $filters
     * @param list<array<string, mixed>> $filterDefinitions
     *
     * @return list<array<string, mixed>>
     */
    public function findAllForDashboard(array $filters = [], array $filterDefinitions = []): array
    {
        $where = $this->buildDashboardFilterWhere($filters, $filterDefinitions);
        $where['ORDER'] = ['assets.id' => 'DESC'];

        $rows = $this->db()->select('assets', [
            '[>]categories' => ['category_id' => 'id'],
            '[>]personnel' => ['personnel_id' => 'id'],
            '[>]locations' => ['location_id' => 'id'],
        ], [
            'assets.id',
            'assets.asset_tag',
            'assets.serial_number',
            'assets.name',
            'assets.category_id',
            'assets.status',
            'assets.personnel_id',
            'assets.location_id',
            'assets.properties',
            'assets.created_at',
            'assets.updated_at',
            'categories.name(category_name)',
            'personnel.name(personnel_name)',
            'locations.name(location_name)',
            'locations.building(location_building)',
        ], $where);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
    }

    /**
     * @param array<string, string> $filters
     * @param list<array<string, mixed>> $filterDefinitions
     *
     * @return array{
     *     data: list<array<string, mixed>>,
     *     pagination: array{page: int, per_page: int, total: int, total_pages: int}
     * }
     */
    public function findPaginatedForDashboard(
        array $filters = [],
        array $filterDefinitions = [],
        int $page = 1,
        int $perPage = ListPagination::PAGE_SIZE
    ): array {
        $join = [
            '[>]categories' => ['category_id' => 'id'],
            '[>]personnel' => ['personnel_id' => 'id'],
            '[>]locations' => ['location_id' => 'id'],
        ];
        $columns = [
            'assets.id',
            'assets.asset_tag',
            'assets.serial_number',
            'assets.name',
            'assets.category_id',
            'assets.status',
            'assets.personnel_id',
            'assets.location_id',
            'assets.properties',
            'assets.created_at',
            'assets.updated_at',
            'categories.name(category_name)',
            'personnel.name(personnel_name)',
            'locations.name(location_name)',
            'locations.building(location_building)',
        ];

        $where = $this->buildDashboardFilterWhere($filters, $filterDefinitions);
        $page = max(1, $page);
        $perPage = ListPagination::PAGE_SIZE;
        $countWhere = $where === [] ? null : $where;
        $total = (int) $this->db()->count('assets', $join, '*', $countWhere);
        $selectWhere = $where;
        $selectWhere['ORDER'] = ['assets.id' => 'DESC'];
        $selectWhere['LIMIT'] = [ListPagination::offset($page, $perPage), $perPage];

        $rows = $this->db()->select('assets', $join, $columns, $selectWhere);

        return [
            'data' => array_map(
                fn (array $row): array => $this->normalizeRow($row),
                $rows
            ),
            'pagination' => ListPagination::meta($page, $total, $perPage),
        ];
    }

    /**
     * @return list<string>
     */
    public function getDistinctPropertyValues(string $propertyKey): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $propertyKey)) {
            return [];
        }

        $jsonPath = '$.' . $propertyKey;
        $statement = $this->db()->query(
            'SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(properties, ?)) AS value
             FROM assets
             WHERE properties IS NOT NULL
               AND JSON_EXTRACT(properties, ?) IS NOT NULL
               AND JSON_UNQUOTE(JSON_EXTRACT(properties, ?)) <> \'\'
             ORDER BY value ASC',
            [$jsonPath, $jsonPath, $jsonPath]
        );

        if ($statement === false) {
            return [];
        }

        $values = [];

        foreach ($statement->fetchAll() as $row) {
            $value = trim((string) ($row['value'] ?? ''));

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param array<string, string> $filters
     * @param list<array<string, mixed>> $filterDefinitions
     *
     * @return array<string, mixed>
     */
    private function buildDashboardFilterWhere(array $filters, array $filterDefinitions): array
    {
        if ($filters === [] || $filterDefinitions === []) {
            return [];
        }

        $definitionMap = [];

        foreach ($filterDefinitions as $definition) {
            $name = (string) ($definition['name'] ?? '');

            if ($name !== '') {
                $definitionMap[$name] = $definition;
            }
        }

        $conditions = [];

        foreach ($filters as $name => $value) {
            $trimmedValue = trim($value);

            if ($trimmedValue === '') {
                continue;
            }

            $name = $this->normalizeDashboardFilterName($name);
            $definition = $definitionMap[$name] ?? null;

            if ($definition === null) {
                continue;
            }

            $match = (string) ($definition['match'] ?? 'partial');
            $column = isset($definition['column']) ? (string) $definition['column'] : '';
            $property = isset($definition['property']) ? (string) $definition['property'] : '';

            if ($column !== '') {
                if ($match === 'exact') {
                    if (in_array($column, ['assets.category_id', 'assets.location_id'], true)) {
                        $conditions[$column] = (int) $trimmedValue;
                    } else {
                        $conditions[$column] = $trimmedValue;
                    }

                    continue;
                }

                $conditions[$column . '[~]'] = '%' . $trimmedValue . '%';
                continue;
            }

            if ($property !== '') {
                $conditions[] = $this->buildPropertyFilterCondition($property, $trimmedValue, $match);
            }
        }

        if ($conditions === []) {
            return [];
        }

        return ['AND' => $conditions];
    }

    private function normalizeDashboardFilterName(string $name): string
    {
        return match ($name) {
            'categories', 'category' => 'category_id',
            default => $name,
        };
    }

    private function buildPropertyFilterCondition(string $propertyKey, string $value, string $match): mixed
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $propertyKey)) {
            return Medoo::raw('1 = 0');
        }

        $jsonPath = '$.' . $propertyKey;
        $extractExpression = "JSON_UNQUOTE(JSON_EXTRACT(<assets.properties>, '" . $jsonPath . "'))";

        if ($match === 'exact') {
            return Medoo::raw($extractExpression . ' = ' . $this->quoteSqlValue($value));
        }

        return Medoo::raw($extractExpression . ' LIKE ' . $this->quoteSqlValue('%' . $value . '%'));
    }

    private function quoteSqlValue(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
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
            '[>]personnel' => ['personnel_id' => 'id'],
            '[>]locations' => ['location_id' => 'id'],
        ], [
            'assets.id',
            'assets.asset_tag',
            'assets.serial_number',
            'assets.name',
            'assets.category_id',
            'assets.status',
            'assets.personnel_id',
            'assets.location_id',
            'assets.properties',
            'assets.created_at',
            'assets.updated_at',
            'categories.name(category_name)',
            'personnel.name(personnel_name)',
            'locations.name(location_name)',
            'locations.building(location_building)',
        ], [
            'assets.id' => $assetId,
        ]);

        return $row === null ? null : $this->normalizeRow($row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllByPersonnelId(int $userId): array
    {
        $rows = $this->db()->select('assets', '*', [
            'personnel_id' => $userId,
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
    public function findForDashboardByPersonnelId(int $userId): array
    {
        $rows = $this->db()->select('assets', [
            '[>]categories' => ['category_id' => 'id'],
            '[>]personnel' => ['personnel_id' => 'id'],
            '[>]locations' => ['location_id' => 'id'],
        ], [
            'assets.id',
            'assets.asset_tag',
            'assets.serial_number',
            'assets.name',
            'assets.category_id',
            'assets.status',
            'assets.personnel_id',
            'assets.location_id',
            'assets.properties',
            'assets.created_at',
            'assets.updated_at',
            'categories.name(category_name)',
            'personnel.name(personnel_name)',
            'locations.name(location_name)',
            'locations.building(location_building)',
        ], [
            'assets.personnel_id' => $userId,
            'ORDER' => ['assets.id' => 'DESC'],
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
    }

    public function isAssignedToPersonnel(int $assetId, int $userId): bool
    {
        return $this->db()->has('assets', [
            'id' => $assetId,
            'personnel_id' => $userId,
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
            'personnel_id' => array_key_exists('personnel_id', $coreFields) ? $coreFields['personnel_id'] : null,
            'location_id' => array_key_exists('location_id', $coreFields) ? $coreFields['location_id'] : null,
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

        if (array_key_exists('personnel_id', $coreFields)) {
            $updateData['personnel_id'] = $coreFields['personnel_id'];
        }

        if (array_key_exists('location_id', $coreFields)) {
            $updateData['location_id'] = $coreFields['location_id'];
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

    /**
     * @return array<string, mixed>|null
     */
    public function findByAssetTag(string $assetTag): ?array
    {
        $trimmed = trim($assetTag);

        if ($trimmed === '') {
            return null;
        }

        $row = $this->db()->get('assets', '*', ['asset_tag' => $trimmed]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    public function serialNumberExists(string $serialNumber, ?int $ignoreAssetId = null): bool
    {
        $trimmed = trim($serialNumber);

        if ($trimmed === '') {
            return false;
        }

        $conditions = ['serial_number' => $trimmed];

        if ($ignoreAssetId !== null) {
            $conditions['id[!]'] = $ignoreAssetId;
        }

        return $this->db()->has('assets', $conditions);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySerialNumber(string $serialNumber): ?array
    {
        $trimmed = trim($serialNumber);

        if ($trimmed === '') {
            return null;
        }

        $row = $this->db()->get('assets', '*', ['serial_number' => $trimmed]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * Create or update an asset matched by serial_number and/or asset_tag (GLPI import upsert).
     *
     * @param array<string, mixed>|null $existingAsset
     * @param array<string, mixed> $coreFields
     * @param array<string, mixed> $properties
     *
     * @return array{asset: array<string, mixed>, created: bool}
     */
    public function upsertFromImport(?array $existingAsset, array $coreFields, array $properties): array
    {
        if ($existingAsset !== null) {
            $assetId = (int) $existingAsset['id'];
            $assetTag = trim((string) ($coreFields['asset_tag'] ?? ''));

            if ($assetTag !== '' && $this->assetTagExists($assetTag, $assetId)) {
                throw new \RuntimeException(sprintf(__('import_error_duplicate_tag'), $assetTag));
            }

            $serialNumber = array_key_exists('serial_number', $coreFields)
                ? trim((string) ($coreFields['serial_number'] ?? ''))
                : '';

            if ($serialNumber !== '' && $this->serialNumberExists($serialNumber, $assetId)) {
                throw new \RuntimeException(sprintf(__('import_error_duplicate_serial_in_file'), $serialNumber));
            }

            if ($assetTag !== '') {
                $coreFields['asset_tag'] = $assetTag;
            } else {
                unset($coreFields['asset_tag']);
            }

            $asset = $this->update($assetId, $coreFields, $properties);

            if ($asset === null) {
                throw new \RuntimeException(__('inventory_import_update_failed'));
            }

            return [
                'asset' => $asset,
                'created' => false,
            ];
        }

        $assetTag = trim((string) ($coreFields['asset_tag'] ?? ''));

        if ($assetTag === '') {
            $coreFields['asset_tag'] = $this->generateNextAssetTag();
        }

        if ($this->assetTagExists((string) $coreFields['asset_tag'])) {
            throw new \RuntimeException(sprintf(__('import_error_duplicate_tag'), (string) $coreFields['asset_tag']));
        }

        $serialNumber = trim((string) ($coreFields['serial_number'] ?? ''));

        if ($serialNumber !== '' && $this->serialNumberExists($serialNumber)) {
            throw new \RuntimeException(sprintf(__('import_error_duplicate_serial_in_file'), $serialNumber));
        }

        return [
            'asset' => $this->create($coreFields, $properties),
            'created' => true,
        ];
    }

    public function generateNextAssetTag(): string
    {
        $rows = $this->db()->select('assets', ['asset_tag'], [
            'ORDER' => ['id' => 'ASC'],
        ]);

        $maxNumber = 0;

        foreach ($rows as $row) {
            $tag = (string) ($row['asset_tag'] ?? '');

            if (preg_match('/^ENV-(\d+)$/', $tag, $matches) !== 1) {
                continue;
            }

            $maxNumber = max($maxNumber, (int) $matches[1]);
        }

        do {
            $maxNumber++;
            $candidate = sprintf('ENV-%04d', $maxNumber);
        } while ($this->assetTagExists($candidate));

        return $candidate;
    }

    public function categoryExists(int $categoryId): bool
    {
        return $this->db()->has('categories', ['id' => $categoryId]);
    }

    public function locationExists(int $locationId): bool
    {
        return $this->db()->has('locations', ['id' => $locationId]);
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

        if (array_key_exists('personnel_name', $row) && $row['personnel_name'] === null) {
            $row['personnel_name'] = null;
        }

        if (array_key_exists('personnel_name', $row)) {
            $row['user_name'] = $row['personnel_name'];
        }

        if (array_key_exists('personnel_id', $row) && $row['personnel_id'] !== null) {
            $row['personnel_id'] = (int) $row['personnel_id'];
            $row['user_id'] = $row['personnel_id'];
        }

        if (array_key_exists('location_id', $row) && $row['location_id'] !== null) {
            $row['location_id'] = (int) $row['location_id'];
        }

        if (array_key_exists('location_name', $row) && $row['location_name'] === null) {
            $row['location_name'] = null;
        }

        if (array_key_exists('location_building', $row) && $row['location_building'] === null) {
            $row['location_building'] = null;
        }

        return $row;
    }
}
