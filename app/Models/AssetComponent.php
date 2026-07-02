<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class AssetComponent
{
    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly AssetType $assetTypeModel,
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
        if ($this->assetTypeModel->findById($assetTypeId) === null) {
            throw new \InvalidArgumentException(__('asset_type_not_found'));
        }

        $trimmedName = trim($name);

        if ($trimmedName === '') {
            throw new \InvalidArgumentException(__('asset_component_name_required'));
        }

        $slug = $this->ensureUniqueSlug($assetTypeId, $this->generateSlug($trimmedName));

        if ($sortOrder <= 0) {
            $sortOrder = $this->nextSortOrder($assetTypeId);
        }

        $this->db()->insert('asset_components', [
            'asset_type_id' => $assetTypeId,
            'name' => $trimmedName,
            'slug' => $slug,
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

        $this->db()->delete('asset_component_values', [
            'component_id' => $id,
        ]);

        $this->db()->delete('asset_components', [
            'id' => $id,
        ]);

        return $this->findById($id) === null;
    }

    /**
     * @return array<string, string>
     */
    public function getValuesForAsset(int $assetTypeId, int $assetId): array
    {
        $rows = $this->db()->select('asset_component_values', [
            'component_id',
            'value',
        ], [
            'asset_type_id' => $assetTypeId,
            'asset_id' => $assetId,
        ]);

        $values = [];

        foreach ($rows as $row) {
            $values[(string) ($row['component_id'] ?? '')] = (string) ($row['value'] ?? '');
        }

        return $values;
    }

    /**
     * @param array<int|string, string|null> $valuesByComponentId
     */
    public function syncValuesForAsset(int $assetTypeId, int $assetId, array $valuesByComponentId): void
    {
        foreach ($valuesByComponentId as $componentId => $value) {
            $componentId = (int) $componentId;

            if ($componentId <= 0) {
                continue;
            }

            $trimmedValue = trim((string) ($value ?? ''));

            if ($trimmedValue === '') {
                $this->db()->delete('asset_component_values', [
                    'asset_type_id' => $assetTypeId,
                    'asset_id' => $assetId,
                    'component_id' => $componentId,
                ]);

                continue;
            }

            $existing = $this->db()->get('asset_component_values', 'id', [
                'asset_type_id' => $assetTypeId,
                'asset_id' => $assetId,
                'component_id' => $componentId,
            ]);

            if ($existing !== null) {
                $this->db()->update('asset_component_values', [
                    'value' => $trimmedValue,
                ], [
                    'asset_type_id' => $assetTypeId,
                    'asset_id' => $assetId,
                    'component_id' => $componentId,
                ]);

                continue;
            }

            $this->db()->insert('asset_component_values', [
                'asset_type_id' => $assetTypeId,
                'asset_id' => $assetId,
                'component_id' => $componentId,
                'value' => $trimmedValue,
            ]);
        }
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

        return $row;
    }
}
