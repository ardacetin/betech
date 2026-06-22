<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class TicketCategory
{
    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $rows = $this->db()->select('ticket_categories', [
            'id',
            'name',
            'color_code',
            'created_at',
            'updated_at',
        ], [
            'ORDER' => ['name' => 'ASC', 'id' => 'ASC'],
        ]);

        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db()->get('ticket_categories', [
            'id',
            'name',
            'color_code',
            'created_at',
            'updated_at',
        ], ['id' => $id]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $name, string $colorCode): array
    {
        $normalizedName = $this->normalizeName($name);
        $normalizedColor = $this->normalizeColorCode($colorCode);

        $this->db()->insert('ticket_categories', [
            'name' => $normalizedName,
            'color_code' => $normalizedColor,
        ]);

        $created = $this->findById((int) $this->db()->id());

        if ($created === null) {
            throw new \RuntimeException(__('ticket_category_create_error'));
        }

        return $created;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function update(int $id, string $name, string $colorCode): ?array
    {
        if ($this->findById($id) === null) {
            return null;
        }

        $this->db()->update('ticket_categories', [
            'name' => $this->normalizeName($name),
            'color_code' => $this->normalizeColorCode($colorCode),
        ], ['id' => $id]);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $this->db()->update('tickets', ['category_id' => null], ['category_id' => $id]);
        $this->db()->delete('ticket_categories', ['id' => $id]);

        return !$this->db()->has('ticket_categories', ['id' => $id]);
    }

    public function countTickets(int $id): int
    {
        return (int) $this->db()->count('tickets', ['category_id' => $id]);
    }

    public function exists(int $id): bool
    {
        return $this->db()->has('ticket_categories', ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['name'] = (string) $row['name'];
        $row['color_code'] = (string) ($row['color_code'] ?: '#6366f1');

        return $row;
    }

    private function normalizeName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new \InvalidArgumentException(__('ticket_category_name_required'));
        }

        if (mb_strlen($trimmed) > 100) {
            throw new \InvalidArgumentException(__('ticket_category_name_too_long'));
        }

        return $trimmed;
    }

    private function normalizeColorCode(string $colorCode): string
    {
        $trimmed = trim($colorCode);

        if ($trimmed === '') {
            return '#6366f1';
        }

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $trimmed)) {
            throw new \InvalidArgumentException(__('ticket_category_color_invalid'));
        }

        return strtolower($trimmed);
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
