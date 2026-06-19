<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class Location
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
            $this->db()->select('locations', [
                'id',
                'name',
                'building',
                'description',
                'created_at',
            ], [
                'ORDER' => [
                    'building' => 'ASC',
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
        $row = $this->db()->get('locations', [
            'id',
            'name',
            'building',
            'description',
            'created_at',
        ], [
            'id' => $id,
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    public function findByName(string $name, ?string $building = null): ?array
    {
        $trimmedName = trim($name);

        if ($trimmedName === '') {
            return null;
        }

        $normalizedBuilding = $building !== null ? trim($building) : '';

        foreach ($this->findAll() as $row) {
            $rowName = trim((string) ($row['name'] ?? ''));
            $rowBuilding = trim((string) ($row['building'] ?? ''));

            if (mb_strtolower($rowName, 'UTF-8') !== mb_strtolower($trimmedName, 'UTF-8')) {
                continue;
            }

            if ($normalizedBuilding === '') {
                return $row;
            }

            if (mb_strtolower($rowBuilding, 'UTF-8') === mb_strtolower($normalizedBuilding, 'UTF-8')) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $name, ?string $building, ?string $description): array
    {
        $trimmedName = trim($name);

        if ($trimmedName === '') {
            throw new \InvalidArgumentException(__('location_name_required'));
        }

        $this->db()->insert('locations', [
            'name' => $trimmedName,
            'building' => $this->normalizeOptionalText($building),
            'description' => $this->normalizeOptionalText($description),
        ]);

        $created = $this->findById((int) $this->db()->id());

        if ($created === null) {
            throw new \RuntimeException(__('location_create_error'));
        }

        return $created;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function update(int $id, string $name, ?string $building, ?string $description): ?array
    {
        if ($this->findById($id) === null) {
            return null;
        }

        $trimmedName = trim($name);

        if ($trimmedName === '') {
            throw new \InvalidArgumentException(__('location_name_required'));
        }

        $this->db()->update('locations', [
            'name' => $trimmedName,
            'building' => $this->normalizeOptionalText($building),
            'description' => $this->normalizeOptionalText($description),
        ], [
            'id' => $id,
        ]);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        if ($this->countAssets($id) > 0) {
            throw new \RuntimeException(__('location_delete_in_use'));
        }

        $this->db()->delete('locations', [
            'id' => $id,
        ]);

        return true;
    }

    public function countAssets(int $locationId): int
    {
        return $this->db()->count('assets', [
            'location_id' => $locationId,
        ]);
    }

    public function exists(int $locationId): bool
    {
        return $this->db()->has('locations', [
            'id' => $locationId,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) $row['id'];

        return $row;
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
