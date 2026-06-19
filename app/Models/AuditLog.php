<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class AuditLog
{
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_LOGIN = 'login';
    public const ACTION_ASSIGNED = 'assigned';
    public const ACTION_RETURNED = 'returned';
    public const ACTION_TRANSFERRED = 'transferred';

    public const ENTITY_ASSET = 'asset';
    public const ENTITY_TICKET = 'ticket';
    public const ENTITY_CATEGORY = 'category';
    public const ENTITY_SETTING = 'setting';
    public const ENTITY_USER = 'user';

    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function create(
        ?int $userId,
        string $actionType,
        string $entityType,
        ?int $entityId,
        ?array $oldValues,
        ?array $newValues,
        ?string $ipAddress
    ): void {
        $this->db()->insert('audit_logs', [
            'user_id' => $userId !== null && $userId > 0 ? $userId : null,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId !== null && $entityId > 0 ? $entityId : null,
            'old_values' => $oldValues !== null ? json_encode($oldValues, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'new_values' => $newValues !== null ? json_encode($newValues, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * @return array{data: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function findFiltered(
        ?int $userId = null,
        ?string $actionType = null,
        ?string $entityType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $page = 1,
        int $perPage = 50
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $conditions = [];

        if ($userId !== null && $userId > 0) {
            $conditions['audit_logs.user_id'] = $userId;
        }

        if ($actionType !== null && $actionType !== '' && $actionType !== 'all') {
            $conditions['audit_logs.action_type'] = $actionType;
        }

        if ($entityType !== null && $entityType !== '' && $entityType !== 'all') {
            $conditions['audit_logs.entity_type'] = $entityType;
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $conditions['audit_logs.created_at[>=]'] = $this->normalizeDateBoundary($dateFrom, true);
        }

        if ($dateTo !== null && $dateTo !== '') {
            $conditions['audit_logs.created_at[<=]'] = $this->normalizeDateBoundary($dateTo, false);
        }

        $total = (int) $this->db()->count('audit_logs', $conditions);

        $rows = $this->db()->select('audit_logs', [
            '[>]users' => ['user_id' => 'id'],
        ], [
            'audit_logs.id',
            'audit_logs.user_id',
            'audit_logs.action_type',
            'audit_logs.entity_type',
            'audit_logs.entity_id',
            'audit_logs.old_values',
            'audit_logs.new_values',
            'audit_logs.ip_address',
            'audit_logs.created_at',
            'users.name(user_name)',
            'users.email(user_email)',
        ], array_merge($conditions, [
            'ORDER' => ['audit_logs.created_at' => 'DESC', 'audit_logs.id' => 'DESC'],
            'LIMIT' => [$offset, $perPage],
        ]));

        if (!is_array($rows)) {
            $rows = [];
        }

        return [
            'data' => array_map(fn (array $row): array => $this->normalizeRow($row), $rows),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * Latest audit entries for the live dashboard feed.
     *
     * @return list<array<string, mixed>>
     */
    public function findRecent(int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));

        $rows = $this->db()->select('audit_logs', [
            '[>]users' => ['user_id' => 'id'],
        ], [
            'audit_logs.id',
            'audit_logs.user_id',
            'audit_logs.action_type',
            'audit_logs.entity_type',
            'audit_logs.entity_id',
            'audit_logs.old_values',
            'audit_logs.new_values',
            'audit_logs.ip_address',
            'audit_logs.created_at',
            'users.name(user_name)',
            'users.email(user_email)',
        ], [
            'ORDER' => ['audit_logs.created_at' => 'DESC', 'audit_logs.id' => 'DESC'],
            'LIMIT' => $limit,
        ]);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    /**
     * @return list<array{id: int, name: string, email: string}>
     */
    public function findDistinctUsers(): array
    {
        $rows = $this->db()->select('audit_logs', [
            '[>]users' => ['user_id' => 'id'],
        ], [
            'users.id',
            'users.name',
            'users.email',
        ], [
            'GROUP' => 'audit_logs.user_id',
            'ORDER' => ['users.name' => 'ASC'],
        ]);

        if (!is_array($rows)) {
            return [];
        }

        $users = [];

        foreach ($rows as $row) {
            if (!is_array($row) || ($row['id'] ?? null) === null) {
                continue;
            }

            $users[] = [
                'id' => (int) $row['id'],
                'name' => (string) ($row['name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
            ];
        }

        return $users;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
            'user_name' => (string) ($row['user_name'] ?? ''),
            'user_email' => (string) ($row['user_email'] ?? ''),
            'action_type' => (string) ($row['action_type'] ?? ''),
            'entity_type' => (string) ($row['entity_type'] ?? ''),
            'entity_id' => $row['entity_id'] !== null ? (int) $row['entity_id'] : null,
            'old_values' => $this->decodeJsonColumn($row['old_values'] ?? null),
            'new_values' => $this->decodeJsonColumn($row['new_values'] ?? null),
            'ip_address' => $row['ip_address'] !== null ? (string) $row['ip_address'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonColumn(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeDateBoundary(string $value, bool $isStart): string
    {
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $isStart ? $value . ' 00:00:00' : $value . ' 23:59:59';
        }

        return $value;
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
