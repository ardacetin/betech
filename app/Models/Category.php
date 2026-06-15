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
