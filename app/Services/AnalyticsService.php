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
            'type',
            'count' => Medoo::raw('COUNT(<id>)'),
        ], [
            'GROUP' => 'type',
        ]);

        $breakdown = [];

        foreach ($rows as $row) {
            $count = (int) $row['count'];
            $typeName = trim((string) ($row['type'] ?? ''));

            if ($typeName === '') {
                $typeName = 'Unknown';
            }

            $breakdown[] = [
                'category_id' => 0,
                'category_name' => $typeName,
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
            'assigned_to[!]' => '',
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

    /**
     * @return array{
     *     volume: array{
     *         total: int,
     *         open: int,
     *         closed: int,
     *         pending: int
     *     },
     *     performance: array{
     *         avg_first_response_minutes: float|null,
     *         avg_resolution_minutes: float|null,
     *         avg_first_response_label: string,
     *         avg_resolution_label: string
     *     },
     *     by_category: list<array{
     *         category_id: int|null,
     *         category_name: string,
     *         color_code: string,
     *         count: int,
     *         percentage: float
     *     }>,
     *     staff_performance: list<array{
     *         user_id: int,
     *         user_name: string,
     *         assigned_count: int,
     *         resolved_count: int,
     *         active_load: int
     *     }>
     * }
     */
    public function getHelpDeskReports(): array
    {
        $total = (int) $this->db()->count('tickets');
        $open = (int) $this->db()->count('tickets', ['status' => 'open']);
        $pending = (int) $this->db()->count('tickets', ['status' => 'in_progress']);
        $closed = (int) $this->db()->count('tickets', [
            'status' => ['resolved', 'closed'],
        ]);

        return [
            'volume' => [
                'total' => $total,
                'open' => $open,
                'closed' => $closed,
                'pending' => $pending,
            ],
            'performance' => $this->fetchTicketPerformanceMetrics(),
            'by_category' => $this->fetchTicketCategoryBreakdown($total),
            'staff_performance' => $this->fetchStaffPerformance(),
        ];
    }

    /**
     * @return array{
     *     avg_first_response_minutes: float|null,
     *     avg_resolution_minutes: float|null,
     *     avg_first_response_label: string,
     *     avg_resolution_label: string
     * }
     */
    private function fetchTicketPerformanceMetrics(): array
    {
        $firstResponseMinutes = null;
        $resolutionMinutes = null;

        $firstResponseStatement = $this->db()->query(
            'SELECT AVG(response_minutes) AS avg_minutes
            FROM (
                SELECT TIMESTAMPDIFF(MINUTE, t.created_at, MIN(tc.created_at)) AS response_minutes
                FROM tickets t
                INNER JOIN ticket_comments tc ON tc.ticket_id = t.id
                INNER JOIN users u ON u.id = tc.user_id
                    AND u.role IN (\'admin\', \'super_admin\', \'technician\')
                GROUP BY t.id
            ) responses
            WHERE response_minutes IS NOT NULL AND response_minutes >= 0'
        );

        if ($firstResponseStatement !== false) {
            $row = $firstResponseStatement->fetch();
            $avg = $row['avg_minutes'] ?? null;

            if ($avg !== null) {
                $firstResponseMinutes = round((float) $avg, 1);
            }
        }

        $resolutionStatement = $this->db()->query(
            'SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) AS avg_minutes
            FROM tickets
            WHERE resolved_at IS NOT NULL
              AND status IN (\'resolved\', \'closed\')'
        );

        if ($resolutionStatement !== false) {
            $row = $resolutionStatement->fetch();
            $avg = $row['avg_minutes'] ?? null;

            if ($avg !== null) {
                $resolutionMinutes = round((float) $avg, 1);
            }
        }

        return [
            'avg_first_response_minutes' => $firstResponseMinutes,
            'avg_resolution_minutes' => $resolutionMinutes,
            'avg_first_response_label' => $this->formatDurationLabel($firstResponseMinutes),
            'avg_resolution_label' => $this->formatDurationLabel($resolutionMinutes),
        ];
    }

    /**
     * @return list<array{
     *     category_id: int|null,
     *     category_name: string,
     *     color_code: string,
     *     count: int,
     *     percentage: float
     * }>
     */
    private function fetchTicketCategoryBreakdown(int $total): array
    {
        $rows = $this->db()->select('tickets', [
            '[>]ticket_categories' => ['category_id' => 'id'],
        ], [
            'tickets.category_id',
            'ticket_categories.name(category_name)',
            'ticket_categories.color_code(color_code)',
            'count' => Medoo::raw('COUNT(<tickets.id>)'),
        ], [
            'GROUP' => [
                'tickets.category_id',
                'ticket_categories.name',
                'ticket_categories.color_code',
            ],
        ]);

        $breakdown = [];

        foreach ($rows as $row) {
            $count = (int) $row['count'];
            $categoryId = $row['category_id'] !== null ? (int) $row['category_id'] : null;
            $categoryName = trim((string) ($row['category_name'] ?? ''));

            $breakdown[] = [
                'category_id' => $categoryId,
                'category_name' => $categoryName !== '' ? $categoryName : __('reports_uncategorized'),
                'color_code' => (string) ($row['color_code'] ?? '#94a3b8') ?: '#94a3b8',
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
     * @return list<array{
     *     user_id: int,
     *     user_name: string,
     *     assigned_count: int,
     *     resolved_count: int,
     *     active_load: int
     * }>
     */
    private function fetchStaffPerformance(): array
    {
        $statement = $this->db()->query(
            'SELECT
                u.id AS user_id,
                u.name AS user_name,
                COUNT(t.id) AS assigned_count,
                SUM(CASE WHEN t.status IN (\'resolved\', \'closed\') THEN 1 ELSE 0 END) AS resolved_count,
                SUM(CASE WHEN t.status IN (\'open\', \'in_progress\') THEN 1 ELSE 0 END) AS active_load
            FROM users u
            LEFT JOIN tickets t ON t.assigned_user_id = u.id
            WHERE u.role IN (\'admin\', \'super_admin\', \'technician\')
            GROUP BY u.id, u.name
            ORDER BY assigned_count DESC, u.name ASC'
        );

        $rows = [];

        if ($statement !== false) {
            foreach ($statement->fetchAll() as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $rows[] = [
                    'user_id' => (int) ($row['user_id'] ?? 0),
                    'user_name' => trim((string) ($row['user_name'] ?? '')) ?: __('reports_unknown_staff'),
                    'assigned_count' => (int) ($row['assigned_count'] ?? 0),
                    'resolved_count' => (int) ($row['resolved_count'] ?? 0),
                    'active_load' => (int) ($row['active_load'] ?? 0),
                ];
            }
        }

        return $rows;
    }

    private function formatDurationLabel(?float $minutes): string
    {
        if ($minutes === null) {
            return __('reports_no_data');
        }

        if ($minutes < 60) {
            return (string) (int) round($minutes) . ' ' . __('reports_minutes_short');
        }

        $hours = $minutes / 60;

        if ($hours < 48) {
            return number_format($hours, 1, '.', '') . ' ' . __('reports_hours_short');
        }

        $days = $hours / 24;

        return number_format($days, 1, '.', '') . ' ' . __('reports_days_short');
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
