<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use InvalidArgumentException;
use Medoo\Medoo;
use RuntimeException;

class Announcement
{
    private const TABLE = 'announcements';

    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $rows = $this->db()->select(self::TABLE, [
            '[>]personnel' => ['created_by' => 'id'],
        ], [
            self::TABLE . '.id',
            self::TABLE . '.title',
            self::TABLE . '.summary',
            self::TABLE . '.category',
            self::TABLE . '.is_published',
            self::TABLE . '.published_at',
            self::TABLE . '.created_by',
            self::TABLE . '.created_at',
            self::TABLE . '.updated_at',
            'personnel.name(author_name)',
        ], [
            'ORDER' => [
                self::TABLE . '.published_at' => 'DESC',
                self::TABLE . '.id' => 'DESC',
            ],
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            is_array($rows) ? $rows : []
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findPublished(int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));

        $rows = $this->db()->select(self::TABLE, [
            'id',
            'title',
            'summary',
            'category',
            'is_published',
            'published_at',
            'created_by',
            'created_at',
            'updated_at',
        ], [
            'is_published' => 1,
            'ORDER' => [
                'published_at' => 'DESC',
                'id' => 'DESC',
            ],
            'LIMIT' => $limit,
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            is_array($rows) ? $rows : []
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db()->get(self::TABLE, [
            '[>]personnel' => ['created_by' => 'id'],
        ], [
            self::TABLE . '.id',
            self::TABLE . '.title',
            self::TABLE . '.summary',
            self::TABLE . '.category',
            self::TABLE . '.is_published',
            self::TABLE . '.published_at',
            self::TABLE . '.created_by',
            self::TABLE . '.created_at',
            self::TABLE . '.updated_at',
            'personnel.name(author_name)',
        ], [
            self::TABLE . '.id' => $id,
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function create(array $payload, ?int $createdBy): array
    {
        $normalized = $this->normalizePayload($payload);

        $this->db()->insert(self::TABLE, [
            'title' => $normalized['title'],
            'summary' => $normalized['summary'],
            'category' => $normalized['category'],
            'is_published' => $normalized['is_published'] ? 1 : 0,
            'published_at' => $normalized['is_published'] ? date('Y-m-d H:i:s') : null,
            'created_by' => $createdBy !== null && $createdBy > 0 ? $createdBy : null,
        ]);

        $id = (int) $this->db()->id();

        if ($id <= 0) {
            throw new RuntimeException(__('announcement_create_error'));
        }

        $row = $this->findById($id);

        if ($row === null) {
            throw new RuntimeException(__('announcement_create_error'));
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $payload): ?array
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            return null;
        }

        $normalized = $this->normalizePayload($payload);
        $wasPublished = (bool) ($existing['is_published'] ?? false);
        $publishedAt = $existing['published_at'] ?? null;

        if ($normalized['is_published'] && (!$wasPublished || $publishedAt === null || $publishedAt === '')) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        if (!$normalized['is_published']) {
            $publishedAt = null;
        }

        $this->db()->update(self::TABLE, [
            'title' => $normalized['title'],
            'summary' => $normalized['summary'],
            'category' => $normalized['category'],
            'is_published' => $normalized['is_published'] ? 1 : 0,
            'published_at' => $publishedAt,
        ], [
            'id' => $id,
        ]);

        return $this->findById($id);
    }

    public function deleteById(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $this->db()->delete(self::TABLE, ['id' => $id]);

        return !$this->db()->has(self::TABLE, ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{title: string, summary: string, category: string, is_published: bool}
     */
    private function normalizePayload(array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $summary = trim((string) ($payload['summary'] ?? ''));
        $category = trim((string) ($payload['category'] ?? ''));
        $isPublished = filter_var($payload['is_published'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($title === '') {
            throw new InvalidArgumentException(__('announcement_title_required'));
        }

        if (mb_strlen($title) > 255) {
            throw new InvalidArgumentException(__('announcement_title_too_long'));
        }

        if (mb_strlen($category) > 64) {
            throw new InvalidArgumentException(__('announcement_category_too_long'));
        }

        return [
            'title' => $title,
            'summary' => $summary,
            'category' => $category,
            'is_published' => $isPublished,
        ];
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
            'title' => (string) ($row['title'] ?? ''),
            'summary' => (string) ($row['summary'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'is_published' => (int) ($row['is_published'] ?? 0) === 1,
            'published_at' => $row['published_at'] ?? null,
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'author_name' => trim((string) ($row['author_name'] ?? '')),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
