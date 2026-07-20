<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\AssetRegistry;
use App\Models\AssetsGlobalRegistry;
use App\Models\AssetType;
use App\Services\AssetColumnSchemaService;
use App\Services\AssetMutationLogger;
use App\Services\AssetTypeTableService;
use App\Services\DatabaseService;
use App\Services\ListPagination;
use App\Services\SortQuery;
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
        'warranty_expires_at',
        'total_ports',
    ];

    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly AssetColumnSchemaService $columnSchemaService,
        private readonly AssetTypeTableService $assetTypeTableService,
        private readonly AssetRegistry $assetRegistry,
        private readonly AssetsGlobalRegistry $assetsGlobalRegistry,
        private readonly AssetType $assetTypeModel,
        private readonly AssetMutationLogger $assetMutationLogger,
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
    public function findAllForDashboard(
        array $filters = [],
        array $filterDefinitions = [],
        ?int $assetTypeId = null
    ): array {
        $where = $this->buildDashboardFilterWhere($filters, $filterDefinitions, $assetTypeId);
        $tableName = $this->resolveTableName($assetTypeId);
        $where['ORDER'] = ['id' => 'DESC'];

        $rows = $this->db()->select($tableName, '*', $where);

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
        int $perPage = ListPagination::PAGE_SIZE,
        ?int $assetTypeId = null,
        ?array $order = null,
        ?string $forcedTableName = null
    ): array {
        $where = $this->buildDashboardFilterWhere($filters, $filterDefinitions, $assetTypeId);
        $tableName = $this->resolveInventoryTableName($assetTypeId, $forcedTableName);
        $page = max(1, $page);
        $perPage = ListPagination::PAGE_SIZE;

        if ($tableName === null || !$this->assetTypeTableService->tableExists($tableName)) {
            error_log(sprintf(
                '[Asset] Inventory table unavailable for type_id=%s (resolved table: %s)',
                $assetTypeId ?? 'none',
                $tableName ?? 'null'
            ));

            return [
                'data' => [],
                'pagination' => ListPagination::meta($page, 0, $perPage),
            ];
        }

        error_log(sprintf(
            '[Asset] Inventory query targeting `%s` for type_id=%s',
            $tableName,
            $assetTypeId ?? 'none'
        ));

        $countWhere = $where === [] ? null : $where;
        $total = (int) $this->db()->count($tableName, $countWhere);
        $selectWhere = $where;
        $selectWhere['ORDER'] = $order ?? ['id' => 'DESC'];
        $selectWhere['LIMIT'] = [ListPagination::offset($page, $perPage), $perPage];

        $rows = $this->db()->select($tableName, '*', $selectWhere);

        return [
            'data' => array_map(
                fn (array $row): array => $this->normalizeRow($row),
                $rows
            ),
            'pagination' => ListPagination::meta($page, $total, $perPage),
        ];
    }

    /**
     * @param array<string, mixed> $queryParams
     *
     * @return array<string, 'ASC'|'DESC'>
     */
    public function buildSortOrderFromQuery(array $queryParams, ?int $assetTypeId = null): array
    {
        return SortQuery::parse($queryParams, $this->resolveSortableColumns($assetTypeId), ['id' => 'DESC'])['order'];
    }

    /**
     * @return list<string>
     */
    public function resolveSortableColumns(?int $assetTypeId = null): array
    {
        return array_values(array_unique(array_merge(
            AssetColumnSchemaService::SYSTEM_COLUMNS,
            $this->columnSchemaService->listAssetsTableColumns($assetTypeId)
        )));
    }

    /**
     * @return list<string>
     */
    public function getDistinctColumnValues(string $column, ?int $assetTypeId = null): array
    {
        if (!$this->columnSchemaService->isQueryableColumn($column, $assetTypeId)) {
            return [];
        }

        $tableName = $this->resolveTableName($assetTypeId);
        $conditions = [
            $column . '[!]' => null,
            'ORDER' => [$column => 'ASC'],
        ];

        $rows = $this->db()->select($tableName, [$column], $conditions);

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
    private function buildDashboardFilterWhere(
        array $filters,
        array $filterDefinitions,
        ?int $assetTypeId = null
    ): array {
        $conditions = [];

        if ($filters === [] || $filterDefinitions === []) {
            return $conditions === [] ? [] : ['AND' => $conditions];
        }

        $definitionMap = [];

        foreach ($filterDefinitions as $definition) {
            $name = (string) ($definition['name'] ?? '');

            if ($name !== '') {
                $definitionMap[$name] = $definition;
            }
        }

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
            $column = str_starts_with($column, 'assets.') ? substr($column, 7) : $column;

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
        $typeId = $this->assetRegistry->resolveTypeId($assetId);

        if ($typeId !== null) {
            $tableName = $this->resolveTableName($typeId);
            $row = $this->db()->get($tableName, '*', ['id' => $assetId]);

            if (is_array($row) && $row !== []) {
                return $this->normalizeRow($row, $typeId);
            }
        }

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
    public function findWarrantyExpiringWithinDays(int $days = 60): array
    {
        if ($days < 1) {
            return [];
        }

        $tables = [];

        if ($this->tableExists('assets') && $this->columnExists('assets', 'warranty_expires_at')) {
            $tables[] = 'assets';
        }

        $typeRows = $this->db()->select('asset_types', ['id', 'slug'], [
            'ORDER' => ['id' => 'ASC'],
        ]);

        if (is_array($typeRows)) {
            foreach ($typeRows as $typeRow) {
                if (!is_array($typeRow)) {
                    continue;
                }

                $tableName = $this->assetTypeTableService->tableNameForTypeId((int) ($typeRow['id'] ?? 0));

                if ($tableName === null || in_array($tableName, $tables, true)) {
                    continue;
                }

                if ($this->tableExists($tableName) && $this->columnExists($tableName, 'warranty_expires_at')) {
                    $tables[] = $tableName;
                }
            }
        }

        $results = [];
        $seen = [];

        foreach ($tables as $tableName) {
            $statement = $this->db()->query(
                sprintf(
                    'SELECT id, asset_tag, name, warranty_expires_at
                    FROM `%s`
                    WHERE warranty_expires_at IS NOT NULL
                      AND warranty_expires_at >= CURDATE()
                      AND warranty_expires_at <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
                    ORDER BY warranty_expires_at ASC, asset_tag ASC',
                    $tableName
                ),
                [':days' => $days]
            );

            if ($statement === false) {
                continue;
            }

            foreach ($statement->fetchAll() as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $assetId = (int) ($row['id'] ?? 0);

                if ($assetId <= 0 || isset($seen[$assetId])) {
                    continue;
                }

                $seen[$assetId] = true;
                $results[] = [
                    'id' => $assetId,
                    'asset_tag' => (string) ($row['asset_tag'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'warranty_expires_at' => (string) ($row['warranty_expires_at'] ?? ''),
                ];
            }
        }

        usort(
            $results,
            static fn (array $left, array $right): int => strcmp(
                (string) ($left['warranty_expires_at'] ?? ''),
                (string) ($right['warranty_expires_at'] ?? '')
            )
        );

        return $results;
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

        $email = trim((string) ($person['email'] ?? ''));
        $name = trim((string) ($person['name'] ?? ''));

        if ($this->assetsGlobalRegistry->tableExists()) {
            $registryRows = $this->assetsGlobalRegistry->findByAssignedReferences($email, $name);
            $rows = [];

            foreach ($registryRows as $registryRow) {
                $assetId = (int) ($registryRow['id'] ?? 0);

                if ($assetId <= 0) {
                    continue;
                }

                $asset = $this->findById($assetId);

                if ($asset !== null) {
                    $asset['asset_type_slug'] = (string) ($registryRow['asset_type_slug'] ?? $registryRow['asset_type'] ?? '');
                    $asset['asset_type_name'] = $this->resolveAssetTypeName($asset['asset_type_slug']);
                    $rows[] = $asset;
                }
            }

            return $rows;
        }

        $rows = $this->findByAssignedReferences($email, $name);

        return array_map(function (array $row): array {
            $row['asset_type_slug'] = $this->resolveAssetTypeSlug((int) ($row['asset_type_id'] ?? 0));
            $row['asset_type_name'] = $this->resolveAssetTypeName($row['asset_type_slug']);

            return $row;
        }, $rows);
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
        if ($assetId <= 0) {
            return false;
        }

        $typeId = $this->assetRegistry->resolveTypeId($assetId);
        $existsInLegacy = $this->db()->has('assets', ['id' => $assetId]);
        $existsInTypeTable = false;

        if ($typeId !== null && $typeId > 0) {
            $tableName = $this->resolveTableName($typeId);
            $existsInTypeTable = $this->tableExists($tableName) && $this->db()->has($tableName, ['id' => $assetId]);
        }

        if (!$existsInLegacy && !$existsInTypeTable) {
            return false;
        }

        $beforeRow = $this->findById($assetId);
        $resolvedTypeId = $typeId ?? (int) ($beforeRow['asset_type_id'] ?? 0);
        $pdo = $this->db()->pdo;

        if ($pdo->inTransaction()) {
            $deleted = $this->executeDeleteCascade($this->db(), $assetId);

            if ($deleted && $beforeRow !== null && $resolvedTypeId > 0) {
                $this->assetMutationLogger->logDeleted($resolvedTypeId, $assetId, $beforeRow);
            }

            return $deleted;
        }

        $deleted = false;

        $this->db()->action(function (Medoo $db) use ($assetId, &$deleted): void {
            $deleted = $this->executeDeleteCascade($db, $assetId);
        });

        if ($deleted && $beforeRow !== null && $resolvedTypeId > 0) {
            $this->assetMutationLogger->logDeleted($resolvedTypeId, $assetId, $beforeRow);
        }

        return $deleted;
    }

    private function executeDeleteCascade(Medoo $db, int $assetId): bool
    {
        $typeId = $this->assetRegistry->resolveTypeId($assetId);
        $existsInLegacy = $db->has('assets', ['id' => $assetId]);
        $tableName = $typeId !== null && $typeId > 0 ? $this->resolveTableName($typeId) : 'assets';
        $existsInTypeTable = $typeId !== null
            && $typeId > 0
            && $this->tableExists($tableName)
            && $db->has($tableName, ['id' => $assetId]);

        if (!$existsInLegacy && !$existsInTypeTable) {
            return false;
        }

        $timestamp = date('Y-m-d H:i:s');

        $db->update('ip_addresses', [
            'asset_id' => null,
            'updated_at' => $timestamp,
        ], [
            'asset_id' => $assetId,
        ]);

        if ($this->tableExists('license_assignments')) {
            $db->update('license_assignments', [
                'asset_id' => null,
            ], [
                'asset_id' => $assetId,
            ]);
        }

        if ($this->tableExists('tickets')) {
            $db->update('tickets', [
                'asset_id' => null,
                'asset_type' => null,
            ], [
                'asset_id' => $assetId,
            ]);
        }

        if ($this->tableExists('maintenance_logs')) {
            $db->delete('maintenance_logs', [
                'asset_id' => $assetId,
            ]);
        }

        $db->delete('asset_histories', [
            'asset_id' => $assetId,
        ]);

        $typeId = $this->assetRegistry->resolveTypeId($assetId);

        if ($typeId !== null && $typeId > 0) {
            $tableName = $this->resolveTableName($typeId);

            if ($this->tableExists($tableName)) {
                $db->delete($tableName, ['id' => $assetId]);
            }
        }

        $this->assetRegistry->unregister($assetId);
        $this->assetsGlobalRegistry->unregister($assetId);

        if ($existsInLegacy) {
            $db->delete('assets', [
                'id' => $assetId,
            ]);
        }

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
    public function create(array $fields, ?int $assetTypeId = null): array
    {
        $typeId = $assetTypeId !== null && $assetTypeId > 0 ? $assetTypeId : 1;
        $tableName = $this->resolveTableName($typeId);
        $insert = $this->filterFlatFields($fields, $typeId);
        $assetTag = trim((string) ($insert['asset_tag'] ?? ''));

        if ($assetTag === '') {
            $assetTag = $this->generateNextAssetTag($typeId);
        }

        $insert['asset_tag'] = $assetTag;
        $insert['name'] = trim((string) ($insert['name'] ?? ''));

        if ($insert['name'] === '') {
            throw new \RuntimeException(__('import_error_name_required'));
        }

        $insert['status'] = trim((string) ($insert['status'] ?? 'ready')) ?: 'ready';

        $typeRow = $this->assetTypeModel->findById($typeId);
        $typeSlug = is_array($typeRow) ? (string) ($typeRow['slug'] ?? '') : '';

        if ($typeSlug !== '' && $this->assetTypeTableService->isSwitchTypeSlug($typeSlug)) {
            $this->assetTypeTableService->ensureTotalPortsColumn($tableName);
        }

        if ($this->columnExists($tableName, 'total_ports')) {
            $ports = (int) ($insert['total_ports'] ?? 48);
            $insert['total_ports'] = $ports > 0 ? min(512, $ports) : 48;
        } else {
            unset($insert['total_ports']);
        }

        $this->db()->insert($tableName, $insert);

        $insertedId = (int) $this->db()->id();
        $this->assetRegistry->register($insertedId, $typeId);
        $row = $this->db()->get($tableName, '*', ['id' => $insertedId]);

        if ($row === null) {
            throw new \RuntimeException('Asset was inserted but could not be retrieved.');
        }

        $this->syncLegacyAssetRow($typeId, $row);
        $this->syncGlobalRegistry($typeId, $row);
        $this->assetMutationLogger->logCreated($typeId, $insertedId, $row);

        return $this->normalizeRow($row, $typeId);
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>|null
     */
    public function update(int $assetId, array $fields): ?array
    {
        $typeId = $this->assetRegistry->resolveTypeId($assetId);
        $tableName = $typeId !== null ? $this->resolveTableName($typeId) : 'assets';
        $existing = $this->db()->get($tableName, '*', ['id' => $assetId]);

        if ($existing === null) {
            $existing = $this->db()->get('assets', '*', ['id' => $assetId]);

            if ($existing === null) {
                return null;
            }

            $tableName = 'assets';
            $typeId = (int) ($existing['asset_type_id'] ?? 0);
        }

        $updateData = $this->filterFlatFields($fields, $typeId > 0 ? $typeId : null);

        if ($updateData === []) {
            return $this->normalizeRow($existing, $typeId > 0 ? $typeId : null);
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

        if (array_key_exists('total_ports', $updateData)) {
            $ports = (int) $updateData['total_ports'];
            $updateData['total_ports'] = $ports > 0 ? min(512, $ports) : null;
        }

        foreach (array_merge(
            ['model', 'brand', 'type', 'location', 'building', 'assigned_to', 'mac_address_1', 'mac_address_2', 'warranty_expires_at'],
            array_diff($this->columnSchemaService->getWritableColumnNames($typeId > 0 ? $typeId : null), self::FLAT_COLUMNS)
        ) as $nullableStringField) {
            if (!array_key_exists($nullableStringField, $updateData) || $nullableStringField === 'total_ports') {
                continue;
            }

            $value = trim((string) ($updateData[$nullableStringField] ?? ''));

            $updateData[$nullableStringField] = $value === '' ? null : $value;
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $this->db()->update($tableName, $updateData, ['id' => $assetId]);

        $row = $this->db()->get($tableName, '*', ['id' => $assetId]);

        if ($row === null) {
            return null;
        }

        if ($typeId !== null && $typeId > 0) {
            $this->syncLegacyAssetRow($typeId, $row);
        }

        $this->syncGlobalRegistry($typeId > 0 ? $typeId : (int) ($row['asset_type_id'] ?? 0), $row);

        $effectiveTypeId = ($typeId !== null && $typeId > 0)
            ? $typeId
            : (int) ($existing['asset_type_id'] ?? $row['asset_type_id'] ?? 0);

        if ($effectiveTypeId > 0) {
            $this->assetMutationLogger->logUpdated($effectiveTypeId, $assetId, $existing, $row);
        }

        return $this->normalizeRow($row, $typeId > 0 ? $typeId : null);
    }

    public function assetTagExists(string $assetTag, ?int $ignoreAssetId = null, ?int $assetTypeId = null): bool
    {
        $tableName = $assetTypeId !== null && $assetTypeId > 0
            ? $this->resolveTableName($assetTypeId)
            : 'assets';
        $conditions = ['asset_tag' => $assetTag];

        if ($ignoreAssetId !== null) {
            $conditions['id[!]'] = $ignoreAssetId;
        }

        return $this->db()->has($tableName, $conditions);
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

    /**
     * @return array<string, mixed>|null
     */
    public function findByAssetTagInSection(string $assetTag, int $assetTypeId): ?array
    {
        $trimmed = trim($assetTag);

        if ($trimmed === '' || $assetTypeId <= 0) {
            return null;
        }

        $tableName = $this->resolveTableName($assetTypeId);
        $row = $this->db()->get($tableName, '*', [
            'asset_tag' => $trimmed,
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row, $assetTypeId);
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
        $assetTypeId = isset($fields['asset_type_id']) ? (int) $fields['asset_type_id'] : 0;

        if ($assetTypeId <= 0 && isset($existingAsset['asset_type_id'])) {
            $assetTypeId = (int) $existingAsset['asset_type_id'];
        }

        $fields = $this->filterFlatFields($fields, $assetTypeId > 0 ? $assetTypeId : null);

        $assetTag = trim((string) ($fields['asset_tag'] ?? ''));

        if ($existingAsset !== null) {
            $assetId = (int) $existingAsset['id'];
            $existingTypeId = (int) ($existingAsset['asset_type_id'] ?? $assetTypeId);

            if ($assetTag !== '' && $this->assetTagExists($assetTag, $assetId, $existingTypeId > 0 ? $existingTypeId : null)) {
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

        if ($this->assetTagExists((string) ($fields['asset_tag'] ?? ''), null, $assetTypeId > 0 ? $assetTypeId : null)) {
            throw new \RuntimeException(sprintf(__('import_error_duplicate_tag'), (string) $fields['asset_tag']));
        }

        $serialNumber = trim((string) ($fields['serial_number'] ?? ''));

        if ($serialNumber !== '' && $this->serialNumberExists($serialNumber)) {
            throw new \RuntimeException(sprintf(__('import_error_duplicate_serial_in_file'), $serialNumber));
        }

        return [
            'asset' => $this->create($fields, $assetTypeId > 0 ? $assetTypeId : null),
            'created' => true,
        ];
    }

    public function generateNextAssetTag(?int $assetTypeId = null): string
    {
        $tableName = $assetTypeId !== null && $assetTypeId > 0
            ? $this->resolveTableName($assetTypeId)
            : 'assets';
        $rows = $this->db()->select($tableName, ['asset_tag'], [
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
        } while ($this->assetTagExists($candidate, null, $assetTypeId));

        return $candidate;
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private function filterFlatFields(array $fields, ?int $assetTypeId = null): array
    {
        return $this->columnSchemaService->filterWritableFields($fields, $assetTypeId);
    }

    private function resolveTableName(?int $assetTypeId): string
    {
        return $this->columnSchemaService->resolveTableName($assetTypeId);
    }

    private function resolveInventoryTableName(?int $assetTypeId, ?string $forcedTableName = null): ?string
    {
        if ($forcedTableName !== null && trim($forcedTableName) !== '') {
            return trim($forcedTableName);
        }

        if ($assetTypeId === null || $assetTypeId <= 0) {
            return null;
        }

        return $this->assetTypeTableService->tableNameForTypeIdStrict($assetTypeId);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function syncLegacyAssetRow(int $assetTypeId, array $row): void
    {
        if (!$this->tableExists('assets') || !$this->columnExists('assets', 'asset_type_id')) {
            return;
        }

        $assetId = (int) ($row['id'] ?? 0);

        if ($assetId <= 0) {
            return;
        }

        $legacy = [
            'asset_type_id' => $assetTypeId,
            'asset_tag' => (string) ($row['asset_tag'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'status' => (string) ($row['status'] ?? 'ready'),
            'serial_number' => $row['serial_number'] ?? null,
            'assigned_to' => $row['assigned_to'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($this->db()->has('assets', ['id' => $assetId])) {
            $this->db()->update('assets', $legacy, ['id' => $assetId]);

            return;
        }

        $legacy['id'] = $assetId;
        $legacy['created_at'] = $row['created_at'] ?? date('Y-m-d H:i:s');
        $this->db()->insert('assets', $legacy);
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $statement = $this->db()->query(
            'SELECT COUNT(*) AS total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ' . $this->db()->quote($tableName) . '
              AND COLUMN_NAME = ' . $this->db()->quote($columnName)
        );

        if ($statement === false) {
            return false;
        }

        $row = $statement->fetch();

        return (int) ($row['total'] ?? 0) > 0;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row, ?int $assetTypeId = null): array
    {
        if (isset($row['id'])) {
            $row['id'] = (int) $row['id'];
        }

        if ($assetTypeId !== null && $assetTypeId > 0) {
            $row['asset_type_id'] = $assetTypeId;
        } elseif (isset($row['asset_type_id'])) {
            $row['asset_type_id'] = (int) $row['asset_type_id'];
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

    /**
     * @param array<string, mixed> $row
     */
    private function syncGlobalRegistry(int $assetTypeId, array $row): void
    {
        $assetId = (int) ($row['id'] ?? 0);

        if ($assetId <= 0) {
            return;
        }

        $slug = $this->resolveAssetTypeSlug($assetTypeId);

        if ($slug === '') {
            return;
        }

        $this->assetsGlobalRegistry->sync($assetId, $slug, $row);
    }

    private function resolveAssetTypeSlug(int $assetTypeId): string
    {
        if ($assetTypeId <= 0) {
            return '';
        }

        $assetType = $this->assetTypeModel->findById($assetTypeId);

        return trim((string) ($assetType['slug'] ?? ''));
    }

    private function resolveAssetTypeName(string $slug): string
    {
        if ($slug === '') {
            return '';
        }

        $assetType = $this->assetTypeModel->findBySlug($slug);

        return trim((string) ($assetType['name'] ?? $slug));
    }
}
