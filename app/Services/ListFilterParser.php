<?php

declare(strict_types=1);

namespace App\Services;

final class ListFilterParser
{
    /**
     * @param array<string, mixed> $queryParams
     *
     * @return array<string, string>
     */
    public static function parse(array $queryParams): array
    {
        $rawFilters = $queryParams['filter'] ?? [];

        if (!is_array($rawFilters)) {
            return [];
        }

        $filters = [];

        foreach ($rawFilters as $name => $value) {
            if (!is_string($name)) {
                continue;
            }

            $trimmed = trim((string) $value);

            if ($trimmed !== '') {
                $filters[$name] = $trimmed;
            }
        }

        return $filters;
    }
}
