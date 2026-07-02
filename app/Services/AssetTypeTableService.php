<?php

declare(strict_types=1);

namespace App\Services;

use Medoo\Medoo;
use PDOException;
use RuntimeException;

class AssetTypeTableService
{
    /** @var list<string> */
    public const BASE_COLUMNS = [
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

    /** @var array<string, list<string>> */
    private array $tableColumnsCache = [];

    public function __construct(
        private readonly DatabaseService $databaseService,
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

    public function tableNameForTypeId(int $assetTypeId): string
    {
        $row = $this->db()->get('asset_types', 'slug', [
            'id' => $assetTypeId,
        ]);

        if (!is_array($row) || trim((string) ($row['slug'] ?? '')) === '') {
            throw new RuntimeException(__('asset_type_not_found'));
        }

        return $this->tableNameForSlug((string) $row['slug']);
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
            return $tableName;
        }

        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                asset_tag VARCHAR(64) NOT NULL,
                name VARCHAR(255) NOT NULL,
                model VARCHAR(255) DEFAULT NULL,
                brand VARCHAR(255) DEFAULT NULL,
                serial_number VARCHAR(128) DEFAULT NULL,
                type VARCHAR(255) DEFAULT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'ready\',
                location VARCHAR(255) DEFAULT NULL,
                building VARCHAR(255) DEFAULT NULL,
                assigned_to VARCHAR(255) DEFAULT NULL,
                mac_address_1 VARCHAR(255) DEFAULT NULL,
                mac_address_2 VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_%s_asset_tag (asset_tag),
                KEY idx_%s_serial_number (serial_number),
                KEY idx_%s_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            $tableName,
            $this->normalizeSlug($slug),
            $this->normalizeSlug($slug),
            $this->normalizeSlug($slug)
        );

        $this->db()->query($sql);
        unset($this->tableColumnsCache[$tableName]);

        return $tableName;
    }

    public function dropTableForSlug(string $slug): void
    {
        $tableName = $this->tableNameForSlug($slug);

        if (!$this->tableExists($tableName)) {
            return;
        }

        $this->db()->query(sprintf('DROP TABLE `%s`', $tableName));
        unset($this->tableColumnsCache[$tableName]);
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

        return $columns;
    }

    public function columnExists(string $tableName, string $columnName): bool
    {
        return in_array($columnName, $this->listTableColumns($tableName), true);
    }

    public function addColumn(string $tableName, string $columnName, string $fieldType = 'varchar'): bool
    {
        if (!$this->isValidColumnIdentifier($columnName)) {
            throw new RuntimeException(sprintf('Invalid column name: %s', $columnName));
        }

        if ($this->columnExists($tableName, $columnName)) {
            return true;
        }

        $sqlType = $this->mapFieldTypeToSql($fieldType);
        $sql = sprintf(
            'ALTER TABLE `%s` ADD COLUMN `%s` %s NULL DEFAULT NULL',
            $tableName,
            $columnName,
            $sqlType
        );

        try {
            $this->db()->query($sql);
        } catch (PDOException $exception) {
            if ($this->isDuplicateColumnError($exception)) {
                unset($this->tableColumnsCache[$tableName]);

                return true;
            }

            throw new RuntimeException(
                sprintf('Failed to add column `%s` on `%s`: %s', $columnName, $tableName, $exception->getMessage()),
                0,
                $exception
            );
        }

        unset($this->tableColumnsCache[$tableName]);

        return true;
    }

    public function dropColumn(string $tableName, string $columnName): void
    {
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

        $this->db()->query(sprintf(
            'ALTER TABLE `%s` DROP COLUMN `%s`',
            $tableName,
            $columnName
        ));

        unset($this->tableColumnsCache[$tableName]);
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
    public function buildSchemaDefinition(int $assetTypeId, array $customFields = []): array
    {
        $tableName = $this->tableNameForTypeId($assetTypeId);
        $columns = $this->listTableColumns($tableName);
        $customByColumn = [];

        foreach ($customFields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $columnName = trim((string) ($field['column_name'] ?? ''));

            if ($columnName !== '') {
                $customByColumn[$columnName] = $field;
            }
        }

        $schema = [];

        foreach (AssetColumnSchemaService::NATIVE_COLUMN_LABELS as $column => $label) {
            if (!in_array($column, $columns, true)) {
                continue;
            }

            $schema[] = [
                'column' => $column,
                'label' => $label,
                'type' => 'varchar',
                'is_custom' => false,
                'input' => $column === 'status' ? 'select' : 'text',
            ];
        }

        foreach ($customFields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $columnName = trim((string) ($field['column_name'] ?? ''));

            if ($columnName === '' || !in_array($columnName, $columns, true)) {
                continue;
            }

            $schema[] = [
                'column' => $columnName,
                'label' => (string) ($field['label'] ?? $columnName),
                'type' => (string) ($field['field_type'] ?? 'varchar'),
                'is_custom' => true,
                'input' => $this->mapFieldTypeToInput((string) ($field['field_type'] ?? 'varchar')),
                'options' => is_array($field['options'] ?? null) ? $field['options'] : [],
            ];
        }

        unset($customByColumn);

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

        return is_array($row) && isset($row['id']) ? (int) $row['id'] : null;
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
            'text', 'textarea' => 'TEXT',
            'number', 'int', 'integer' => 'INT NULL',
            default => 'VARCHAR(255)',
        };
    }

    private function mapFieldTypeToInput(string $fieldType): string
    {
        return match (mb_strtolower(trim($fieldType), 'UTF-8')) {
            'text', 'textarea' => 'textarea',
            'number', 'int', 'integer' => 'number',
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
}
