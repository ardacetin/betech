<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AssetTypeTableService;
use App\Services\DatabaseService;
use App\Services\DdlIdentifierGuard;
use JsonException;
use Medoo\Medoo;

class AssetCustomField
{
    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly AssetTypeTableService $assetTypeTableService,
        private readonly AssetType $assetTypeModel,
        private readonly DdlIdentifierGuard $ddlIdentifierGuard,
    ) {
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByAssetTypeId(int $assetTypeId, bool $activeOnly = true): array
    {
        $conditions = [
            'asset_type_id' => $assetTypeId,
            'ORDER' => [
                'sort_order' => 'ASC',
                'label' => 'ASC',
            ],
        ];

        if ($activeOnly && $this->hasIsActiveColumn()) {
            $conditions['is_active'] = 1;
        }

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $this->db()->select('asset_custom_fields', '*', $conditions)
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db()->get('asset_custom_fields', '*', [
            'id' => $id,
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * @param list<string> $options
     *
     * @return array<string, mixed>
     */
    public function create(
        int $assetTypeId,
        string $label,
        string $fieldType = 'varchar',
        array $options = [],
        int $sortOrder = 0
    ): array {
        $assetType = $this->assetTypeModel->findById($assetTypeId);

        if ($assetType === null) {
            throw new \InvalidArgumentException(__('asset_type_not_found'));
        }

        $trimmedLabel = trim($label);

        if ($trimmedLabel === '') {
            throw new \InvalidArgumentException(__('asset_custom_field_label_required'));
        }

        $columnName = $this->uniqueColumnName(
            $assetTypeId,
            $this->ddlIdentifierGuard->columnNameFromLabel($trimmedLabel)
        );
        $tableName = $this->assetTypeTableService->createTableForSlug((string) $assetType['slug']);
        $this->assetTypeTableService->addColumn($tableName, $columnName, $fieldType);

        if ($sortOrder <= 0) {
            $sortOrder = $this->nextSortOrder($assetTypeId);
        }

        $insert = [
            'asset_type_id' => $assetTypeId,
            'label' => $trimmedLabel,
            'column_name' => $columnName,
            'field_type' => $this->normalizeFieldType($fieldType),
            'options' => $this->encodeOptions($options),
            'sort_order' => $sortOrder,
        ];

        if ($this->hasIsActiveColumn()) {
            $insert['is_active'] = 1;
        }

        $this->db()->insert('asset_custom_fields', $insert);

        $created = $this->findById((int) $this->db()->id());

        if ($created === null) {
            throw new \RuntimeException(__('asset_custom_field_create_error'));
        }

        return $created;
    }

    /**
     * @param list<string> $options
     *
     * @return array<string, mixed>|null
     */
    public function update(
        int $id,
        string $label,
        string $fieldType = 'varchar',
        array $options = [],
        ?int $sortOrder = null
    ): ?array {
        $existing = $this->findById($id);

        if ($existing === null) {
            return null;
        }

        $trimmedLabel = trim($label);

        if ($trimmedLabel === '') {
            throw new \InvalidArgumentException(__('asset_custom_field_label_required'));
        }

        $updateData = [
            'label' => $trimmedLabel,
            'field_type' => $this->normalizeFieldType($fieldType),
            'options' => $this->encodeOptions($options),
        ];

        if ($sortOrder !== null && $sortOrder > 0) {
            $updateData['sort_order'] = $sortOrder;
        }

        $this->db()->update('asset_custom_fields', $updateData, [
            'id' => $id,
        ]);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            return false;
        }

        if ($this->hasIsActiveColumn()) {
            $this->db()->update('asset_custom_fields', [
                'is_active' => 0,
            ], [
                'id' => $id,
            ]);

            $updated = $this->findById($id);

            return is_array($updated) && (int) ($updated['is_active'] ?? 1) === 0;
        }

        $this->db()->delete('asset_custom_fields', [
            'id' => $id,
        ]);

        return $this->findById($id) === null;
    }

    private function hasIsActiveColumn(): bool
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $statement = $this->db()->query(
            "SHOW COLUMNS FROM `asset_custom_fields` LIKE 'is_active'"
        );

        if ($statement === false) {
            $cached = false;

            return false;
        }

        $cached = $statement->fetch() !== false;

        return $cached;
    }

    private function nextSortOrder(int $assetTypeId): int
    {
        $row = $this->db()->max('asset_custom_fields', 'sort_order', [
            'asset_type_id' => $assetTypeId,
        ]);

        return max(1, ((int) $row) + 1);
    }

    private function uniqueColumnName(int $assetTypeId, string $baseName): string
    {
        $name = $this->ddlIdentifierGuard->assertSafeIdentifier($baseName !== '' ? $baseName : 'custom_field', 'column');
        $suffix = 2;

        while ($this->columnNameExists($assetTypeId, $name)
            || in_array($name, AssetTypeTableService::BASE_COLUMNS, true)) {
            $name = $this->ddlIdentifierGuard->assertSafeIdentifier($baseName . '_' . $suffix, 'column');
            ++$suffix;
        }

        return $name;
    }

    private function columnNameExists(int $assetTypeId, string $columnName): bool
    {
        return $this->db()->has('asset_custom_fields', [
            'asset_type_id' => $assetTypeId,
            'column_name' => $columnName,
        ]);
    }

    private function normalizeFieldType(string $fieldType): string
    {
        $normalized = mb_strtolower(trim($fieldType), 'UTF-8');

        return in_array($normalized, ['varchar', 'text', 'int', 'date', 'decimal', 'number', 'dropdown'], true)
            ? ($normalized === 'number' ? 'int' : $normalized)
            : 'varchar';
    }

    /**
     * @param list<string> $options
     */
    private function encodeOptions(array $options): ?string
    {
        $normalized = [];

        foreach ($options as $option) {
            $value = trim((string) $option);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        if ($normalized === []) {
            return null;
        }

        try {
            return json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return null;
        }
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

        if (isset($row['asset_type_id'])) {
            $row['asset_type_id'] = (int) $row['asset_type_id'];
        }

        if (isset($row['sort_order'])) {
            $row['sort_order'] = (int) $row['sort_order'];
        }

        if (isset($row['is_active'])) {
            $row['is_active'] = (int) $row['is_active'];
        }

        $options = $row['options'] ?? null;

        if (is_string($options) && $options !== '') {
            try {
                $decoded = json_decode($options, true, 512, JSON_THROW_ON_ERROR);
                $row['options'] = is_array($decoded) ? $decoded : [];
            } catch (JsonException) {
                $row['options'] = [];
            }
        } elseif (!is_array($options)) {
            $row['options'] = [];
        }

        return $row;
    }
}
