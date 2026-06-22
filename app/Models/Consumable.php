<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use App\Services\ListPagination;
use Medoo\Medoo;

class Consumable
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
        $rows = $this->db()->select('consumables', [
            '[>]locations' => ['location_id' => 'id'],
        ], [
            'consumables.id',
            'consumables.name',
            'consumables.quantity',
            'consumables.min_stock_level',
            'consumables.location_id',
            'consumables.created_at',
            'locations.name(location_name)',
            'locations.building(location_building)',
        ], [
            'ORDER' => [
                'consumables.name' => 'ASC',
            ],
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
    }

    /**
     * @param array<string, string> $filters
     * @param list<array<string, mixed>> $filterDefinitions
     *
     * @return array{
     *     data: list<array<string, mixed>>,
     *     pagination: array{page: int, per_page: int, total: int, total_pages: int}
     * }
     */
    public function findPaginated(int $page = 1, array $filters = [], array $filterDefinitions = []): array
    {
        $page = max(1, $page);
        $perPage = ListPagination::PAGE_SIZE;
        $where = $this->buildFilterWhere($filters, $filterDefinitions);
        $total = (int) $this->db()->count('consumables', $where);
        $selectWhere = $where;
        $selectWhere['ORDER'] = [
            'consumables.name' => 'ASC',
        ];
        $selectWhere['LIMIT'] = [ListPagination::offset($page, $perPage), $perPage];

        $rows = $this->db()->select('consumables', [
            '[>]locations' => ['location_id' => 'id'],
        ], [
            'consumables.id',
            'consumables.name',
            'consumables.quantity',
            'consumables.min_stock_level',
            'consumables.location_id',
            'consumables.created_at',
            'locations.name(location_name)',
            'locations.building(location_building)',
        ], $selectWhere);

        return [
            'data' => array_map(
                fn (array $row): array => $this->normalizeRow($row),
                $rows
            ),
            'pagination' => ListPagination::meta($page, $total, $perPage),
        ];
    }

    /**
     * @param array<string, string> $filters
     * @param list<array<string, mixed>> $filterDefinitions
     *
     * @return array<string, mixed>
     */
    private function buildFilterWhere(array $filters, array $filterDefinitions): array
    {
        if ($filters === [] || $filterDefinitions === []) {
            return [];
        }

        $definitionMap = [];

        foreach ($filterDefinitions as $definition) {
            $name = (string) ($definition['name'] ?? '');

            if ($name !== '') {
                $definitionMap[$name] = $definition;
            }
        }

        $conditions = [];

        foreach ($filters as $name => $value) {
            $trimmedValue = trim($value);

            if ($trimmedValue === '') {
                continue;
            }

            $definition = $definitionMap[$name] ?? null;

            if ($definition === null) {
                continue;
            }

            $virtual = (string) ($definition['virtual'] ?? '');
            $column = isset($definition['column']) ? (string) $definition['column'] : '';
            $match = (string) ($definition['match'] ?? 'partial');

            if ($virtual === 'stock_status') {
                $conditions[] = $this->buildStockStatusCondition($trimmedValue);
                continue;
            }

            if ($column === '') {
                continue;
            }

            if ($match === 'exact') {
                if ($column === 'consumables.location_id') {
                    $locationId = (int) $trimmedValue;

                    if ($locationId <= 0) {
                        continue;
                    }

                    $conditions[$column] = $locationId;
                    continue;
                }

                $conditions[$column] = $trimmedValue;
                continue;
            }

            $conditions[$column . '[~]'] = '%' . $trimmedValue . '%';
        }

        if ($conditions === []) {
            return [];
        }

        return ['AND' => $conditions];
    }

    private function buildStockStatusCondition(string $value): mixed
    {
        return match ($value) {
            'low_stock' => Medoo::raw('(consumables.quantity <= consumables.min_stock_level)'),
            'in_stock' => Medoo::raw('(consumables.quantity > consumables.min_stock_level)'),
            default => Medoo::raw('1 = 1'),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db()->get('consumables', [
            '[>]locations' => ['location_id' => 'id'],
        ], [
            'consumables.id',
            'consumables.name',
            'consumables.quantity',
            'consumables.min_stock_level',
            'consumables.location_id',
            'consumables.created_at',
            'locations.name(location_name)',
            'locations.building(location_building)',
        ], [
            'consumables.id' => $id,
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $name, int $quantity, int $minStockLevel, ?int $locationId): array
    {
        $trimmedName = trim($name);

        if ($trimmedName === '') {
            throw new \InvalidArgumentException(__('consumable_name_required'));
        }

        if ($quantity < 0) {
            throw new \InvalidArgumentException(__('consumable_quantity_invalid'));
        }

        if ($minStockLevel < 0) {
            throw new \InvalidArgumentException(__('consumable_min_stock_invalid'));
        }

        $this->assertLocationExists($locationId);

        $this->db()->insert('consumables', [
            'name' => $trimmedName,
            'quantity' => $quantity,
            'min_stock_level' => $minStockLevel,
            'location_id' => $locationId,
        ]);

        $created = $this->findById((int) $this->db()->id());

        if ($created === null) {
            throw new \RuntimeException(__('consumable_create_error'));
        }

        return $created;
    }

    /**
     * @param array{name?: string, quantity?: int, min_stock_level?: int, location_id?: int|null} $payload
     *
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $payload): ?array
    {
        if ($this->findById($id) === null) {
            return null;
        }

        $update = [];

        if (array_key_exists('name', $payload)) {
            $trimmedName = trim((string) $payload['name']);

            if ($trimmedName === '') {
                throw new \InvalidArgumentException(__('consumable_name_required'));
            }

            $update['name'] = $trimmedName;
        }

        if (array_key_exists('quantity', $payload)) {
            $quantity = (int) $payload['quantity'];

            if ($quantity < 0) {
                throw new \InvalidArgumentException(__('consumable_quantity_invalid'));
            }

            $update['quantity'] = $quantity;
        }

        if (array_key_exists('min_stock_level', $payload)) {
            $minStockLevel = (int) $payload['min_stock_level'];

            if ($minStockLevel < 0) {
                throw new \InvalidArgumentException(__('consumable_min_stock_invalid'));
            }

            $update['min_stock_level'] = $minStockLevel;
        }

        if (array_key_exists('location_id', $payload)) {
            $locationId = $this->normalizeLocationId($payload['location_id']);
            $this->assertLocationExists($locationId);
            $update['location_id'] = $locationId;
        }

        if ($update !== []) {
            $this->db()->update('consumables', $update, ['id' => $id]);
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $this->db()->delete('consumables', ['id' => $id]);

        return !$this->db()->has('consumables', ['id' => $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findLowStock(): array
    {
        $rows = $this->db()->select('consumables', [
            '[>]locations' => ['location_id' => 'id'],
        ], [
            'consumables.id',
            'consumables.name',
            'consumables.quantity',
            'consumables.min_stock_level',
            'consumables.location_id',
            'consumables.created_at',
            'locations.name(location_name)',
            'locations.building(location_building)',
        ], [
            'consumables.quantity[<=]consumables.min_stock_level',
            'ORDER' => [
                'consumables.quantity' => 'ASC',
                'consumables.name' => 'ASC',
            ],
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function checkout(int $id, int $quantity): array
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException(__('consumable_adjust_quantity_invalid'));
        }

        return $this->adjustQuantity($id, -$quantity, __('consumable_checkout_insufficient'));
    }

    /**
     * @return array<string, mixed>
     */
    public function restock(int $id, int $quantity): array
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException(__('consumable_adjust_quantity_invalid'));
        }

        return $this->adjustQuantity($id, $quantity);
    }

    /**
     * @return array<string, mixed>
     */
    private function adjustQuantity(int $id, int $delta, ?string $insufficientMessage = null): array
    {
        $updated = null;

        $this->db()->action(function (Medoo $db) use ($id, $delta, $insufficientMessage, &$updated): void {
            $row = $db->get('consumables', [
                'id',
                'quantity',
            ], [
                'id' => $id,
            ]);

            if (!is_array($row) || $row === []) {
                throw new \RuntimeException(__('consumable_not_found'));
            }

            $currentQuantity = (int) $row['quantity'];
            $nextQuantity = $currentQuantity + $delta;

            if ($nextQuantity < 0) {
                throw new \InvalidArgumentException($insufficientMessage ?? __('consumable_quantity_invalid'));
            }

            $db->update('consumables', [
                'quantity' => $nextQuantity,
            ], [
                'id' => $id,
            ]);
        });

        $updated = $this->findById($id);

        if ($updated === null) {
            throw new \RuntimeException(__('consumable_not_found'));
        }

        return $updated;
    }

    private function assertLocationExists(?int $locationId): void
    {
        if ($locationId === null) {
            return;
        }

        if (!$this->db()->has('locations', ['id' => $locationId])) {
            throw new \InvalidArgumentException(__('consumable_location_not_found'));
        }
    }

    /**
     * @param mixed $locationId
     */
    private function normalizeLocationId(mixed $locationId): ?int
    {
        if ($locationId === null || $locationId === '') {
            return null;
        }

        $normalized = (int) $locationId;

        return $normalized > 0 ? $normalized : null;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['quantity'] = (int) $row['quantity'];
        $row['min_stock_level'] = (int) $row['min_stock_level'];
        $row['location_id'] = isset($row['location_id']) && $row['location_id'] !== null
            ? (int) $row['location_id']
            : null;
        $row['is_low_stock'] = (int) $row['quantity'] <= (int) $row['min_stock_level'];

        $building = trim((string) ($row['location_building'] ?? ''));
        $locationName = trim((string) ($row['location_name'] ?? ''));

        if ($locationName === '') {
            $row['location_label'] = null;
        } elseif ($building === '') {
            $row['location_label'] = $locationName;
        } else {
            $row['location_label'] = $building . ' / ' . $locationName;
        }

        return $row;
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
