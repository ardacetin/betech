<?php

declare(strict_types=1);

namespace App\Services;

use Medoo\Medoo;

class AnalyticsService
{
    private const KNOWN_STATUSES = ['ready', 'deployed', 'storage', 'broken'];

    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    /**
     * @return array{
     *     total: int,
     *     summary_cards: array{total: int, deployed: int, in_storage: int, broken: int},
     *     by_status: list<array{status: string, count: int, percentage: float}>,
     *     by_category: list<array{category_id: int, category_name: string, count: int, percentage: float}>,
     *     assignment: array{
     *         assigned: int,
     *         unassigned: int,
     *         assigned_percentage: float,
     *         unassigned_percentage: float
     *     }
     * }
     */
    public function getDashboardStats(): array
    {
        $total = $this->db()->count('assets');
        $byStatus = $this->fetchStatusBreakdown($total);
        $byCategory = $this->fetchCategoryBreakdown($total);
        $assignment = $this->fetchAssignmentStats($total);

        return [
            'total' => $total,
            'summary_cards' => $this->buildSummaryCards($byStatus, $total),
            'by_status' => $byStatus,
            'by_category' => $byCategory,
            'assignment' => $assignment,
        ];
    }

    /**
     * @return list<array{status: string, count: int, percentage: float}>
     */
    private function fetchStatusBreakdown(int $total): array
    {
        $rows = $this->db()->select('assets', [
            'status',
            'count' => Medoo::raw('COUNT(<id>)'),
        ], [
            'GROUP' => 'status',
        ]);

        $countsByStatus = [];

        foreach ($rows as $row) {
            $countsByStatus[(string) $row['status']] = (int) $row['count'];
        }

        $statuses = array_unique(array_merge(self::KNOWN_STATUSES, array_keys($countsByStatus)));
        $breakdown = [];

        foreach ($statuses as $status) {
            $count = $countsByStatus[$status] ?? 0;

            if ($count === 0 && !in_array($status, self::KNOWN_STATUSES, true)) {
                continue;
            }

            $breakdown[] = [
                'status' => $status,
                'count' => $count,
                'percentage' => $this->percentage($count, $total),
            ];
        }

        usort(
            $breakdown,
            static fn (array $left, array $right): int => $right['count'] <=> $left['count']
        );

        return $breakdown;
    }

    /**
     * @return array{
     *     total: int,
     *     summary_cards: array{total: int, deployed: int, in_storage: int, broken: int},
     *     by_status: list<array{status: string, count: int, percentage: float}>,
     *     by_category: list<array{category_id: int, category_name: string, count: int, percentage: float}>,
     *     assignment: array{
     *         assigned: int,
     *         unassigned: int,
     *         assigned_percentage: float,
     *         unassigned_percentage: float
     *     }
     * }
     */
    public function getEmptyDashboardStats(): array
    {
        $byStatus = [];

        foreach (self::KNOWN_STATUSES as $status) {
            $byStatus[] = [
                'status' => $status,
                'count' => 0,
                'percentage' => 0.0,
            ];
        }

        return [
            'total' => 0,
            'summary_cards' => [
                'total' => 0,
                'deployed' => 0,
                'in_storage' => 0,
                'broken' => 0,
            ],
            'by_status' => $byStatus,
            'by_category' => [],
            'assignment' => [
                'assigned' => 0,
                'unassigned' => 0,
                'assigned_percentage' => 0.0,
                'unassigned_percentage' => 0.0,
            ],
        ];
    }

    /**
     * @return list<array{category_id: int, category_name: string, count: int, percentage: float}>
     */
    private function fetchCategoryBreakdown(int $total): array
    {
        $rows = $this->db()->select('assets', [
            '[>]categories' => ['category_id' => 'id'],
        ], [
            'assets.category_id',
            'categories.name(category_name)',
            'count' => Medoo::raw('COUNT(<assets.id>)'),
        ], [
            'GROUP' => [
                'assets.category_id',
                'categories.name',
            ],
        ]);

        $breakdown = [];

        foreach ($rows as $row) {
            $count = (int) $row['count'];

            $breakdown[] = [
                'category_id' => (int) $row['category_id'],
                'category_name' => (string) ($row['category_name'] ?? 'Unknown'),
                'count' => $count,
                'percentage' => $this->percentage($count, $total),
            ];
        }

        usort(
            $breakdown,
            static fn (array $left, array $right): int => $right['count'] <=> $left['count']
        );

        return $breakdown;
    }

    /**
     * @return array{
     *     assigned: int,
     *     unassigned: int,
     *     assigned_percentage: float,
     *     unassigned_percentage: float
     * }
     */
    private function fetchAssignmentStats(int $total): array
    {
        $assigned = (int) $this->db()->count('assets', [
            'user_id[!]' => null,
        ]);
        $unassigned = max(0, $total - $assigned);

        return [
            'assigned' => $assigned,
            'unassigned' => $unassigned,
            'assigned_percentage' => $this->percentage($assigned, $total),
            'unassigned_percentage' => $this->percentage($unassigned, $total),
        ];
    }

    /**
     * @param list<array{status: string, count: int, percentage: float}> $byStatus
     *
     * @return array{total: int, deployed: int, in_storage: int, broken: int}
     */
    private function buildSummaryCards(array $byStatus, int $total): array
    {
        $countsByStatus = [];

        foreach ($byStatus as $row) {
            $countsByStatus[$row['status']] = $row['count'];
        }

        return [
            'total' => $total,
            'deployed' => $countsByStatus['deployed'] ?? 0,
            'in_storage' => ($countsByStatus['ready'] ?? 0) + ($countsByStatus['storage'] ?? 0),
            'broken' => $countsByStatus['broken'] ?? 0,
        ];
    }

    private function percentage(int $part, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, 1);
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
