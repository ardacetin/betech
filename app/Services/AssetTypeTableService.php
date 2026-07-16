<?php

declare(strict_types=1);

namespace App\Services;

use Medoo\Medoo;
use PDOException;
use RuntimeException;

class AssetTypeTableService
{
    public const DEFAULT_EXTENDED_TYPE_SLUG = 'bilgisayarlar';

    /** @var list<string> */
    public const FOUNDATION_COLUMNS = [
        'asset_tag',
        'name',
        'serial_number',
        'status',
        'assigned_to',
        'warranty_expires_at',
    ];

    /** @var list<string> */
    public const EXTENDED_COLUMNS = [
        'model',
        'brand',
        'type',
        'location',
        'building',
        'mac_address_1',
        'mac_address_2',
    ];

    /** @var list<string> */
    public const BASE_COLUMNS = [
        ...self::FOUNDATION_COLUMNS,
        ...self::EXTENDED_COLUMNS,
    ];

    /** @var array<string, list<string>> */
    private array $tableColumnsCache = [];

    /** @var list<array{name: string, column: string}> */
    private const PERFORMANCE_INDEXES = [
        ['name' => 'idx_status', 'column' => 'status'],
        ['name' => 'idx_assigned', 'column' => 'assigned_to'],
        ['name' => 'idx_serial', 'column' => 'serial_number'],
        ['name' => 'idx_created_at', 'column' => 'created_at'],
        ['name' => 'idx_brand', 'column' => 'brand'],
        ['name' => 'idx_model', 'column' => 'model'],
    ];

    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly DdlIdentifierGuard $ddlIdentifierGuard,
        private readonly ?FileStorageCache $schemaMetadataCache = null,
    ) {
    }

    public function tableNameForSlug(string $slug): string
    {
        $normalized = $this->normalizeSlug($slug);

        if ($normalized === '') {
            throw new RuntimeException('Invalid asset type slug.');
        }

        return 'assets_' . $normalized;
    }

    public function tableNameForTypeId(int $assetTypeId): ?string
    {
        if ($assetTypeId <= 0) {
            return null;
        }

        $slug = $this->slugForTypeId($assetTypeId);

        if ($slug === null) {
            error_log(sprintf(
                '[AssetTypeTableService] Asset type id %d not found in asset_types whitelist.',
                $assetTypeId
            ));

            return null;
        }

        return $this->tableNameForSlug($slug);
    }

    /**
     * Resolve a whitelisted asset type from a route/query slug or numeric id.
     *
     * @return array{id: int, slug: string, table: string}|null
     */
    public function resolveWhitelistedType(string $identifier): ?array
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            $identifier = self::DEFAULT_EXTENDED_TYPE_SLUG;
        }

        $typeId = $this->resolveTypeIdFromIdentifier($identifier);

        if ($typeId === null) {
            return null;
        }

        $slug = $this->slugForTypeId($typeId);

        if ($slug === null) {
            return null;
        }

        return [
            'id' => $typeId,
            'slug' => $slug,
            'table' => $this->tableNameForSlug($slug),
        ];
    }

    public function resolveFallbackTableName(): string
    {
        $defaultSlug = $this->extractSlugFromRow($this->db()->get('asset_types', 'slug', [
            'slug' => self::DEFAULT_EXTENDED_TYPE_SLUG,
            'ORDER' => ['id' => 'ASC'],
        ]));

        if ($defaultSlug !== null) {
            $defaultTable = $this->tableNameForSlug($defaultSlug);

            if ($this->tableExists($defaultTable)) {
                return $defaultTable;
            }
        }

        $firstSlug = $this->extractSlugFromRow($this->db()->get('asset_types', 'slug', [
            'ORDER' => ['sort_order' => 'ASC', 'id' => 'ASC'],
        ]));

        if ($firstSlug !== null) {
            $firstTable = $this->tableNameForSlug($firstSlug);

            if ($this->tableExists($firstTable)) {
                return $firstTable;
            }
        }

        $bilgisayarlarTable = $this->tableNameForSlug(self::DEFAULT_EXTENDED_TYPE_SLUG);

        if ($this->tableExists($bilgisayarlarTable)) {
            return $bilgisayarlarTable;
        }

        if ($this->tableExists('assets')) {
            return 'assets';
        }

        return $bilgisayarlarTable;
    }

    public function tableExists(string $tableName): bool
    {
        if (!$this->isValidTableName($tableName)) {
            return false;
        }

        $statement = $this->db()->query(
            'SELECT COUNT(*) AS total
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ' . $this->db()->quote($tableName)
        );

        if ($statement === false) {
            return false;
        }

        $row = $statement->fetch();

        return (int) ($row['total'] ?? 0) > 0;
    }

    public function createTableForSlug(string $slug): string
    {
        $tableName = $this->tableNameForSlug($slug);

        if ($this->tableExists($tableName)) {
            $this->ensurePerformanceIndexesForTable($tableName);

            return $tableName;
        }

        $normalizedSlug = $this->normalizeSlug($slug);
        $extended = $this->usesExtendedSchema($slug);
        $columnDefinitions = [
            'id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
            'asset_tag VARCHAR(64) NOT NULL',
            'name VARCHAR(255) NOT NULL',
            'serial_number VARCHAR(128) DEFAULT NULL',
            'status VARCHAR(32) NOT NULL DEFAULT \'ready\'',
            'assigned_to VARCHAR(255) DEFAULT NULL',
            'warranty_expires_at DATE DEFAULT NULL',
        ];

        if ($extended) {
            $columnDefinitions = array_merge($columnDefinitions, [
                'model VARCHAR(255) DEFAULT NULL',
                'brand VARCHAR(255) DEFAULT NULL',
                'type VARCHAR(255) DEFAULT NULL',
                'location VARCHAR(255) DEFAULT NULL',
                'building VARCHAR(255) DEFAULT NULL',
                'mac_address_1 VARCHAR(255) DEFAULT NULL',
                'mac_address_2 VARCHAR(255) DEFAULT NULL',
            ]);
        }

        $columnDefinitions[] = 'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $columnDefinitions[] = 'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
        $columnDefinitions[] = 'PRIMARY KEY (id)';
        $columnDefinitions[] = sprintf('UNIQUE KEY uq_%s_asset_tag (asset_tag)', $normalizedSlug);

        foreach (self::PERFORMANCE_INDEXES as $indexDefinition) {
            $column = $indexDefinition['column'];

            if ($column === 'brand' || $column === 'model') {
                if (!$extended) {
                    continue;
                }
            }

            $columnDefinitions[] = sprintf(
                'KEY %s (%s)',
                $indexDefinition['name'],
                $column
            );
        }

        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (%s) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            $tableName,
            implode(",\n                ", $columnDefinitions)
        );

        $this->db()->query($sql);
        $this->invalidateSchemaCache($tableName);

        return $tableName;
    }

    /**
     * Ensure performance indexes exist on every registered polymorphic asset table.
     *
     * @return list<string>
     */
    public function ensureAllPolymorphicPerformanceIndexes(): array
    {
        $messages = [];

        foreach ($this->listRegisteredTypeSlugs() as $slug) {
            $tableName = $this->tableNameForSlug($slug);

            if (!$this->tableExists($tableName)) {
                continue;
            }

            foreach ($this->ensurePerformanceIndexesForTable($tableName) as $message) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * @return list<string>
     */
    public function ensurePerformanceIndexesForTable(string $tableName): array
    {
        if (!$this->isValidTableName($tableName) || !$this->tableExists($tableName)) {
            return [];
        }

        $messages = [];

        foreach (self::PERFORMANCE_INDEXES as $indexDefinition) {
            $indexName = $indexDefinition['name'];
            $columnName = $indexDefinition['column'];

            if (!$this->columnExists($tableName, $columnName)) {
                continue;
            }

            if ($this->indexExistsOnTable($tableName, $indexName)) {
                continue;
            }

            $this->db()->query(sprintf(
                'ALTER TABLE `%s` ADD INDEX `%s` (`%s`)',
                $tableName,
                $indexName,
                $columnName
            ));

            $messages[] = sprintf(
                'Added performance index `%s` on `%s` (%s).',
                $indexName,
                $tableName,
                $columnName
            );
        }

        if ($messages !== []) {
            $this->invalidateSchemaCache($tableName);
        }

        return $messages;
    }

    /**
     * @return list<string>
     */
    private function listRegisteredTypeSlugs(): array
    {
        $rows = $this->db()->select('asset_types', 'slug', [
            'ORDER' => ['sort_order' => 'ASC', 'id' => 'ASC'],
        ]);

        if (!is_array($rows)) {
            return [];
        }

        $slugs = [];

        foreach ($rows as $row) {
            $slug = $this->extractSlugFromRow($row);

            if ($slug !== null) {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }

    private function indexExistsOnTable(string $tableName, string $indexName): bool
    {
        if (!$this->isValidTableName($tableName) || !$this->isValidIndexName($indexName)) {
            return false;
        }

        $statement = $this->db()->query(
            sprintf("SHOW INDEX FROM `%s` WHERE Key_name = %s", $tableName, $this->db()->quote($indexName))
        );

        return $statement !== false && $statement->rowCount() > 0;
    }

    private function isValidIndexName(string $indexName): bool
    {
        return preg_match('/^[a-z][a-z0-9_]*$/', $indexName) === 1;
    }

    private function invalidateSchemaCache(string $tableName): void
    {
        unset($this->tableColumnsCache[$tableName]);

        if ($this->schemaMetadataCache !== null) {
            $this->schemaMetadataCache->delete('schema_columns:' . $tableName);
        }
    }

    public function dropTableForSlug(string $slug): void
    {
        $tableName = $this->tableNameForSlug($slug);

        if (!$this->tableExists($tableName)) {
            return;
        }

        $this->db()->query(sprintf('DROP TABLE `%s`', $tableName));
        $this->invalidateSchemaCache($tableName);
    }

    /**
     * @return list<string>
     */
    public function listTableColumns(string $tableName): array
    {
        if (!$this->isValidTableName($tableName)) {
            throw new RuntimeException(sprintf('Invalid table name: %s', $tableName));
        }

        if (isset($this->tableColumnsCache[$tableName])) {
            return $this->tableColumnsCache[$tableName];
        }

        $cacheKey = 'schema_columns:' . $tableName;

        if ($this->schemaMetadataCache !== null) {
            $cached = $this->schemaMetadataCache->get($cacheKey);

            if (is_array($cached)) {
                /** @var list<string> $cached */
                $this->tableColumnsCache[$tableName] = $cached;

                return $cached;
            }
        }

        if (!$this->tableExists($tableName)) {
            return [];
        }

        $statement = $this->db()->query(sprintf('SHOW COLUMNS FROM `%s`', $tableName));

        if ($statement === false) {
            throw new RuntimeException(sprintf('Unable to read schema for table `%s`.', $tableName));
        }

        $columns = [];

        foreach ($statement->fetchAll() as $row) {
            $name = trim((string) ($row['Field'] ?? ''));

            if ($name !== '') {
                $columns[] = $name;
            }
        }

        $this->tableColumnsCache[$tableName] = $columns;

        if ($this->schemaMetadataCache !== null) {
            $this->schemaMetadataCache->set($cacheKey, $columns, FileStorageCache::DEFAULT_TTL_SECONDS);
        }

        return $columns;
    }

    public function columnExists(string $tableName, string $columnName): bool
    {
        return in_array($columnName, $this->listTableColumns($tableName), true);
    }

    public function addColumn(string $tableName, string $columnName, string $fieldType = 'varchar'): bool
    {
        $this->ddlIdentifierGuard->assertSafeIdentifier($columnName, 'column');

        if (!$this->isValidColumnIdentifier($columnName)) {
            throw new RuntimeException(sprintf('Invalid column name: %s', $columnName));
        }

        if ($this->columnExists($tableName, $columnName)) {
            return true;
        }

        $sqlType = $this->mapFieldTypeToSql($fieldType);
        $sql = sprintf(
            'ALTER TABLE `%s` ADD COLUMN `%s` %s DEFAULT NULL',
            $tableName,
            $columnName,
            $sqlType
        );

        try {
            $this->db()->query($sql);
        } catch (PDOException $exception) {
            if ($this->isDuplicateColumnError($exception)) {
                $this->invalidateSchemaCache($tableName);

                return true;
            }

            throw new RuntimeException(
                sprintf('Failed to add column `%s` on `%s`: %s', $columnName, $tableName, $exception->getMessage()),
                0,
                $exception
            );
        }

        $this->invalidateSchemaCache($tableName);

        return true;
    }

    public function dropColumn(string $tableName, string $columnName): void
    {
        $this->ddlIdentifierGuard->assertSafeIdentifier($columnName, 'column');

        if (!$this->isValidColumnIdentifier($columnName)) {
            throw new RuntimeException(sprintf('Invalid column name: %s', $columnName));
        }

        if (in_array($columnName, ['id', 'created_at', 'updated_at'], true)
            || in_array($columnName, self::BASE_COLUMNS, true)) {
            throw new RuntimeException(sprintf('Cannot drop base column `%s`.', $columnName));
        }

        if (!$this->columnExists($tableName, $columnName)) {
            return;
        }

        try {
            $this->db()->query(sprintf(
                'ALTER TABLE `%s` DROP COLUMN `%s`',
                $tableName,
                $columnName
            ));
        } catch (PDOException $exception) {
            if (!$this->isMissingColumnError($exception)) {
                throw new RuntimeException(
                    sprintf('Failed to drop column `%s` on `%s`: %s', $columnName, $tableName, $exception->getMessage()),
                    0,
                    $exception
                );
            }
        }

        unset($this->tableColumnsCache[$tableName]);
    }

    public function usesExtendedSchema(string $slug): bool
    {
        return $this->normalizeSlug($slug) === self::DEFAULT_EXTENDED_TYPE_SLUG;
    }

    public function countRows(string $tableName): int
    {
        if (!$this->tableExists($tableName)) {
            return 0;
        }

        return (int) $this->db()->count($tableName);
    }

    /**
     * @return list<array{column: string, label: string, type: string, is_custom: bool}>
     */
    public function buildSchemaDefinition(int $assetTypeId, array $customFields = [], array $components = []): array
    {
        if ($assetTypeId <= 0) {
            return [];
        }

        $tableName = $this->tableNameForTypeId($assetTypeId);

        if ($tableName === null || trim($tableName) === '' || !$this->tableExists($tableName)) {
            error_log(sprintf(
                '[AssetTypeTableService] Skipping schema build for asset type id %d; table `%s` is unavailable.',
                $assetTypeId,
                (string) $tableName
            ));

            return [];
        }

        $columns = $this->listTableColumns($tableName);
        $schema = [];
        $seen = [];

        foreach (AssetColumnSchemaService::NATIVE_COLUMN_LABELS as $column => $label) {
            if (!in_array($column, $columns, true)) {
                continue;
            }

            $schema[] = [
                'column' => $column,
                'label' => $label,
                'type' => 'varchar',
                'is_custom' => false,
                'is_component' => false,
                'input' => $column === 'status' ? 'select' : 'text',
            ];
            $seen[$column] = true;
        }

        foreach ($customFields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $columnName = trim((string) ($field['column_name'] ?? ''));

            if ($columnName === '' || !in_array($columnName, $columns, true) || isset($seen[$columnName])) {
                continue;
            }

            $schema[] = [
                'column' => $columnName,
                'label' => (string) ($field['label'] ?? $columnName),
                'type' => (string) ($field['field_type'] ?? 'varchar'),
                'is_custom' => true,
                'is_component' => false,
                'input' => $this->mapFieldTypeToInput((string) ($field['field_type'] ?? 'varchar')),
                'options' => is_array($field['options'] ?? null) ? $field['options'] : [],
            ];
            $seen[$columnName] = true;
        }

        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }

            $columnName = trim((string) ($component['column_name'] ?? ''));

            if ($columnName === '' || !in_array($columnName, $columns, true) || isset($seen[$columnName])) {
                continue;
            }

            $schema[] = [
                'column' => $columnName,
                'label' => (string) ($component['name'] ?? $columnName),
                'type' => 'varchar',
                'is_custom' => false,
                'is_component' => true,
                'input' => 'text',
                'options' => [],
            ];
            $seen[$columnName] = true;
        }

        foreach ($columns as $column) {
            if (isset($seen[$column]) || in_array($column, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }

            $schema[] = [
                'column' => $column,
                'label' => $column,
                'type' => 'varchar',
                'is_custom' => true,
                'is_component' => false,
                'input' => 'text',
                'options' => [],
            ];
        }

        return $schema;
    }

    public function resolveTypeIdFromIdentifier(string $identifier): ?int
    {
        $trimmed = trim($identifier);

        if ($trimmed === '') {
            return null;
        }

        if (ctype_digit($trimmed)) {
            $byId = (int) $trimmed;

            if ($this->db()->has('asset_types', ['id' => $byId])) {
                return $byId;
            }

            return null;
        }

        $row = $this->db()->get('asset_types', 'id', [
            'slug' => $trimmed,
        ]);

        return $this->extractIdFromRow($row);
    }

    /**
     * Medoo returns a scalar when selecting a single column via get().
     */
    private function extractIdFromRow(mixed $row): ?int
    {
        if (is_int($row)) {
            return $row > 0 ? $row : null;
        }

        if (is_string($row) && ctype_digit(trim($row))) {
            $id = (int) trim($row);

            return $id > 0 ? $id : null;
        }

        if (is_array($row) && isset($row['id'])) {
            $id = (int) $row['id'];

            return $id > 0 ? $id : null;
        }

        return null;
    }

    public function slugForTypeId(int $typeId): ?string
    {
        if ($typeId <= 0) {
            return null;
        }

        return $this->extractSlugFromRow($this->db()->get('asset_types', 'slug', ['id' => $typeId]));
    }

    /**
     * Resolve the isolated physical table for a whitelisted asset type id.
     */
    public function tableNameForTypeIdStrict(int $assetTypeId): ?string
    {
        $slug = $this->slugForTypeId($assetTypeId);

        if ($slug === null) {
            return null;
        }

        return $this->tableNameForSlug($slug);
    }

    /**
     * Medoo returns a scalar string when selecting a single column via get().
     */
    private function extractSlugFromRow(mixed $row): ?string
    {
        if (is_string($row)) {
            $slug = trim($row);

            return $slug !== '' ? $slug : null;
        }

        if (is_array($row)) {
            $slug = trim((string) ($row['slug'] ?? ''));

            return $slug !== '' ? $slug : null;
        }

        return null;
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }

    private function normalizeSlug(string $slug): string
    {
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', mb_strtolower(trim($slug), 'UTF-8')) ?? '';

        return trim($normalized, '_');
    }

    private function isValidTableName(string $tableName): bool
    {
        return preg_match('/^assets_[a-z0-9_]+$/', $tableName) === 1;
    }

    private function isValidColumnIdentifier(string $columnName): bool
    {
        return preg_match('/^[a-z][a-z0-9_]*$/', $columnName) === 1;
    }

    private function mapFieldTypeToSql(string $fieldType): string
    {
        return match (mb_strtolower(trim($fieldType), 'UTF-8')) {
            'text', 'textarea' => 'TEXT NULL',
            'int', 'integer', 'number' => 'INT NULL',
            'date' => 'DATE NULL',
            'decimal' => 'DECIMAL(12,2) NULL',
            default => 'VARCHAR(255) NULL',
        };
    }

    private function mapFieldTypeToInput(string $fieldType): string
    {
        return match (mb_strtolower(trim($fieldType), 'UTF-8')) {
            'text', 'textarea' => 'textarea',
            'int', 'integer', 'number' => 'number',
            'date' => 'date',
            'decimal' => 'number',
            'dropdown', 'select' => 'select',
            default => 'text',
        };
    }

    private function isDuplicateColumnError(PDOException $exception): bool
    {
        $errorInfo = $exception->errorInfo ?? null;

        if (is_array($errorInfo) && isset($errorInfo[1]) && (int) $errorInfo[1] === 1060) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'duplicate column')
            || str_contains($message, '1060');
    }

    private function isMissingColumnError(PDOException $exception): bool
    {
        $errorInfo = $exception->errorInfo ?? null;

        if (is_array($errorInfo) && isset($errorInfo[1]) && (int) $errorInfo[1] === 1091) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, "check that column")
            || str_contains($message, "can't drop")
            || str_contains($message, '1091');
    }
}
