<?php

declare(strict_types=1);

namespace App\Services;

final class SortQuery
{
    /**
     * @param array<string, mixed> $queryParams
     * @param list<string> $allowedColumns
     * @param array<string, 'ASC'|'DESC'> $defaultOrder
     *
     * @return array{
     *     sort: ?string,
     *     direction: 'ASC'|'DESC',
     *     order: array<string, 'ASC'|'DESC'>
     * }
     */
    public static function parse(array $queryParams, array $allowedColumns, array $defaultOrder = ['id' => 'DESC']): array
    {
        $allowedColumns = array_values(array_unique(array_map(static fn ($column): string => (string) $column, $allowedColumns)));
        $sort = self::sanitizeColumn((string) ($queryParams['sort'] ?? ''));
        $direction = self::parseDirection((string) ($queryParams['direction'] ?? ''));

        if ($sort === '' || !in_array($sort, $allowedColumns, true)) {
            return [
                'sort' => null,
                'direction' => self::primaryDirection($defaultOrder),
                'order' => self::normalizeOrder($defaultOrder),
            ];
        }

        $order = [$sort => $direction];

        if ($sort !== 'id' && in_array('id', $allowedColumns, true)) {
            $order['id'] = 'DESC';
        }

        return [
            'sort' => $sort,
            'direction' => $direction,
            'order' => $order,
        ];
    }

    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, string> $columnMap API column => SQL/Medoo order key
     * @param array<string, 'ASC'|'DESC'> $defaultOrder API column => direction
     *
     * @return array{
     *     sort: ?string,
     *     direction: 'ASC'|'DESC',
     *     order: array<string, 'ASC'|'DESC'>
     * }
     */
    public static function parseMapped(
        array $queryParams,
        array $columnMap,
        array $defaultOrder = ['created_at' => 'DESC']
    ): array {
        $parsed = self::parse($queryParams, array_keys($columnMap), $defaultOrder);

        if ($parsed['sort'] === null) {
            return [
                ...$parsed,
                'order' => self::mapOrderKeys($defaultOrder, $columnMap),
            ];
        }

        $mappedOrder = [];
        foreach ($parsed['order'] as $column => $direction) {
            $mappedKey = $columnMap[$column] ?? $column;
            $mappedOrder[$mappedKey] = $direction;
        }

        return [
            ...$parsed,
            'order' => $mappedOrder,
        ];
    }

    private static function sanitizeColumn(string $column): string
    {
        $column = trim($column);

        if ($column === '' || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            return '';
        }

        return $column;
    }

    private static function parseDirection(string $direction): string
    {
        return strtolower(trim($direction)) === 'asc' ? 'ASC' : 'DESC';
    }

    /**
     * @param array<string, 'ASC'|'DESC'> $defaultOrder
     */
    private static function primaryDirection(array $defaultOrder): string
    {
        foreach ($defaultOrder as $direction) {
            return strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        }

        return 'DESC';
    }

    /**
     * @param array<string, 'ASC'|'DESC'> $order
     *
     * @return array<string, 'ASC'|'DESC'>
     */
    private static function normalizeOrder(array $order): array
    {
        $normalized = [];

        foreach ($order as $column => $direction) {
            $sanitizedColumn = self::sanitizeColumn((string) $column);

            if ($sanitizedColumn === '') {
                continue;
            }

            $normalized[$sanitizedColumn] = strtoupper((string) $direction) === 'ASC' ? 'ASC' : 'DESC';
        }

        return $normalized !== [] ? $normalized : ['id' => 'DESC'];
    }

    /**
     * @param array<string, 'ASC'|'DESC'> $order
     * @param array<string, string> $columnMap
     *
     * @return array<string, 'ASC'|'DESC'>
     */
    private static function mapOrderKeys(array $order, array $columnMap): array
    {
        $mapped = [];

        foreach (self::normalizeOrder($order) as $column => $direction) {
            $mapped[$columnMap[$column] ?? $column] = $direction;
        }

        return $mapped;
    }
}
