<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Medoo\Medoo;
use PDOException;
use RuntimeException;

class AssetColumnSchemaService
{
    /** @var list<string> */
    public const SYSTEM_COLUMNS = ['id', 'created_at', 'updated_at'];

    /**
     * Native inventory columns and their Turkish CSV/UI labels.
     *
     * @var array<string, string>
     */
    public const NATIVE_COLUMN_LABELS = [
        'asset_tag' => 'Demirbaş No',
        'name' => 'Cihaz Adı',
        'model' => 'Model',
        'brand' => 'Marka',
        'serial_number' => 'Seri No',
        'type' => 'Tür',
        'status' => 'Durum',
        'location' => 'Lokasyon',
        'building' => 'Bina',
        'assigned_to' => 'Zimmetli Kişi',
        'mac_address_1' => 'Mac Adresi 1',
        'mac_address_2' => 'Mac Adresi 2',
    ];

    /** @var list<string>|null */
    private ?array $tableColumnsCache = null;

    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly Setting $settingModel,
    ) {
    }

    /**
     * @return list<string>
     */
    public function nativeColumns(): array
    {
        return array_keys(self::NATIVE_COLUMN_LABELS);
    }

    /**
     * @return list<array{id: int, name: string, label: string, type: string}>
     */
    public function getActiveCustomFields(): array
    {
        $settings = $this->settingModel->getAdminBundle();
        $customFields = $settings['custom_fields'] ?? [];

        if (!is_array($customFields)) {
            return [];
        }

        $normalized = [];

        foreach ($customFields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));
            $label = trim((string) ($field['label'] ?? ''));

            if ($name === '' || $label === '' || !$this->isValidCustomColumnName($name)) {
                continue;
            }

            $normalized[] = [
                'id' => isset($field['id']) && is_numeric($field['id']) ? (int) $field['id'] : 0,
                'name' => $name,
                'label' => $label,
                'type' => trim((string) ($field['type'] ?? 'text')) ?: 'text',
            ];
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    public function listAssetsTableColumns(): array
    {
        if ($this->tableColumnsCache !== null) {
            return $this->tableColumnsCache;
        }

        $statement = $this->db()->query('SHOW COLUMNS FROM `assets`');

        if ($statement === false) {
            throw new RuntimeException('Unable to read assets table schema.');
        }

        $columns = [];

        foreach ($statement->fetchAll() as $row) {
            $name = trim((string) ($row['Field'] ?? ''));

            if ($name !== '') {
                $columns[] = $name;
            }
        }

        $this->tableColumnsCache = $columns;

        return $columns;
    }

    public function columnExists(string $columnName): bool
    {
        if (!$this->isValidColumnIdentifier($columnName)) {
            return false;
        }

        return in_array($columnName, $this->listAssetsTableColumns(), true);
    }

    public function isValidCustomColumnName(string $columnName): bool
    {
        if (!$this->isValidColumnIdentifier($columnName)) {
            return false;
        }

        if (in_array($columnName, self::SYSTEM_COLUMNS, true)) {
            return false;
        }

        return !in_array($columnName, $this->nativeColumns(), true);
    }

    /**
     * @return list<string>
     */
    public function getWritableColumnNames(): array
    {
        $tableColumns = $this->listAssetsTableColumns();
        $allowed = array_merge($this->nativeColumns(), array_column($this->getActiveCustomFields(), 'name'));
        $allowed = array_values(array_unique($allowed));

        return array_values(array_intersect($allowed, $tableColumns));
    }

    public function isQueryableColumn(string $column): bool
    {
        if (in_array($column, self::SYSTEM_COLUMNS, true)) {
            return false;
        }

        return in_array($column, $this->listAssetsTableColumns(), true);
    }

    /**
     * @param list<array{id: int, name: string, label: string, type: string}> $normalizedFields
     * @param list<array{id: int, name: string, label: string, type: string}> $previousFields
     */
    public function syncCustomFieldColumns(array $normalizedFields, array $previousFields = []): void
    {
        unset($previousFields);

        $this->ensureConfiguredCustomColumns($normalizedFields);
    }

    /**
     * @param list<array{id: int, name: string, label: string, type: string}> $customFields
     */
    public function ensureConfiguredCustomColumns(array $customFields = []): void
    {
        $fields = $customFields !== [] ? $customFields : $this->getActiveCustomFields();

        foreach ($fields as $field) {
            $name = trim((string) ($field['name'] ?? ''));

            if ($name === '' || !$this->isValidCustomColumnName($name)) {
                continue;
            }

            $this->ensureColumnExists($name);
        }
    }

    public function ensureColumnExists(string $columnName): bool
    {
        if (!$this->isValidCustomColumnName($columnName)) {
            throw new RuntimeException(sprintf('Invalid custom field column name: %s', $columnName));
        }

        if ($this->columnExists($columnName)) {
            return true;
        }

        $sql = sprintf(
            'ALTER TABLE `assets` ADD COLUMN `%s` VARCHAR(255) NULL DEFAULT NULL',
            $columnName
        );

        try {
            $this->db()->query($sql);
        } catch (PDOException $exception) {
            if ($this->isDuplicateColumnError($exception)) {
                $this->tableColumnsCache = null;

                return true;
            }

            throw new RuntimeException(
                sprintf('Failed to add assets column `%s`: %s', $columnName, $exception->getMessage()),
                0,
                $exception
            );
        }

        $this->tableColumnsCache = null;

        return true;
    }

    /**
     * @return list<array{column: string, label: string}>
     */
    public function buildExportSchema(): array
    {
        $this->ensureConfiguredCustomColumns();

        $schema = [];

        foreach (self::NATIVE_COLUMN_LABELS as $column => $label) {
            if ($this->columnExists($column)) {
                $schema[] = [
                    'column' => $column,
                    'label' => $label,
                ];
            }
        }

        foreach ($this->getActiveCustomFields() as $field) {
            if (!$this->columnExists($field['name'])) {
                continue;
            }

            $schema[] = [
                'column' => $field['name'],
                'label' => $field['label'],
            ];
        }

        return $schema;
    }

    public function buildTemplateCsvContent(): string
    {
        $headers = array_column($this->buildExportSchema(), 'label');
        $sampleValues = [
            'ENV-GLPI-001',
            'BT Departman Laptop',
            'Latitude 5540',
            'Dell',
            'SN-GLPI-001',
            'Bilgisayar',
            'deployed',
            'IT Depo',
            'Merkez Kampüs',
            'ahmet.yilmaz@sirket.com',
            'AA:BB:CC:DD:EE:01',
            'AA:BB:CC:DD:EE:02',
        ];

        $schema = $this->buildExportSchema();
        $row = [];

        foreach ($schema as $index => $definition) {
            $row[] = $sampleValues[$index] ?? '';
        }

        return $this->buildCsvLine($headers) . $this->buildCsvLine($row);
    }

    /**
     * @param list<string> $headers
     *
     * @return array<string, int>
     */
    public function buildHeaderColumnMap(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $column = $this->resolveHeaderToColumn($header);

            if ($column === null || isset($map[$column])) {
                continue;
            }

            $map[$column] = $index;
        }

        return $map;
    }

    public function resolveHeaderToColumn(string $header): ?string
    {
        $trimmed = trim($header);

        if ($trimmed === '') {
            return null;
        }

        $normalizedHeader = $this->normalizeHeaderKey($trimmed);

        foreach (self::NATIVE_COLUMN_LABELS as $column => $label) {
            if (
                $this->normalizeHeaderKey($label) === $normalizedHeader
                || $this->normalizeHeaderKey($column) === $normalizedHeader
                || mb_strtolower($column, 'UTF-8') === mb_strtolower($trimmed, 'UTF-8')
            ) {
                return $column;
            }
        }

        foreach ($this->getActiveCustomFields() as $field) {
            if (
                $this->normalizeHeaderKey($field['label']) === $normalizedHeader
                || $this->normalizeHeaderKey($field['name']) === $normalizedHeader
                || mb_strtolower($field['name'], 'UTF-8') === mb_strtolower($trimmed, 'UTF-8')
            ) {
                return $field['name'];
            }
        }

        $generated = custom_field_code_from_label($trimmed);

        if ($this->columnExists($generated)) {
            return $generated;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    public function filterWritableFields(array $fields): array
    {
        $writable = array_flip($this->getWritableColumnNames());
        $filtered = [];

        foreach ($fields as $key => $value) {
            if (!is_string($key) || !isset($writable[$key])) {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }

    private function isValidColumnIdentifier(string $columnName): bool
    {
        return preg_match('/^[a-z][a-z0-9_]*$/', $columnName) === 1;
    }

    private function normalizeHeaderKey(string $header): string
    {
        return custom_field_code_from_label($header);
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

    /**
     * @param list<string> $fields
     */
    private function buildCsvLine(array $fields): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, $fields, ',', '"', '\\');
        rewind($handle);
        $line = stream_get_contents($handle);
        fclose($handle);

        return is_string($line) ? $line : '';
    }
}
