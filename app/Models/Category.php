<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use JsonException;
use Medoo\Medoo;

class Category
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
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $this->db()->select('categories', [
                'id',
                'name',
                'slug',
                'fields',
            ], [
                'ORDER' => ['name' => 'ASC'],
            ])
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db()->get('categories', [
            'id',
            'name',
            'slug',
            'fields',
        ], [
            'id' => $id,
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    public function create(string $name, array $fields): array
    {
        $trimmedName = trim($name);

        if ($trimmedName === '') {
            throw new \InvalidArgumentException('Category name is required.');
        }

        $slug = $this->ensureUniqueSlug($this->generateSlug($trimmedName));

        $this->db()->insert('categories', [
            'name' => $trimmedName,
            'slug' => $slug,
            'fields' => json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);

        $created = $this->findById((int) $this->db()->id());

        if ($created === null) {
            throw new \RuntimeException('Failed to create category.');
        }

        return $created;
    }

    /**
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>|null
     */
    public function update(int $id, string $name, array $fields): ?array
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            return null;
        }

        $trimmedName = trim($name);

        if ($trimmedName === '') {
            throw new \InvalidArgumentException('Category name is required.');
        }

        $slug = (string) ($existing['slug'] ?? '');

        if ($trimmedName !== (string) ($existing['name'] ?? '')) {
            $slug = $this->ensureUniqueSlug($this->generateSlug($trimmedName), $id);
        }

        $this->db()->update('categories', [
            'name' => $trimmedName,
            'slug' => $slug,
            'fields' => json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ], [
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

        if ($this->countAssets($id) > 0) {
            throw new \RuntimeException('Category is in use by existing assets.');
        }

        $this->db()->delete('categories', [
            'id' => $id,
        ]);

        return true;
    }

    public function countAssets(int $categoryId): int
    {
        return $this->db()->count('assets', [
            'category_id' => $categoryId,
        ]);
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

        return $slug !== '' ? $slug : 'category';
    }

    private function ensureUniqueSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $candidate = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($candidate, $ignoreId)) {
            $candidate = $baseSlug . '-' . $suffix;
            ++$suffix;
        }

        return $candidate;
    }

    private function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $conditions = [
            'slug' => $slug,
        ];

        if ($ignoreId !== null) {
            $conditions['id[!]'] = $ignoreId;
        }

        return $this->db()->has('categories', $conditions);
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    public function fieldMapByCategoryId(): array
    {
        $map = [];

        foreach ($this->findAll() as $category) {
            $map[(int) $category['id']] = $category['fields'];
        }

        return $map;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function decodeFields(mixed $fields): array
    {
        if ($fields === null || $fields === '') {
            return [];
        }

        if (is_array($fields)) {
            return $fields;
        }

        if (!is_string($fields)) {
            return [];
        }

        try {
            $decoded = json_decode($fields, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['fields'] = $this->decodeFields($row['fields'] ?? null);

        return $row;
    }
}
