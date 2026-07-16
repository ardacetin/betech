<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\AssetRegistry;
use App\Models\AssetsGlobalRegistry;
use App\Services\DatabaseService;
use App\Services\ListPagination;
use App\Services\SortQuery;
use Medoo\Medoo;

class Ticket
{
    /** @var array<string, string> */
    public const SORTABLE_COLUMNS = [
        'created_at' => 'tickets.created_at',
        'subject' => 'tickets.subject',
        'status' => 'tickets.status',
        'ticket_number' => 'tickets.ticket_number',
        'priority' => 'tickets.priority',
        'id' => 'tickets.id',
    ];
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    public const SOURCE_WEB = 'web';
    public const SOURCE_EMAIL = 'email';

    public const EMAIL_DIRECTION_INBOUND = 'inbound';
    public const EMAIL_DIRECTION_OUTBOUND = 'outbound';

    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly AssetsGlobalRegistry $assetsGlobalRegistry,
        private readonly AssetRegistry $assetRegistry,
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
     * @return array{
     *     data: list<array<string, mixed>>,
     *     pagination: array{page: int, per_page: int, total: int, total_pages: int}
     * }
     */
    public function findPaginated(
        ?string $status = null,
        ?string $priority = null,
        int $page = 1,
        ?string $scope = null,
        ?array $order = null
    ): array {
        return $this->paginateList(null, $status, $priority, $page, $scope, $order);
    }

    /**
     * @return array{
     *     data: list<array<string, mixed>>,
     *     pagination: array{page: int, per_page: int, total: int, total_pages: int}
     * }
     */
    public function findPaginatedByPersonnelId(
        int $personnelId,
        ?string $status = null,
        ?string $priority = null,
        int $page = 1,
        ?string $scope = null,
        ?array $order = null
    ): array {
        return $this->paginateList($personnelId, $status, $priority, $page, $scope, $order);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findStaleOpenTickets(int $hours = 48): array
    {
        if ($hours < 1) {
            return [];
        }

        $statement = $this->db()->query(
            'SELECT tickets.id
            FROM tickets
            WHERE tickets.status = :status
              AND tickets.updated_at <= DATE_SUB(NOW(), INTERVAL :hours HOUR)
            ORDER BY tickets.updated_at ASC, tickets.id ASC',
            [
                ':status' => self::STATUS_OPEN,
                ':hours' => $hours,
            ]
        );

        if ($statement === false) {
            return [];
        }

        $ticketIds = [];

        foreach ($statement->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }

            $ticketId = (int) ($row['id'] ?? 0);

            if ($ticketId > 0) {
                $ticketIds[] = $ticketId;
            }
        }

        if ($ticketIds === []) {
            return [];
        }

        return $this->mapRows($this->selectRows([
            'tickets.id' => $ticketIds,
        ]));
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
            $ticket['comments'] = $this->findCommentsByTicketId($id, true);
            $ticket['followers'] = $this->findFollowersByTicketId($id);
        }

        return $ticket;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findCommentsByTicketId(int $ticketId, bool $includeInternal = true): array
    {
        $conditions = [
            'ticket_id' => $ticketId,
            'ORDER' => ['created_at' => 'ASC', 'id' => 'ASC'],
        ];

        if (!$includeInternal) {
            $conditions['is_internal'] = 0;
        }

        $columns = [
            'id',
            'ticket_id',
            'user_id',
            'author_name',
            'body',
            'is_internal',
            'email_message_id',
            'email_in_reply_to',
            'created_at',
        ];

        try {
            $rows = $this->db()->select('ticket_comments', $columns, $conditions);
        } catch (\Throwable) {
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
        }

        if (!is_array($rows)) {
            return [];
        }

        $comments = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $comment = $this->normalizeCommentRow($row);

            if (!$includeInternal && !empty($comment['is_internal'])) {
                continue;
            }

            $comment['attachments'] = $this->findAttachmentsByCommentId((int) $comment['id']);
            $comments[] = $comment;
        }

        return $comments;
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

        $assetTypeSlug = $this->resolveAssetTypeSlug($assetId);

        $payload = [
            'ticket_number' => $this->generateTicketNumber(),
            'subject' => $this->normalizeSubject($subject),
            'description' => $this->normalizeDescription($description),
            'personnel_id' => $personnelId,
            'asset_id' => $assetId,
            'asset_type' => $assetTypeSlug,
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
     *     assigned_user_id?: int|null,
     *     category_id?: int|null
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
            $update['asset_type'] = $this->resolveAssetTypeSlug($assetId);
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

        if (array_key_exists('category_id', $fields)) {
            $categoryId = $this->normalizeOptionalCategoryId($fields['category_id']);
            $this->assertCategoryExists($categoryId);
            $update['category_id'] = $categoryId;
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
    public function addComment(
        int $ticketId,
        string $body,
        ?int $userId,
        string $authorName,
        bool $isInternal = false,
        ?string $emailMessageId = null,
        ?string $emailInReplyTo = null
    ): array {
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

        $payload = [
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'author_name' => $trimmedAuthor,
            'body' => $trimmedBody,
            'is_internal' => $isInternal ? 1 : 0,
            'email_message_id' => $this->normalizeOptionalMessageId($emailMessageId),
            'email_in_reply_to' => $this->normalizeOptionalMessageId($emailInReplyTo),
        ];

        $this->db()->insert('ticket_comments', $payload);

        $commentId = (int) $this->db()->id();
        $comment = $this->findCommentById($commentId);

        if ($comment === null) {
            throw new \RuntimeException(__('ticket_comment_create_error'));
        }

        return $comment;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCommentById(int $commentId): ?array
    {
        try {
            $comment = $this->db()->get('ticket_comments', [
                'id',
                'ticket_id',
                'user_id',
                'author_name',
                'body',
                'is_internal',
                'email_message_id',
                'email_in_reply_to',
                'created_at',
            ], ['id' => $commentId]);
        } catch (\Throwable) {
            $comment = $this->db()->get('ticket_comments', [
                'id',
                'ticket_id',
                'user_id',
                'author_name',
                'body',
                'created_at',
            ], ['id' => $commentId]);
        }

        if (!is_array($comment) || $comment === []) {
            return null;
        }

        $normalized = $this->normalizeCommentRow($comment);
        $normalized['attachments'] = $this->findAttachmentsByCommentId((int) $normalized['id']);

        return $normalized;
    }

    /**
     * @param list<array{
     *     original_filename: string,
     *     stored_filename: string,
     *     file_path: string,
     *     file_size: string,
     *     mime_type?: string|null
     * }> $attachments
     *
     * @return list<array<string, mixed>>
     */
    public function addCommentAttachments(
        int $ticketId,
        int $commentId,
        array $attachments,
        ?int $uploadedByUserId
    ): array {
        $created = [];

        foreach ($attachments as $attachment) {
            $this->db()->insert('ticket_comment_attachments', [
                'comment_id' => $commentId,
                'ticket_id' => $ticketId,
                'original_filename' => (string) ($attachment['original_filename'] ?? 'file'),
                'stored_filename' => (string) ($attachment['stored_filename'] ?? ''),
                'file_path' => (string) ($attachment['file_path'] ?? ''),
                'file_size' => (string) ($attachment['file_size'] ?? ''),
                'mime_type' => $attachment['mime_type'] ?? null,
                'uploaded_by_user_id' => $uploadedByUserId,
            ]);

            $row = $this->findAttachmentById((int) $this->db()->id());

            if ($row !== null) {
                $created[] = $row;
            }
        }

        return $created;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAttachmentsByCommentId(int $commentId): array
    {
        if (!$this->collaborationTablesReady()) {
            return [];
        }

        $rows = $this->db()->select('ticket_comment_attachments', '*', [
            'comment_id' => $commentId,
            'ORDER' => ['id' => 'ASC'],
        ]);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(fn (array $row): array => $this->normalizeAttachmentRow($row), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAttachmentById(int $attachmentId): ?array
    {
        if (!$this->collaborationTablesReady() || $attachmentId <= 0) {
            return null;
        }

        $row = $this->db()->get('ticket_comment_attachments', '*', ['id' => $attachmentId]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeAttachmentRow($row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findFollowersByTicketId(int $ticketId): array
    {
        if (!$this->collaborationTablesReady()) {
            return [];
        }

        $rows = $this->db()->select('ticket_followers', '*', [
            'ticket_id' => $ticketId,
            'ORDER' => ['id' => 'ASC'],
        ]);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(fn (array $row): array => $this->normalizeFollowerRow($row), $rows);
    }

    /**
     * @param list<array{personnel_id?: int|null, user_id?: int|null, email?: string|null, notify_email?: bool|int|null}> $items
     *
     * @return list<array<string, mixed>>
     */
    public function replaceFollowers(int $ticketId, array $items): array
    {
        if ($this->findById($ticketId) === null) {
            throw new \InvalidArgumentException(__('ticket_not_found'));
        }

        if (!$this->collaborationTablesReady()) {
            throw new \RuntimeException(__('ticket_followers_unavailable'));
        }

        $this->db()->delete('ticket_followers', ['ticket_id' => $ticketId]);

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $personnelId = isset($item['personnel_id']) && (int) $item['personnel_id'] > 0
                ? (int) $item['personnel_id']
                : null;
            $userId = isset($item['user_id']) && (int) $item['user_id'] > 0
                ? (int) $item['user_id']
                : null;
            $email = isset($item['email']) ? strtolower(trim((string) $item['email'])) : '';

            if ($personnelId === null && $userId === null && $email === '') {
                continue;
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException(__('ticket_follower_email_invalid'));
            }

            if ($personnelId !== null) {
                $this->assertPersonnelExists($personnelId);
            }

            if ($userId !== null) {
                $this->assertUserExists($userId);
            }

            $this->db()->insert('ticket_followers', [
                'ticket_id' => $ticketId,
                'personnel_id' => $personnelId,
                'user_id' => $userId,
                'email' => $email !== '' ? $email : null,
                'notify_email' => !array_key_exists('notify_email', $item) || (bool) $item['notify_email'] ? 1 : 0,
            ]);
        }

        return $this->findFollowersByTicketId($ticketId);
    }

    /**
     * @return list<string>
     */
    public function followerEmailsForTicket(int $ticketId): array
    {
        $emails = [];

        foreach ($this->findFollowersByTicketId($ticketId) as $follower) {
            if (!(bool) ($follower['notify_email'] ?? true)) {
                continue;
            }

            $email = trim((string) ($follower['email'] ?? ''));

            if ($email === '' && !empty($follower['personnel_id'])) {
                $row = $this->db()->get('personnel', 'email', ['id' => (int) $follower['personnel_id']]);
                $email = is_string($row) ? $row : (string) ($row['email'] ?? '');
            }

            if ($email === '' && !empty($follower['user_id'])) {
                $row = $this->db()->get('users', 'email', ['id' => (int) $follower['user_id']]);
                $email = is_string($row) ? $row : (string) ($row['email'] ?? '');
            }

            $email = strtolower(trim($email));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * @return array<string, mixed>
     */
    public function transferToCategory(
        int $ticketId,
        int $categoryId,
        ?int $actorUserId,
        string $actorName,
        ?string $note = null
    ): array {
        $existing = $this->findById($ticketId);

        if ($existing === null) {
            throw new \InvalidArgumentException(__('ticket_not_found'));
        }

        $this->assertCategoryExists($categoryId);

        $oldCategoryName = trim((string) ($existing['category_name'] ?? '')) ?: __('ticket_transfer_uncategorized');
        $category = $this->db()->get('ticket_categories', ['id', 'name'], ['id' => $categoryId]);
        $newCategoryName = is_array($category)
            ? trim((string) ($category['name'] ?? ''))
            : '';

        if ($newCategoryName === '') {
            throw new \InvalidArgumentException(__('ticket_category_not_found'));
        }

        $this->db()->update('tickets', ['category_id' => $categoryId], ['id' => $ticketId]);

        $systemBody = sprintf(
            __('ticket_transfer_system_note'),
            $oldCategoryName,
            $newCategoryName
        );

        if ($note !== null && trim($note) !== '') {
            $systemBody .= "\n\n" . trim($note);
        }

        $this->addComment(
            $ticketId,
            $systemBody,
            $actorUserId,
            $actorName !== '' ? $actorName : __('ticket_system_author'),
            true
        );

        $updated = $this->findById($ticketId, true);

        if ($updated === null) {
            throw new \RuntimeException(__('ticket_update_error'));
        }

        return $updated;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmailMessageId(string $messageId): ?array
    {
        $normalized = $this->normalizeOptionalMessageId($messageId);

        if ($normalized === null) {
            return null;
        }

        if ($this->collaborationTablesReady()) {
            $emailRow = $this->db()->get('ticket_email_messages', ['ticket_id'], [
                'message_id' => $normalized,
            ]);

            if (is_array($emailRow) && (int) ($emailRow['ticket_id'] ?? 0) > 0) {
                return $this->findById((int) $emailRow['ticket_id']);
            }
        }

        $ticket = $this->db()->get('tickets', 'id', [
            'email_message_id' => $normalized,
        ]);

        $ticketId = is_array($ticket) ? (int) ($ticket['id'] ?? 0) : (int) $ticket;

        return $ticketId > 0 ? $this->findById($ticketId) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByTicketNumber(string $ticketNumber): ?array
    {
        $normalized = strtoupper(trim($ticketNumber));

        if ($normalized === '') {
            return null;
        }

        $rows = $this->selectRows(['tickets.ticket_number' => $normalized], 1);

        return $rows === [] ? null : $this->normalizeRow($rows[0]);
    }

    public function recordEmailMessage(
        int $ticketId,
        string $messageId,
        string $direction,
        ?string $inReplyTo = null,
        ?int $commentId = null,
        ?string $fromAddress = null,
        ?string $subject = null
    ): void {
        if (!$this->collaborationTablesReady()) {
            return;
        }

        $normalizedMessageId = $this->normalizeOptionalMessageId($messageId);

        if ($normalizedMessageId === null) {
            return;
        }

        if ($this->db()->has('ticket_email_messages', ['message_id' => $normalizedMessageId])) {
            return;
        }

        $this->db()->insert('ticket_email_messages', [
            'ticket_id' => $ticketId,
            'comment_id' => $commentId,
            'message_id' => $normalizedMessageId,
            'in_reply_to' => $this->normalizeOptionalMessageId($inReplyTo),
            'direction' => $direction === self::EMAIL_DIRECTION_OUTBOUND
                ? self::EMAIL_DIRECTION_OUTBOUND
                : self::EMAIL_DIRECTION_INBOUND,
            'from_address' => $fromAddress !== null ? trim($fromAddress) : null,
            'subject' => $subject !== null ? trim($subject) : null,
        ]);
    }

    public function emailMessageExists(string $messageId): bool
    {
        $normalized = $this->normalizeOptionalMessageId($messageId);

        if ($normalized === null || !$this->collaborationTablesReady()) {
            return false;
        }

        return $this->db()->has('ticket_email_messages', ['message_id' => $normalized]);
    }

    /**
     * @return list<string>
     */
    public function emailReferenceChain(int $ticketId): array
    {
        if (!$this->collaborationTablesReady()) {
            return [];
        }

        $rows = $this->db()->select('ticket_email_messages', ['message_id'], [
            'ticket_id' => $ticketId,
            'ORDER' => ['id' => 'ASC'],
        ]);

        if (!is_array($rows)) {
            return [];
        }

        $ids = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $messageId = $this->normalizeOptionalMessageId((string) ($row['message_id'] ?? ''));

            if ($messageId !== null) {
                $ids[] = $messageId;
            }
        }

        return $ids;
    }

    public function setEmailThreadRoot(int $ticketId, string $messageId, ?string $references = null): void
    {
        $normalized = $this->normalizeOptionalMessageId($messageId);

        if ($normalized === null) {
            return;
        }

        $this->db()->update('tickets', [
            'source' => self::SOURCE_EMAIL,
            'email_message_id' => $normalized,
            'email_references' => $references,
        ], ['id' => $ticketId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllByPersonnelId(int $personnelId, ?string $status = null, ?string $priority = null): array
    {
        return $this->findPaginatedByPersonnelId($personnelId, $status, $priority)['data'];
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
            $ticket['comments'] = $this->findCommentsByTicketId($id, false);
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
     * @param array<string, mixed> $queryParams
     *
     * @return array<string, 'ASC'|'DESC'>
     */
    public function buildSortOrderFromQuery(array $queryParams): array
    {
        return SortQuery::parseMapped(
            $queryParams,
            self::SORTABLE_COLUMNS,
            ['created_at' => 'DESC', 'id' => 'DESC']
        )['order'];
    }

    /**
     * @param array<string, mixed> $conditions
     *
     * @return list<array<string, mixed>>
     */
    private function selectRows(
        array $conditions = [],
        ?int $limit = null,
        ?int $offset = null,
        ?array $order = null
    ): array {
        $options = [
            'ORDER' => $order ?? [
                'tickets.created_at' => 'DESC',
                'tickets.id' => 'DESC',
            ],
        ];

        if ($conditions !== []) {
            $options = [...$conditions, ...$options];
        }

        if ($limit !== null) {
            $options['LIMIT'] = $offset !== null ? [$offset, $limit] : $limit;
        }

        return $this->db()->select('tickets', [
            '[>]personnel' => ['personnel_id' => 'id'],
            '[>]assets_global_registry' => ['asset_id' => 'id'],
            '[>]ticket_categories' => ['category_id' => 'id'],
            '[>]users(assigned)' => ['assigned_user_id' => 'id'],
            '[>]users(creator)' => ['created_by_user_id' => 'id'],
        ], [
            'tickets.id',
            'tickets.ticket_number',
            'tickets.subject',
            'tickets.description',
            'tickets.personnel_id',
            'tickets.asset_id',
            'tickets.asset_type',
            'tickets.status',
            'tickets.priority',
            'tickets.category_id',
            'tickets.assigned_user_id',
            'tickets.created_by_user_id',
            'tickets.source',
            'tickets.email_message_id',
            'tickets.email_references',
            'tickets.resolved_at',
            'tickets.created_at',
            'tickets.updated_at',
            'personnel.name(personnel_name)',
            'personnel.email(personnel_email)',
            'personnel.department(personnel_department)',
            'assets_global_registry.asset_tag',
            'assets_global_registry.name(asset_name)',
            'ticket_categories.name(category_name)',
            'ticket_categories.color_code(category_color)',
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
        $row['asset_type'] = trim((string) ($row['asset_type'] ?? '')) ?: null;
        $row['assigned_user_id'] = $row['assigned_user_id'] !== null ? (int) $row['assigned_user_id'] : null;
        $row['created_by_user_id'] = $row['created_by_user_id'] !== null ? (int) $row['created_by_user_id'] : null;
        $row['category_id'] = $row['category_id'] !== null ? (int) $row['category_id'] : null;
        $row['status'] = (string) $row['status'];
        $row['priority'] = (string) $row['priority'];
        $row['source'] = trim((string) ($row['source'] ?? self::SOURCE_WEB)) ?: self::SOURCE_WEB;
        $row['email_message_id'] = trim((string) ($row['email_message_id'] ?? '')) ?: null;
        $row['email_references'] = trim((string) ($row['email_references'] ?? '')) ?: null;
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

        $row['category_name'] = trim((string) ($row['category_name'] ?? '')) ?: null;
        $row['category_color'] = trim((string) ($row['category_color'] ?? '')) ?: null;

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeCommentRow(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['ticket_id'] = (int) ($row['ticket_id'] ?? 0);
        $row['user_id'] = isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null;
        $row['author_name'] = (string) ($row['author_name'] ?? '');
        $row['body'] = (string) ($row['body'] ?? '');
        $row['is_internal'] = (bool) ((int) ($row['is_internal'] ?? 0));
        $row['email_message_id'] = trim((string) ($row['email_message_id'] ?? '')) ?: null;
        $row['email_in_reply_to'] = trim((string) ($row['email_in_reply_to'] ?? '')) ?: null;
        $row['created_at'] = (string) ($row['created_at'] ?? '');
        $row['attachments'] = $row['attachments'] ?? [];

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeAttachmentRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'comment_id' => (int) ($row['comment_id'] ?? 0),
            'ticket_id' => (int) ($row['ticket_id'] ?? 0),
            'original_filename' => (string) ($row['original_filename'] ?? ''),
            'stored_filename' => (string) ($row['stored_filename'] ?? ''),
            'file_path' => (string) ($row['file_path'] ?? ''),
            'file_size' => (string) ($row['file_size'] ?? ''),
            'mime_type' => trim((string) ($row['mime_type'] ?? '')) ?: null,
            'uploaded_by_user_id' => isset($row['uploaded_by_user_id']) && $row['uploaded_by_user_id'] !== null
                ? (int) $row['uploaded_by_user_id']
                : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeFollowerRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'ticket_id' => (int) ($row['ticket_id'] ?? 0),
            'personnel_id' => isset($row['personnel_id']) && $row['personnel_id'] !== null
                ? (int) $row['personnel_id']
                : null,
            'user_id' => isset($row['user_id']) && $row['user_id'] !== null
                ? (int) $row['user_id']
                : null,
            'email' => trim((string) ($row['email'] ?? '')) ?: null,
            'notify_email' => (bool) ((int) ($row['notify_email'] ?? 1)),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    private function normalizeOptionalMessageId(?string $messageId): ?string
    {
        if ($messageId === null) {
            return null;
        }

        $trimmed = trim($messageId);

        if ($trimmed === '') {
            return null;
        }

        if ($trimmed[0] !== '<') {
            $trimmed = '<' . trim($trimmed, '<>') . '>';
        }

        return $trimmed;
    }

    private function collaborationTablesReady(): bool
    {
        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        try {
            $statement = $this->db()->query("SHOW TABLES LIKE 'ticket_comment_attachments'");
            $ready = $statement !== false && $statement->rowCount() > 0;
        } catch (\Throwable) {
            $ready = false;
        }

        return $ready;
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

        if ($this->assetRegistry->resolveTypeId($assetId) !== null) {
            return;
        }

        if ($this->db()->has('assets', ['id' => $assetId])) {
            return;
        }

        throw new \InvalidArgumentException(__('ticket_asset_not_found'));
    }

    private function resolveAssetTypeSlug(?int $assetId): ?string
    {
        if ($assetId === null || $assetId <= 0) {
            return null;
        }

        $slug = $this->assetsGlobalRegistry->resolveTypeSlug($assetId);

        if ($slug !== null && $slug !== '') {
            return $slug;
        }

        $typeId = $this->assetRegistry->resolveTypeId($assetId);

        if ($typeId === null) {
            return null;
        }

        $row = $this->db()->get('asset_types', 'slug', ['id' => $typeId]);

        if (is_string($row)) {
            $slug = trim($row);

            return $slug !== '' ? $slug : null;
        }

        if (is_array($row)) {
            $slug = trim((string) ($row['slug'] ?? ''));

            return $slug !== '' ? $slug : null;
        }

        return null;
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

    private function assertCategoryExists(?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        if (!$this->db()->has('ticket_categories', ['id' => $categoryId])) {
            throw new \InvalidArgumentException(__('ticket_category_not_found'));
        }
    }

    /**
     * @param mixed $value
     */
    private function normalizeOptionalCategoryId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $categoryId = (int) $value;

        return $categoryId > 0 ? $categoryId : null;
    }

    /**
     * @return array{
     *     data: list<array<string, mixed>>,
     *     pagination: array{page: int, per_page: int, total: int, total_pages: int}
     * }
     */
    private function paginateList(
        ?int $personnelId,
        ?string $status,
        ?string $priority,
        int $page,
        ?string $scope = null,
        ?array $order = null
    ): array {
        $selectConditions = $this->buildSelectConditions($personnelId, $status, $priority, $scope);
        $countConditions = $this->buildCountConditions($personnelId, $status, $priority, $scope);
        $page = max(1, $page);
        $perPage = ListPagination::TICKET_PAGE_SIZE;
        $total = (int) $this->db()->count('tickets', $countConditions);
        $rows = $this->selectRows(
            $selectConditions,
            $perPage,
            ListPagination::offset($page, $perPage),
            $order
        );

        return [
            'data' => $this->mapRows($rows),
            'pagination' => ListPagination::meta($page, $total, $perPage),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCountConditions(
        ?int $personnelId,
        ?string $status,
        ?string $priority,
        ?string $scope = null
    ): array {
        $conditions = [];

        if ($personnelId !== null) {
            $conditions['personnel_id'] = $personnelId;
        }

        if ($scope === 'active') {
            $conditions['status[!]'] = [self::STATUS_CLOSED, self::STATUS_RESOLVED];
        } elseif ($status !== null && $status !== '') {
            $conditions['status'] = $this->normalizeStatus($status);
        }

        if ($priority !== null && $priority !== '') {
            $conditions['priority'] = $this->normalizePriority($priority);
        }

        return $conditions;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSelectConditions(
        ?int $personnelId,
        ?string $status,
        ?string $priority,
        ?string $scope = null
    ): array {
        $conditions = [];

        if ($personnelId !== null) {
            $conditions['tickets.personnel_id'] = $personnelId;
        }

        if ($scope === 'active') {
            $conditions['tickets.status[!]'] = [self::STATUS_CLOSED, self::STATUS_RESOLVED];
        } elseif ($status !== null && $status !== '') {
            $conditions['tickets.status'] = $this->normalizeStatus($status);
        }

        if ($priority !== null && $priority !== '') {
            $conditions['tickets.priority'] = $this->normalizePriority($priority);
        }

        return $conditions;
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
