<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AssetTypeTableService;
use App\Services\DatabaseService;
use App\Services\DdlIdentifierGuard;
use Medoo\Medoo;

class AssetType
{
    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly AssetTypeTableService $assetTypeTableService,
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
    public function findAll(): array
    {
        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $this->db()->select('asset_types', [
                'id',
                'name',
                'slug',
                'sort_order',
            ], [
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
        $row = $this->db()->get('asset_types', [
            'id',
            'name',
            'slug',
            'sort_order',
        ], [
            'id' => $id,
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $row = $this->db()->get('asset_types', [
            'id',
            'name',
            'slug',
            'sort_order',
        ], [
            'slug' => trim($slug),
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $name, int $sortOrder = 0): array
    {
        $trimmedName = trim($name);

        if ($trimmedName === '') {
            throw new \InvalidArgumentException(__('asset_type_name_required'));
        }

        if ($sortOrder <= 0) {
            $sortOrder = $this->nextSortOrder();
        }

        $slug = $this->ensureUniqueSlug($this->generateSlug($trimmedName));
        $this->ddlIdentifierGuard->assertSafeSlug($slug);

        $this->db()->insert('asset_types', [
            'name' => $trimmedName,
            'slug' => $slug,
            'sort_order' => $sortOrder,
        ]);

        $created = $this->findById((int) $this->db()->id());

        if ($created === null) {
            throw new \RuntimeException(__('asset_type_create_error'));
        }

        $this->assetTypeTableService->createTableForSlug((string) $created['slug']);

        return $created;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function update(int $id, string $name, ?int $sortOrder = null): ?array
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            return null;
        }

        $trimmedName = trim($name);

        if ($trimmedName === '') {
            throw new \InvalidArgumentException(__('asset_type_name_required'));
        }

        $slug = (string) ($existing['slug'] ?? '');

        if ($trimmedName !== (string) ($existing['name'] ?? '')) {
            $slug = $this->ensureUniqueSlug($this->generateSlug($trimmedName), $id);
            $this->ddlIdentifierGuard->assertSafeSlug($slug);
        }

        $updateData = [
            'name' => $trimmedName,
            'slug' => $slug,
        ];

        if ($sortOrder !== null && $sortOrder > 0) {
            $updateData['sort_order'] = $sortOrder;
        }

        $this->db()->update('asset_types', $updateData, [
            'id' => $id,
        ]);

        $updated = $this->findById($id);

        if ($updated !== null) {
            $this->assetTypeTableService->createTableForSlug((string) $updated['slug']);
        }

        return $updated;
    }

    public function delete(int $id): bool
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            return false;
        }

        if ($this->countAssets($id) > 0) {
            throw new \RuntimeException(__('asset_type_delete_in_use'));
        }

        $slug = (string) ($existing['slug'] ?? '');

        $this->db()->delete('asset_types', [
            'id' => $id,
        ]);

        if ($slug !== '') {
            $this->assetTypeTableService->dropTableForSlug($slug);
        }

        return $this->findById($id) === null;
    }

    public function countAssets(int $assetTypeId): int
    {
        try {
            $tableName = $this->assetTypeTableService->tableNameForTypeId($assetTypeId);

            if ($this->assetTypeTableService->tableExists($tableName)) {
                return $this->assetTypeTableService->countRows($tableName);
            }
        } catch (\Throwable) {
        }

        if (!$this->db()->has('assets', ['asset_type_id' => $assetTypeId])) {
            return 0;
        }

        return (int) $this->db()->count('assets', [
            'asset_type_id' => $assetTypeId,
        ]);
    }

    public function nextSortOrder(): int
    {
        $row = $this->db()->max('asset_types', 'sort_order');

        return max(1, ((int) $row) + 1);
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

        if (isset($row['sort_order'])) {
            $row['sort_order'] = (int) $row['sort_order'];
        }

        return $row;
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

        return $slug !== '' ? $slug : 'asset-type';
    }

    private function ensureUniqueSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $baseSlug . '-' . $suffix;
            ++$suffix;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $conditions = ['slug' => $slug];

        if ($ignoreId !== null) {
            $conditions['id[!]'] = $ignoreId;
        }

        return $this->db()->has('asset_types', $conditions);
    }
}
