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
     *     },
     *     help_desk: array{open: int, in_progress: int, critical: int},
     *     licenses: array{
     *         total: int,
     *         expiring_soon: int,
     *         seat_usage: list<array{id: int, name: string, vendor: string, assigned_seats: int, seats: int, usage_percentage: float}>
     *     },
     *     consumables: array{
     *         total: int,
     *         low_stock: int,
     *         low_stock_items: list<array{id: int, name: string, quantity: int, min_stock_level: int, stock_percentage: float}>
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
            'help_desk' => $this->fetchHelpDeskStats(),
            'licenses' => $this->fetchLicenseStats(),
            'consumables' => $this->fetchConsumableStats(),
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
     *     },
     *     help_desk: array{open: int, in_progress: int, critical: int},
     *     licenses: array{
     *         total: int,
     *         expiring_soon: int,
     *         seat_usage: list<array{id: int, name: string, vendor: string, assigned_seats: int, seats: int, usage_percentage: float}>
     *     },
     *     consumables: array{
     *         total: int,
     *         low_stock: int,
     *         low_stock_items: list<array{id: int, name: string, quantity: int, min_stock_level: int, stock_percentage: float}>
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
            'help_desk' => [
                'open' => 0,
                'in_progress' => 0,
                'critical' => 0,
            ],
            'licenses' => [
                'total' => 0,
                'expiring_soon' => 0,
                'seat_usage' => [],
            ],
            'consumables' => [
                'total' => 0,
                'low_stock' => 0,
                'low_stock_items' => [],
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
            'personnel_id[!]' => null,
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

    /**
     * @return array{open: int, in_progress: int, critical: int}
     */
    private function fetchHelpDeskStats(): array
    {
        return [
            'open' => (int) $this->db()->count('tickets', ['status' => 'open']),
            'in_progress' => (int) $this->db()->count('tickets', ['status' => 'in_progress']),
            'critical' => (int) $this->db()->count('tickets', [
                'AND' => [
                    'priority' => 'critical',
                    'status' => ['open', 'in_progress'],
                ],
            ]),
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     expiring_soon: int,
     *     seat_usage: list<array{id: int, name: string, vendor: string, assigned_seats: int, seats: int, usage_percentage: float}>
     * }
     */
    private function fetchLicenseStats(): array
    {
        $total = (int) $this->db()->count('licenses');

        $expiringStatement = $this->db()->query(
            'SELECT COUNT(*) AS total FROM licenses
            WHERE expiration_date IS NOT NULL
              AND expiration_date >= CURDATE()
              AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)'
        );

        $expiringSoon = 0;

        if ($expiringStatement !== false) {
            $row = $expiringStatement->fetch();
            $expiringSoon = (int) ($row['total'] ?? 0);
        }

        $usageStatement = $this->db()->query(
            'SELECT
                l.id,
                l.name,
                l.vendor,
                l.seats,
                COUNT(la.id) AS assigned_seats
            FROM licenses l
            LEFT JOIN license_assignments la ON la.license_id = l.id
            GROUP BY l.id, l.name, l.vendor, l.seats
            ORDER BY (COUNT(la.id) / GREATEST(l.seats, 1)) DESC, l.name ASC
            LIMIT 5'
        );

        $seatUsage = [];

        if ($usageStatement !== false) {
            foreach ($usageStatement->fetchAll() as $row) {
                $seats = max(1, (int) ($row['seats'] ?? 0));
                $assignedSeats = (int) ($row['assigned_seats'] ?? 0);

                $seatUsage[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'vendor' => (string) ($row['vendor'] ?? ''),
                    'assigned_seats' => $assignedSeats,
                    'seats' => $seats,
                    'usage_percentage' => $this->percentage($assignedSeats, $seats),
                ];
            }
        }

        return [
            'total' => $total,
            'expiring_soon' => $expiringSoon,
            'seat_usage' => $seatUsage,
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     low_stock: int,
     *     low_stock_items: list<array{id: int, name: string, quantity: int, min_stock_level: int, stock_percentage: float}>
     * }
     */
    private function fetchConsumableStats(): array
    {
        $total = (int) $this->db()->count('consumables');

        $countStatement = $this->db()->query(
            'SELECT COUNT(*) AS total FROM consumables WHERE quantity <= min_stock_level'
        );
        $lowStock = 0;

        if ($countStatement !== false) {
            $countRow = $countStatement->fetch();
            $lowStock = (int) ($countRow['total'] ?? 0);
        }

        $itemsStatement = $this->db()->query(
            'SELECT id, name, quantity, min_stock_level
            FROM consumables
            WHERE quantity <= min_stock_level
            ORDER BY quantity ASC, name ASC
            LIMIT 5'
        );

        $lowStockItems = [];

        if ($itemsStatement !== false) {
            foreach ($itemsStatement->fetchAll() as $row) {
                $quantity = (int) ($row['quantity'] ?? 0);
                $minStockLevel = max(0, (int) ($row['min_stock_level'] ?? 0));
                $denominator = max(1, $minStockLevel);

                $lowStockItems[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'quantity' => $quantity,
                    'min_stock_level' => $minStockLevel,
                    'stock_percentage' => $this->percentage(min($quantity, $denominator), $denominator),
                ];
            }
        }

        return [
            'total' => $total,
            'low_stock' => $lowStock,
            'low_stock_items' => $lowStockItems,
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
