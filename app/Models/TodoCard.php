<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use DateTimeImmutable;
use Medoo\Medoo;

class TodoCard
{
    public const STATUS_TODO = 'todo';
    public const STATUS_DOING = 'doing';
    public const STATUS_DONE = 'done';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_TICKET = 'ticket';

    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findBoard(bool $archived = false): array
    {
        return $this->mapRows($this->selectRows([
            'todo_cards.archived' => $archived ? 1 : 0,
            'ORDER' => [
                'todo_cards.status' => 'ASC',
                'todo_cards.sort_order' => 'ASC',
                'todo_cards.id' => 'ASC',
            ],
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $rows = $this->selectRows([
            'todo_cards.id' => $id,
            'LIMIT' => 1,
        ]);

        return $rows === [] ? null : $this->normalizeRow($rows[0]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByTicketId(int $ticketId): ?array
    {
        $rows = $this->selectRows([
            'todo_cards.ticket_id' => $ticketId,
            'LIMIT' => 1,
        ]);

        return $rows === [] ? null : $this->normalizeRow($rows[0]);
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    public function create(array $fields, ?int $createdByUserId): array
    {
        $status = $this->normalizeStatus((string) ($fields['status'] ?? self::STATUS_TODO));
        $assignedUserId = $this->normalizeOptionalUserId($fields['assigned_user_id'] ?? null);
        $this->assertOperationalUser($assignedUserId);

        $startDate = $this->normalizeDate($fields['start_date'] ?? null);
        $dueDate = $this->normalizeDate($fields['due_date'] ?? null);
        $this->assertDateRange($startDate, $dueDate);

        $this->db()->insert('todo_cards', [
            'title' => $this->normalizeTitle((string) ($fields['title'] ?? '')),
            'description' => $this->normalizeDescription((string) ($fields['description'] ?? '')),
            'status' => $status,
            'priority' => $this->normalizePriority((string) ($fields['priority'] ?? Ticket::PRIORITY_MEDIUM)),
            'assigned_user_id' => $assignedUserId,
            'created_by_user_id' => $createdByUserId,
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'labels' => $this->encodeJson($this->normalizeLabels($fields['labels'] ?? [])),
            'checklist' => $this->encodeJson($this->normalizeChecklist($fields['checklist'] ?? [])),
            'sort_order' => $this->nextSortOrder($status),
            'archived' => 0,
            'source' => self::SOURCE_MANUAL,
        ]);

        $created = $this->findById((int) $this->db()->id());

        if ($created === null) {
            throw new \RuntimeException(__('todo_create_error'));
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $fields): ?array
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            return null;
        }

        $update = [];
        $previousStatus = (string) $existing['status'];

        if (array_key_exists('title', $fields)) {
            $update['title'] = $this->normalizeTitle((string) $fields['title']);
        }

        if (array_key_exists('description', $fields)) {
            $update['description'] = $this->normalizeDescription((string) $fields['description']);
        }

        if (array_key_exists('priority', $fields)) {
            $update['priority'] = $this->normalizePriority((string) $fields['priority']);
        }

        if (array_key_exists('status', $fields)) {
            $status = $this->normalizeStatus((string) $fields['status']);
            $update['status'] = $status;

            if ($status !== $previousStatus) {
                $update['sort_order'] = $this->nextSortOrder($status);
            }
        }

        if (array_key_exists('assigned_user_id', $fields)) {
            $assignedUserId = $this->normalizeOptionalUserId($fields['assigned_user_id']);
            $this->assertOperationalUser($assignedUserId);
            $update['assigned_user_id'] = $assignedUserId;
        }

        if (array_key_exists('start_date', $fields)) {
            $update['start_date'] = $this->normalizeDate($fields['start_date']);
        }

        if (array_key_exists('due_date', $fields)) {
            $update['due_date'] = $this->normalizeDate($fields['due_date']);
        }

        $startDate = array_key_exists('start_date', $update)
            ? $update['start_date']
            : ($existing['start_date'] ?? null);
        $dueDate = array_key_exists('due_date', $update)
            ? $update['due_date']
            : ($existing['due_date'] ?? null);
        $this->assertDateRange(
            is_string($startDate) ? $startDate : null,
            is_string($dueDate) ? $dueDate : null
        );

        if (array_key_exists('labels', $fields)) {
            $update['labels'] = $this->encodeJson($this->normalizeLabels($fields['labels']));
        }

        if (array_key_exists('checklist', $fields)) {
            $update['checklist'] = $this->encodeJson($this->normalizeChecklist($fields['checklist']));
        }

        if (array_key_exists('archived', $fields)) {
            $update['archived'] = filter_var($fields['archived'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if ($update !== []) {
            $this->db()->update('todo_cards', $update, ['id' => $id]);
        }

        if (isset($update['status']) && $update['status'] !== $previousStatus) {
            $this->resequenceColumn($previousStatus);
        }

        return $this->findById($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function move(int $id, string $status, int $position): ?array
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            return null;
        }

        $previousStatus = (string) $existing['status'];
        $targetStatus = $this->normalizeStatus($status);
        $this->db()->update('todo_cards', ['status' => $targetStatus], ['id' => $id]);
        $this->resequenceColumn($targetStatus, $id, max(0, $position));

        if ($previousStatus !== $targetStatus) {
            $this->resequenceColumn($previousStatus);
        }

        return $this->findById($id);
    }

    /**
     * Create or update the one card linked to a helpdesk ticket.
     *
     * @param array<string, mixed> $ticket
     *
     * @return array<string, mixed>
     */
    public function syncFromTicket(array $ticket): array
    {
        $ticketId = (int) ($ticket['id'] ?? 0);

        if ($ticketId <= 0) {
            throw new \InvalidArgumentException(__('ticket_invalid_id'));
        }

        $existing = $this->findByTicketId($ticketId);
        $status = $this->statusFromTicket((string) ($ticket['status'] ?? Ticket::STATUS_OPEN));
        $payload = [
            'title' => $this->normalizeTitle((string) ($ticket['subject'] ?? '')),
            'description' => $this->normalizeDescription((string) ($ticket['description'] ?? '')),
            'status' => $status,
            'priority' => $this->normalizePriority((string) ($ticket['priority'] ?? Ticket::PRIORITY_MEDIUM)),
            'assigned_user_id' => $this->normalizeOptionalUserId($ticket['assigned_user_id'] ?? null),
        ];

        if ($existing === null) {
            $this->db()->insert('todo_cards', [
                'ticket_id' => $ticketId,
                ...$payload,
                'created_by_user_id' => $this->normalizeOptionalUserId($ticket['created_by_user_id'] ?? null),
                'labels' => $this->encodeJson([]),
                'checklist' => $this->encodeJson([]),
                'sort_order' => $this->nextSortOrder($status),
                'archived' => 0,
                'source' => self::SOURCE_TICKET,
            ]);

            $created = $this->findById((int) $this->db()->id());

            if ($created === null) {
                throw new \RuntimeException(__('todo_create_error'));
            }

            return $created;
        }

        $previousStatus = (string) $existing['status'];
        $this->db()->update('todo_cards', $payload, ['id' => (int) $existing['id']]);

        if ($previousStatus !== $status) {
            $this->resequenceColumn($previousStatus);
            $this->resequenceColumn($status, (int) $existing['id'], $this->nextSortOrder($status));
        }

        return $this->findById((int) $existing['id']) ?? $existing;
    }

    public function ticketStatusFromCard(string $status): string
    {
        return match ($this->normalizeStatus($status)) {
            self::STATUS_DOING => Ticket::STATUS_IN_PROGRESS,
            self::STATUS_DONE => Ticket::STATUS_RESOLVED,
            default => Ticket::STATUS_OPEN,
        };
    }

    /**
     * @param array<string, mixed> $conditions
     *
     * @return list<array<string, mixed>>
     */
    private function selectRows(array $conditions): array
    {
        return $this->db()->select('todo_cards', [
            '[>]tickets' => ['ticket_id' => 'id'],
            '[>]users(assigned)' => ['assigned_user_id' => 'id'],
            '[>]users(creator)' => ['created_by_user_id' => 'id'],
        ], [
            'todo_cards.id',
            'todo_cards.ticket_id',
            'todo_cards.title',
            'todo_cards.description',
            'todo_cards.status',
            'todo_cards.priority',
            'todo_cards.assigned_user_id',
            'todo_cards.created_by_user_id',
            'todo_cards.start_date',
            'todo_cards.due_date',
            'todo_cards.labels',
            'todo_cards.checklist',
            'todo_cards.sort_order',
            'todo_cards.archived',
            'todo_cards.source',
            'todo_cards.created_at',
            'todo_cards.updated_at',
            'tickets.ticket_number',
            'tickets.status(ticket_status)',
            'assigned.name(assigned_user_name)',
            'assigned.email(assigned_user_email)',
            'creator.name(created_by_user_name)',
        ], $conditions);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function mapRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['ticket_id'] = $row['ticket_id'] !== null ? (int) $row['ticket_id'] : null;
        $row['assigned_user_id'] = $row['assigned_user_id'] !== null ? (int) $row['assigned_user_id'] : null;
        $row['created_by_user_id'] = $row['created_by_user_id'] !== null ? (int) $row['created_by_user_id'] : null;
        $row['sort_order'] = (int) $row['sort_order'];
        $row['archived'] = (bool) $row['archived'];
        $row['labels'] = $this->decodeJsonArray($row['labels'] ?? null);
        $row['checklist'] = $this->decodeJsonArray($row['checklist'] ?? null);

        return $row;
    }

    private function resequenceColumn(string $status, ?int $movingId = null, ?int $targetPosition = null): void
    {
        $conditions = [
            'status' => $this->normalizeStatus($status),
            'archived' => 0,
            'ORDER' => ['sort_order' => 'ASC', 'id' => 'ASC'],
        ];

        if ($movingId !== null) {
            $conditions['id[!]'] = $movingId;
        }

        $ids = array_map(
            static fn (array $row): int => (int) $row['id'],
            $this->db()->select('todo_cards', ['id'], $conditions)
        );

        if ($movingId !== null) {
            $position = min(max(0, (int) $targetPosition), count($ids));
            array_splice($ids, $position, 0, [$movingId]);
        }

        foreach ($ids as $index => $cardId) {
            $this->db()->update('todo_cards', ['sort_order' => $index], ['id' => $cardId]);
        }
    }

    private function nextSortOrder(string $status): int
    {
        $maximum = $this->db()->max('todo_cards', 'sort_order', [
            'status' => $this->normalizeStatus($status),
            'archived' => 0,
        ]);

        return is_numeric($maximum) ? ((int) $maximum + 1) : 0;
    }

    private function normalizeTitle(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            throw new \InvalidArgumentException(__('todo_title_required'));
        }

        if (mb_strlen($title) > 255) {
            throw new \InvalidArgumentException(__('todo_title_too_long'));
        }

        return $title;
    }

    private function normalizeDescription(string $description): string
    {
        $description = trim($description);

        if (mb_strlen($description) > 20000) {
            throw new \InvalidArgumentException(__('todo_description_too_long'));
        }

        return $description;
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            self::STATUS_DOING => self::STATUS_DOING,
            self::STATUS_DONE => self::STATUS_DONE,
            default => self::STATUS_TODO,
        };
    }

    private function statusFromTicket(string $status): string
    {
        return match (strtolower(trim($status))) {
            Ticket::STATUS_IN_PROGRESS => self::STATUS_DOING,
            Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED => self::STATUS_DONE,
            default => self::STATUS_TODO,
        };
    }

    private function normalizePriority(string $priority): string
    {
        return match (strtolower(trim($priority))) {
            Ticket::PRIORITY_LOW => Ticket::PRIORITY_LOW,
            Ticket::PRIORITY_HIGH => Ticket::PRIORITY_HIGH,
            Ticket::PRIORITY_CRITICAL => Ticket::PRIORITY_CRITICAL,
            default => Ticket::PRIORITY_MEDIUM,
        };
    }

    private function normalizeOptionalUserId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function assertOperationalUser(?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        $role = $this->db()->get('users', 'role', ['id' => $userId]);

        if (!is_string($role) || User::normalizeRoleStatic($role) !== User::ROLE_ADMIN) {
            throw new \InvalidArgumentException(__('todo_assignee_invalid'));
        }
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException(__('todo_date_invalid'));
        }

        return $value;
    }

    private function assertDateRange(?string $startDate, ?string $dueDate): void
    {
        if ($startDate !== null && $dueDate !== null && $startDate > $dueDate) {
            throw new \InvalidArgumentException(__('todo_date_range_invalid'));
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeLabels(mixed $labels): array
    {
        if (is_string($labels)) {
            $labels = array_map('trim', explode(',', $labels));
        }

        if (!is_array($labels)) {
            return [];
        }

        $normalized = [];

        foreach ($labels as $label) {
            $label = trim((string) $label);

            if ($label === '') {
                continue;
            }

            if (mb_strlen($label) > 32) {
                throw new \InvalidArgumentException(__('todo_label_too_long'));
            }

            $normalized[mb_strtolower($label, 'UTF-8')] = $label;

            if (count($normalized) >= 8) {
                break;
            }
        }

        return array_values($normalized);
    }

    /**
     * @return list<array{id: string, text: string, done: bool}>
     */
    private function normalizeChecklist(mixed $checklist): array
    {
        if (!is_array($checklist)) {
            return [];
        }

        $normalized = [];

        foreach ($checklist as $item) {
            if (!is_array($item)) {
                continue;
            }

            $text = trim((string) ($item['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            if (mb_strlen($text) > 255) {
                throw new \InvalidArgumentException(__('todo_checklist_item_too_long'));
            }

            $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($item['id'] ?? '')) ?: bin2hex(random_bytes(6));
            $normalized[] = [
                'id' => $id,
                'text' => $text,
                'done' => filter_var($item['done'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];

            if (count($normalized) >= 50) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @return list<mixed>
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @param list<mixed> $value
     */
    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
