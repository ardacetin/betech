<?php

declare(strict_types=1);

namespace App\Services;

final class ListPagination
{
    public const PAGE_SIZE = 50;

    /**
     * @param array<string, mixed> $queryParams
     */
    public static function parsePage(array $queryParams): int
    {
        return max(1, (int) ($queryParams['page'] ?? 1));
    }

    public static function offset(int $page, int $perPage = self::PAGE_SIZE): int
    {
        return (max(1, $page) - 1) * max(1, $perPage);
    }

    /**
     * @return array{page: int, per_page: int, total: int, total_pages: int}
     */
    public static function meta(int $page, int $total, int $perPage = self::PAGE_SIZE): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }
}
