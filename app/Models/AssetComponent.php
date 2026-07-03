<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AssetTypeTableService;
use App\Services\DatabaseService;
use Medoo\Medoo;

class AssetComponent
{
    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly AssetType $assetTypeModel,
        private readonly AssetTypeTableService $assetTypeTableService,
    ) {
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByAssetTypeId(int $assetTypeId): array
    {
        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $this->db()->select('asset_components', '*', [
                'asset_type_id' => $assetTypeId,
                'ORDER' => [
                    'sort_order' => 'ASC',
                    'name' => 'ASC',
                ],
            ])
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db()->get('asset_components', '*', [
            'id' => $id,
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(int $assetTypeId, string $name, string $description = '', int $sortOrder = 0): array
    {
        $assetType = $this->assetTypeModel->findById($assetTypeId);

        if ($assetType === null) {
            throw new \InvalidArgumentException(__('asset_type_not_found'));
        }

        $trimmedName = trim($name);

        if ($trimmedName === '') {
            throw new \InvalidArgumentException(__('asset_component_name_required'));
        }

        $slug = $this->ensureUniqueSlug($assetTypeId, $this->generateSlug($trimmedName));
        $columnName = $this->uniqueColumnName($assetTypeId, $this->generateColumnName($trimmedName));
        $tableName = $this->assetTypeTableService->createTableForSlug((string) $assetType['slug']);
        $this->assetTypeTableService->addColumn($tableName, $columnName);

        if ($sortOrder <= 0) {
            $sortOrder = $this->nextSortOrder($assetTypeId);
        }

        $this->db()->insert('asset_components', [
            'asset_type_id' => $assetTypeId,
            'name' => $trimmedName,
            'slug' => $slug,
            'column_name' => $columnName,
            'description' => trim($description) !== '' ? trim($description) : null,
            'sort_order' => $sortOrder,
        ]);

        $created = $this->findById((int) $this->db()->id());

        if ($created === null) {
            throw new \RuntimeException(__('asset_component_create_error'));
        }

        return $created;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function update(
        int $id,
        string $name,
        string $description = '',
        ?int $sortOrder = null
    ): ?array {
        $existing = $this->findById($id);

        if ($existing === null) {
            return null;
        }

        $trimmedName = trim($name);

        if ($trimmedName === '') {
            throw new \InvalidArgumentException(__('asset_component_name_required'));
        }

        $assetTypeId = (int) ($existing['asset_type_id'] ?? 0);
        $slug = (string) ($existing['slug'] ?? '');

        if ($trimmedName !== (string) ($existing['name'] ?? '')) {
            $slug = $this->ensureUniqueSlug($assetTypeId, $this->generateSlug($trimmedName), $id);
        }

        $updateData = [
            'name' => $trimmedName,
            'slug' => $slug,
            'description' => trim($description) !== '' ? trim($description) : null,
        ];

        if ($sortOrder !== null && $sortOrder > 0) {
            $updateData['sort_order'] = $sortOrder;
        }

        $this->db()->update('asset_components', $updateData, [
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

        $assetTypeId = (int) ($existing['asset_type_id'] ?? 0);
        $columnName = trim((string) ($existing['column_name'] ?? ''));

        if ($columnName !== '' && $assetTypeId > 0) {
            $tableName = $this->assetTypeTableService->tableNameForTypeId($assetTypeId);

            if ($tableName !== null && $this->assetTypeTableService->tableExists($tableName)) {
                try {
                    $this->assetTypeTableService->dropColumn($tableName, $columnName);
                } catch (\Throwable) {
                }
            }
        }

        $this->db()->delete('asset_components', [
            'id' => $id,
        ]);

        return $this->findById($id) === null;
    }

    private function nextSortOrder(int $assetTypeId): int
    {
        $row = $this->db()->max('asset_components', 'sort_order', [
            'asset_type_id' => $assetTypeId,
        ]);

        return max(1, ((int) $row) + 1);
    }

    private function generateSlug(string $name): string
    {
        $normalized = mb_strtolower(trim($name), 'UTF-8');
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);

        if ($transliterated === false) {
            $transliterated = $normalized;
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', $transliterated) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'component';
    }

    private function generateColumnName(string $name): string
    {
        $base = custom_field_code_from_label($name);

        return str_starts_with($base, 'comp_') ? $base : 'comp_' . $base;
    }

    private function uniqueColumnName(int $assetTypeId, string $baseName): string
    {
        $name = $baseName !== '' ? $baseName : 'comp_field';
        $suffix = 2;

        while ($this->columnNameExists($assetTypeId, $name)
            || in_array($name, AssetTypeTableService::BASE_COLUMNS, true)) {
            $name = $baseName . '_' . $suffix;
            ++$suffix;
        }

        return $name;
    }

    private function columnNameExists(int $assetTypeId, string $columnName): bool
    {
        if ($this->db()->has('asset_components', [
            'asset_type_id' => $assetTypeId,
            'column_name' => $columnName,
        ])) {
            return true;
        }

        if ($this->db()->has('asset_custom_fields', [
            'asset_type_id' => $assetTypeId,
            'column_name' => $columnName,
        ])) {
            return true;
        }

        return false;
    }

    private function ensureUniqueSlug(int $assetTypeId, string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($assetTypeId, $slug, $ignoreId)) {
            $slug = $baseSlug . '-' . $suffix;
            ++$suffix;
        }

        return $slug;
    }

    private function slugExists(int $assetTypeId, string $slug, ?int $ignoreId = null): bool
    {
        $conditions = [
            'asset_type_id' => $assetTypeId,
            'slug' => $slug,
        ];

        if ($ignoreId !== null) {
            $conditions['id[!]'] = $ignoreId;
        }

        return $this->db()->has('asset_components', $conditions);
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

        if (trim((string) ($row['column_name'] ?? '')) === '' && trim((string) ($row['name'] ?? '')) !== '') {
            $row['column_name'] = $this->generateColumnName((string) $row['name']);
        }

        return $row;
    }
}
