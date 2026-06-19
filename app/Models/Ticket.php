<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class Ticket
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(?string $status = null, ?string $priority = null): array
    {
        $conditions = [];

        if ($status !== null && $status !== '') {
            $conditions['tickets.status'] = $this->normalizeStatus($status);
        }

        if ($priority !== null && $priority !== '') {
            $conditions['tickets.priority'] = $this->normalizePriority($priority);
        }

        return $this->mapRows($this->selectRows($conditions));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id, bool $withComments = false): ?array
    {
        $rows = $this->selectRows(['tickets.id' => $id], 1);

        if ($rows === []) {
            return null;
        }

        $ticket = $this->normalizeRow($rows[0]);

        if ($withComments) {
            $ticket['comments'] = $this->findCommentsByTicketId($id);
        }

        return $ticket;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findCommentsByTicketId(int $ticketId): array
    {
        $rows = $this->db()->select('ticket_comments', [
            'id',
            'ticket_id',
            'user_id',
            'author_name',
            'body',
            'created_at',
        ], [
            'ticket_id' => $ticketId,
            'ORDER' => ['created_at' => 'ASC', 'id' => 'ASC'],
        ]);

        return array_map(
            static function (array $row): array {
                $row['id'] = (int) $row['id'];
                $row['ticket_id'] = (int) $row['ticket_id'];
                $row['user_id'] = $row['user_id'] !== null ? (int) $row['user_id'] : null;

                return $row;
            },
            $rows
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function create(
        string $subject,
        string $description,
        int $personnelId,
        ?int $assetId,
        string $priority,
        ?int $createdByUserId
    ): array {
        $this->assertPersonnelExists($personnelId);
        $this->assertAssetExists($assetId);

        $payload = [
            'ticket_number' => $this->generateTicketNumber(),
            'subject' => $this->normalizeSubject($subject),
            'description' => $this->normalizeDescription($description),
            'personnel_id' => $personnelId,
            'asset_id' => $assetId,
            'status' => self::STATUS_OPEN,
            'priority' => $this->normalizePriority($priority),
            'created_by_user_id' => $createdByUserId,
        ];

        $this->db()->insert('tickets', $payload);
        $created = $this->findById((int) $this->db()->id());

        if ($created === null) {
            throw new \RuntimeException(__('ticket_create_error'));
        }

        return $created;
    }

    /**
     * @param array{
     *     subject?: string,
     *     description?: string,
     *     personnel_id?: int,
     *     asset_id?: int|null,
     *     status?: string,
     *     priority?: string,
     *     assigned_user_id?: int|null
     * } $fields
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

        if (array_key_exists('subject', $fields)) {
            $update['subject'] = $this->normalizeSubject((string) $fields['subject']);
        }

        if (array_key_exists('description', $fields)) {
            $update['description'] = $this->normalizeDescription((string) $fields['description']);
        }

        if (array_key_exists('personnel_id', $fields)) {
            $personnelId = (int) $fields['personnel_id'];
            $this->assertPersonnelExists($personnelId);
            $update['personnel_id'] = $personnelId;
        }

        if (array_key_exists('asset_id', $fields)) {
            $assetId = $this->normalizeOptionalAssetId($fields['asset_id']);
            $this->assertAssetExists($assetId);
            $update['asset_id'] = $assetId;
        }

        if (array_key_exists('status', $fields)) {
            $status = $this->normalizeStatus((string) $fields['status']);
            $update['status'] = $status;

            if (in_array($status, [self::STATUS_RESOLVED, self::STATUS_CLOSED], true)) {
                $update['resolved_at'] = date('Y-m-d H:i:s');
            } elseif ($status === self::STATUS_OPEN || $status === self::STATUS_IN_PROGRESS) {
                $update['resolved_at'] = null;
            }
        }

        if (array_key_exists('priority', $fields)) {
            $update['priority'] = $this->normalizePriority((string) $fields['priority']);
        }

        if (array_key_exists('assigned_user_id', $fields)) {
            $assignedUserId = $this->normalizeOptionalUserId($fields['assigned_user_id']);
            $this->assertUserExists($assignedUserId);
            $update['assigned_user_id'] = $assignedUserId;
        }

        if ($update === []) {
            return $existing;
        }

        $this->db()->update('tickets', $update, ['id' => $id]);

        return $this->findById($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function addComment(int $ticketId, string $body, ?int $userId, string $authorName): array
    {
        if ($this->findById($ticketId) === null) {
            throw new \InvalidArgumentException(__('ticket_not_found'));
        }

        $trimmedBody = trim($body);
        $trimmedAuthor = trim($authorName);

        if ($trimmedBody === '') {
            throw new \InvalidArgumentException(__('ticket_comment_required'));
        }

        if ($trimmedAuthor === '') {
            throw new \InvalidArgumentException(__('ticket_comment_author_required'));
        }

        $this->db()->insert('ticket_comments', [
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'author_name' => $trimmedAuthor,
            'body' => $trimmedBody,
        ]);

        $commentId = (int) $this->db()->id();
        $comment = $this->db()->get('ticket_comments', [
            'id',
            'ticket_id',
            'user_id',
            'author_name',
            'body',
            'created_at',
        ], ['id' => $commentId]);

        if (!is_array($comment) || $comment === []) {
            throw new \RuntimeException(__('ticket_comment_create_error'));
        }

        $comment['id'] = (int) $comment['id'];
        $comment['ticket_id'] = (int) $comment['ticket_id'];
        $comment['user_id'] = $comment['user_id'] !== null ? (int) $comment['user_id'] : null;

        return $comment;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllByPersonnelId(int $personnelId, ?string $status = null, ?string $priority = null): array
    {
        $conditions = [
            'tickets.personnel_id' => $personnelId,
        ];

        if ($status !== null && $status !== '') {
            $conditions['tickets.status'] = $this->normalizeStatus($status);
        }

        if ($priority !== null && $priority !== '') {
            $conditions['tickets.priority'] = $this->normalizePriority($priority);
        }

        return $this->mapRows($this->selectRows($conditions));
    }

    public function belongsToPersonnel(int $ticketId, int $personnelId): bool
    {
        return $this->db()->has('tickets', [
            'id' => $ticketId,
            'personnel_id' => $personnelId,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdForPersonnel(int $id, int $personnelId, bool $withComments = false): ?array
    {
        $rows = $this->selectRows([
            'tickets.id' => $id,
            'tickets.personnel_id' => $personnelId,
        ], 1);

        if ($rows === []) {
            return null;
        }

        $ticket = $this->normalizeRow($rows[0]);

        if ($withComments) {
            $ticket['comments'] = $this->findCommentsByTicketId($id);
        }

        return $ticket;
    }

    public function delete(int $id): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $this->db()->delete('tickets', ['id' => $id]);

        return !$this->db()->has('tickets', ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $conditions
     *
     * @return list<array<string, mixed>>
     */
    private function selectRows(array $conditions = [], ?int $limit = null): array
    {
        $options = [
            'ORDER' => [
                'tickets.created_at' => 'DESC',
                'tickets.id' => 'DESC',
            ],
        ];

        if ($conditions !== []) {
            $options = [...$conditions, ...$options];
        }

        if ($limit !== null) {
            $options['LIMIT'] = $limit;
        }

        return $this->db()->select('tickets', [
            '[>]personnel' => ['personnel_id' => 'id'],
            '[>]assets' => ['asset_id' => 'id'],
            '[>]users(assigned)' => ['assigned_user_id' => 'id'],
            '[>]users(creator)' => ['created_by_user_id' => 'id'],
        ], [
            'tickets.id',
            'tickets.ticket_number',
            'tickets.subject',
            'tickets.description',
            'tickets.personnel_id',
            'tickets.asset_id',
            'tickets.status',
            'tickets.priority',
            'tickets.assigned_user_id',
            'tickets.created_by_user_id',
            'tickets.resolved_at',
            'tickets.created_at',
            'tickets.updated_at',
            'personnel.name(personnel_name)',
            'personnel.email(personnel_email)',
            'personnel.department(personnel_department)',
            'assets.asset_tag',
            'assets.name(asset_name)',
            'assigned.name(assigned_user_name)',
            'creator.name(created_by_user_name)',
        ], $options);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function mapRows(array $rows): array
    {
        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['personnel_id'] = (int) $row['personnel_id'];
        $row['asset_id'] = $row['asset_id'] !== null ? (int) $row['asset_id'] : null;
        $row['assigned_user_id'] = $row['assigned_user_id'] !== null ? (int) $row['assigned_user_id'] : null;
        $row['created_by_user_id'] = $row['created_by_user_id'] !== null ? (int) $row['created_by_user_id'] : null;
        $row['status'] = (string) $row['status'];
        $row['priority'] = (string) $row['priority'];
        $row['is_open'] = !in_array($row['status'], [self::STATUS_RESOLVED, self::STATUS_CLOSED], true);

        if ($row['asset_id'] === null) {
            $row['asset_label'] = null;
        } else {
            $tag = trim((string) ($row['asset_tag'] ?? ''));
            $name = trim((string) ($row['asset_name'] ?? ''));
            $row['asset_label'] = $tag !== '' && $name !== ''
                ? $tag . ' — ' . $name
                : ($tag !== '' ? $tag : $name);
        }

        return $row;
    }

    private function generateTicketNumber(): string
    {
        $year = date('Y');
        $prefix = 'HD-' . $year . '-';

        $lastNumber = $this->db()->get('tickets', 'ticket_number', [
            'ticket_number[~]' => $prefix . '%',
            'ORDER' => ['id' => 'DESC'],
        ]);

        $sequence = 1;

        if (is_string($lastNumber) && $lastNumber !== '') {
            $parts = explode('-', $lastNumber);
            $lastSequence = (int) end($parts);

            if ($lastSequence > 0) {
                $sequence = $lastSequence + 1;
            }
        }

        return $prefix . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    private function normalizeSubject(string $subject): string
    {
        $trimmed = trim($subject);

        if ($trimmed === '') {
            throw new \InvalidArgumentException(__('ticket_subject_required'));
        }

        if (mb_strlen($trimmed) > 255) {
            throw new \InvalidArgumentException(__('ticket_subject_too_long'));
        }

        return $trimmed;
    }

    private function normalizeDescription(string $description): string
    {
        $trimmed = trim($description);

        if ($trimmed === '') {
            throw new \InvalidArgumentException(__('ticket_description_required'));
        }

        return $trimmed;
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            self::STATUS_IN_PROGRESS => self::STATUS_IN_PROGRESS,
            self::STATUS_RESOLVED => self::STATUS_RESOLVED,
            self::STATUS_CLOSED => self::STATUS_CLOSED,
            default => self::STATUS_OPEN,
        };
    }

    private function normalizePriority(string $priority): string
    {
        return match (strtolower(trim($priority))) {
            self::PRIORITY_LOW => self::PRIORITY_LOW,
            self::PRIORITY_HIGH => self::PRIORITY_HIGH,
            self::PRIORITY_CRITICAL => self::PRIORITY_CRITICAL,
            default => self::PRIORITY_MEDIUM,
        };
    }

    /**
     * @param mixed $value
     */
    private function normalizeOptionalAssetId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $assetId = (int) $value;

        return $assetId > 0 ? $assetId : null;
    }

    /**
     * @param mixed $value
     */
    private function normalizeOptionalUserId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $userId = (int) $value;

        return $userId > 0 ? $userId : null;
    }

    private function assertPersonnelExists(int $personnelId): void
    {
        if ($personnelId <= 0) {
            throw new \InvalidArgumentException(__('ticket_personnel_required'));
        }

        if (!$this->db()->has('personnel', ['id' => $personnelId])) {
            throw new \InvalidArgumentException(__('ticket_personnel_not_found'));
        }
    }

    private function assertAssetExists(?int $assetId): void
    {
        if ($assetId === null) {
            return;
        }

        if (!$this->db()->has('assets', ['id' => $assetId])) {
            throw new \InvalidArgumentException(__('ticket_asset_not_found'));
        }
    }

    private function assertUserExists(?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        if (!$this->db()->has('users', ['id' => $userId])) {
            throw new \InvalidArgumentException(__('ticket_assignee_not_found'));
        }
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
