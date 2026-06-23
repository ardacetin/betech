<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use App\Services\ListPagination;
use Medoo\Medoo;

class Asset
{
    /** @var list<string> */
    public const FLAT_COLUMNS = [
        'asset_tag',
        'name',
        'model',
        'brand',
        'serial_number',
        'type',
        'status',
        'location',
        'building',
        'assigned_to',
        'mac_address_1',
        'mac_address_2',
    ];

    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly AssetColumnSchemaService $columnSchemaService,
    ) {
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }

    /**
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
     * @param array<string, string> $filters
     * @param list<array<string, mixed>> $filterDefinitions
     *
     * @return list<array<string, mixed>>
     */
    public function findAllForDashboard(array $filters = [], array $filterDefinitions = []): array
    {
        $where = $this->buildDashboardFilterWhere($filters, $filterDefinitions);
        $where['ORDER'] = ['assets.id' => 'DESC'];

        $rows = $this->db()->select('assets', '*', $where);

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
        $where = $this->buildDashboardFilterWhere($filters, $filterDefinitions);
        $page = max(1, $page);
        $perPage = ListPagination::PAGE_SIZE;
        $countWhere = $where === [] ? null : $where;
        $total = (int) $this->db()->count('assets', $countWhere);
        $selectWhere = $where;
        $selectWhere['ORDER'] = ['assets.id' => 'DESC'];
        $selectWhere['LIMIT'] = [ListPagination::offset($page, $perPage), $perPage];

        $rows = $this->db()->select('assets', '*', $selectWhere);

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
    public function getDistinctColumnValues(string $column): array
    {
        if (!$this->columnSchemaService->isQueryableColumn($column)) {
            return [];
        }

        $rows = $this->db()->select('assets', [$column], [
            $column . '[!]' => null,
            'ORDER' => [$column => 'ASC'],
        ]);

        $values = [];
        $seen = [];

        foreach ($rows as $row) {
            $value = trim((string) ($row[$column] ?? ''));

            if ($value === '' || isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;
            $values[] = $value;
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
            if (is_object($value) || is_array($value)) {
                continue;
            }

            $trimmedValue = trim((string) $value);

            if ($trimmedValue === '') {
                continue;
            }

            $name = $this->normalizeDashboardFilterName((string) $name);
            $definition = $definitionMap[$name] ?? null;

            if ($definition === null) {
                continue;
            }

            $match = (string) ($definition['match'] ?? 'partial');
            $column = isset($definition['column']) ? (string) $definition['column'] : '';

            if ($column === '') {
                continue;
            }

            if ($match === 'exact') {
                $conditions[$column] = $trimmedValue;

                continue;
            }

            $conditions[$column . '[~]'] = '%' . $this->escapeLikePattern($trimmedValue) . '%';
        }

        if ($conditions === []) {
            return [];
        }

        return ['AND' => $conditions];
    }

    private function normalizeDashboardFilterName(string $name): string
    {
        return match ($name) {
            'categories', 'category', 'category_name' => 'type',
            'personnel_name', 'user_name' => 'assigned_to',
            'location_name' => 'location',
            default => $name,
        };
    }

    private function escapeLikePattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    public function findById(int $assetId): ?array
    {
        $row = $this->db()->get('assets', '*', ['id' => $assetId]);

        return $row === null ? null : $this->normalizeRow($row);
    }

    public function findByIdForView(int $assetId): ?array
    {
        return $this->findById($assetId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllByPersonnelId(int $userId): array
    {
        $person = $this->db()->get('personnel', ['email', 'name'], ['id' => $userId]);

        if (!is_array($person)) {
            return [];
        }

        return $this->findByAssignedReferences(
            trim((string) ($person['email'] ?? '')),
            trim((string) ($person['name'] ?? ''))
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findForDashboardByPersonnelId(int $userId): array
    {
        $person = $this->db()->get('personnel', ['email', 'name'], ['id' => $userId]);

        if (!is_array($person)) {
            return [];
        }

        $rows = $this->findByAssignedReferences(
            trim((string) ($person['email'] ?? '')),
            trim((string) ($person['name'] ?? ''))
        );

        usort(
            $rows,
            static fn (array $left, array $right): int => ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0))
        );

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findByAssignedReferences(string $email, string $name): array
    {
        $needles = array_values(array_unique(array_filter([$email, $name], static fn (string $value): bool => $value !== '')));

        if ($needles === []) {
            return [];
        }

        $rows = $this->db()->select('assets', '*', [
            'assigned_to' => $needles,
            'ORDER' => ['id' => 'ASC'],
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
    }

    public function isAssignedToPersonnel(int $assetId, int $userId): bool
    {
        $asset = $this->findById($assetId);

        if ($asset === null || trim((string) ($asset['assigned_to'] ?? '')) === '') {
            return false;
        }

        $person = $this->db()->get('personnel', ['email', 'name'], ['id' => $userId]);

        if (!is_array($person)) {
            return false;
        }

        $assignedTo = trim((string) ($asset['assigned_to'] ?? ''));
        $email = trim((string) ($person['email'] ?? ''));
        $name = trim((string) ($person['name'] ?? ''));

        return ($email !== '' && strcasecmp($assignedTo, $email) === 0)
            || ($name !== '' && strcasecmp($assignedTo, $name) === 0);
    }

    public function deletePermanently(int $assetId): bool
    {
        if (!$this->db()->has('assets', ['id' => $assetId])) {
            return false;
        }

        $pdo = $this->db()->pdo;

        if ($pdo->inTransaction()) {
            return $this->executeDeleteCascade($assetId);
        }

        $pdo->beginTransaction();

        try {
            $deleted = $this->executeDeleteCascade($assetId);
            $pdo->commit();

            return $deleted;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function executeDeleteCascade(int $assetId): bool
    {
        if (!$this->db()->has('assets', ['id' => $assetId])) {
            return false;
        }

        $timestamp = date('Y-m-d H:i:s');

        $this->db()->update('ip_addresses', [
            'asset_id' => null,
            'updated_at' => $timestamp,
        ], [
            'asset_id' => $assetId,
        ]);

        if ($this->tableExists('license_assignments')) {
            $this->db()->update('license_assignments', [
                'asset_id' => null,
            ], [
                'asset_id' => $assetId,
            ]);
        }

        if ($this->tableExists('tickets')) {
            $this->db()->update('tickets', [
                'asset_id' => null,
            ], [
                'asset_id' => $assetId,
            ]);
        }

        if ($this->tableExists('maintenance_logs')) {
            $this->db()->delete('maintenance_logs', [
                'asset_id' => $assetId,
            ]);
        }

        $this->db()->delete('asset_histories', [
            'asset_id' => $assetId,
        ]);

        $this->db()->delete('assets', [
            'id' => $assetId,
        ]);

        return true;
    }

    private function tableExists(string $tableName): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/', $tableName)) {
            return false;
        }

        $statement = $this->db()->query(
            "SHOW TABLES LIKE '" . str_replace("'", "''", $tableName) . "'"
        );

        if ($statement === false) {
            return false;
        }

        return $statement->fetch() !== false;
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
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    public function create(array $fields): array
    {
        $insert = $this->filterFlatFields($fields);
        $assetTag = trim((string) ($insert['asset_tag'] ?? ''));

        if ($assetTag === '') {
            $assetTag = $this->generateNextAssetTag();
        }

        $insert['asset_tag'] = $assetTag;
        $insert['name'] = trim((string) ($insert['name'] ?? ''));

        if ($insert['name'] === '') {
            throw new \RuntimeException(__('import_error_name_required'));
        }

        $insert['status'] = trim((string) ($insert['status'] ?? 'ready')) ?: 'ready';

        $this->db()->insert('assets', $insert);

        $insertedId = $this->db()->id();
        $row = $this->db()->get('assets', '*', ['id' => $insertedId]);

        if ($row === null) {
            throw new \RuntimeException('Asset was inserted but could not be retrieved.');
        }

        return $this->normalizeRow($row);
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>|null
     */
    public function update(int $assetId, array $fields): ?array
    {
        $existing = $this->db()->get('assets', '*', ['id' => $assetId]);

        if ($existing === null) {
            return null;
        }

        $updateData = $this->filterFlatFields($fields);

        if ($updateData === []) {
            return $this->normalizeRow($existing);
        }

        if (array_key_exists('asset_tag', $updateData)) {
            $updateData['asset_tag'] = trim((string) $updateData['asset_tag']);
        }

        if (array_key_exists('name', $updateData)) {
            $updateData['name'] = trim((string) $updateData['name']);
        }

        if (array_key_exists('serial_number', $updateData)) {
            $serialNumber = trim((string) ($updateData['serial_number'] ?? ''));
            $updateData['serial_number'] = $serialNumber === '' ? null : $serialNumber;
        }

        if (array_key_exists('status', $updateData)) {
            $status = trim((string) ($updateData['status'] ?? ''));
            $updateData['status'] = $status !== '' ? $status : 'ready';
        }

        foreach (array_merge(
            ['model', 'brand', 'type', 'location', 'building', 'assigned_to', 'mac_address_1', 'mac_address_2'],
            array_diff($this->columnSchemaService->getWritableColumnNames(), self::FLAT_COLUMNS)
        ) as $nullableStringField) {
            if (!array_key_exists($nullableStringField, $updateData)) {
                continue;
            }

            $value = trim((string) ($updateData[$nullableStringField] ?? ''));

            $updateData[$nullableStringField] = $value === '' ? null : $value;
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
     * @param array<string, mixed>|null $existingAsset
     * @param array<string, mixed> $fields
     *
     * @return array{asset: array<string, mixed>, created: bool}
     */
    public function upsertFromImport(?array $existingAsset, array $fields): array
    {
        $fields = $this->filterFlatFields($fields);
        $assetTag = trim((string) ($fields['asset_tag'] ?? ''));

        if ($existingAsset !== null) {
            $assetId = (int) $existingAsset['id'];

            if ($assetTag !== '' && $this->assetTagExists($assetTag, $assetId)) {
                throw new \RuntimeException(sprintf(__('import_error_duplicate_tag'), $assetTag));
            }

            $serialNumber = trim((string) ($fields['serial_number'] ?? ''));

            if ($serialNumber !== '' && $this->serialNumberExists($serialNumber, $assetId)) {
                throw new \RuntimeException(sprintf(__('import_error_duplicate_serial_in_file'), $serialNumber));
            }

            $asset = $this->update($assetId, $fields);

            if ($asset === null) {
                throw new \RuntimeException(__('inventory_import_update_failed'));
            }

            return [
                'asset' => $asset,
                'created' => false,
            ];
        }

        if ($assetTag === '') {
            $fields['asset_tag'] = $this->generateNextAssetTag();
        }

        if ($this->assetTagExists((string) ($fields['asset_tag'] ?? ''))) {
            throw new \RuntimeException(sprintf(__('import_error_duplicate_tag'), (string) $fields['asset_tag']));
        }

        $serialNumber = trim((string) ($fields['serial_number'] ?? ''));

        if ($serialNumber !== '' && $this->serialNumberExists($serialNumber)) {
            throw new \RuntimeException(sprintf(__('import_error_duplicate_serial_in_file'), $serialNumber));
        }

        return [
            'asset' => $this->create($fields),
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

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private function filterFlatFields(array $fields): array
    {
        return $this->columnSchemaService->filterWritableFields($fields);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        if (isset($row['id'])) {
            $row['id'] = (int) $row['id'];
        }

        $row['type'] = trim((string) ($row['type'] ?? ''));
        $row['category_name'] = $row['type'] !== '' ? $row['type'] : null;
        $row['assigned_to'] = trim((string) ($row['assigned_to'] ?? ''));
        $row['user_name'] = $row['assigned_to'] !== '' ? $row['assigned_to'] : null;
        $row['location'] = trim((string) ($row['location'] ?? ''));
        $row['location_name'] = $row['location'] !== '' ? $row['location'] : null;
        $row['building'] = trim((string) ($row['building'] ?? ''));
        $row['location_building'] = $row['building'] !== '' ? $row['building'] : null;
        $row['model'] = trim((string) ($row['model'] ?? ''));
        $row['brand'] = trim((string) ($row['brand'] ?? ''));

        return $row;
    }
}
