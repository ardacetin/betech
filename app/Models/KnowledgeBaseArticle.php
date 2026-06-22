<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class KnowledgeBaseArticle
{
    private const TABLE = 'knowledge_base_articles';

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
            '[>]personnel' => ['author_id' => 'id'],
        ], [
            self::TABLE . '.id',
            self::TABLE . '.title',
            self::TABLE . '.content',
            self::TABLE . '.is_published',
            self::TABLE . '.author_id',
            self::TABLE . '.created_at',
            self::TABLE . '.updated_at',
            'personnel.name(author_name)',
        ], [
            'ORDER' => [
                self::TABLE . '.updated_at' => 'DESC',
            ],
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findPublished(): array
    {
        $rows = $this->db()->select(self::TABLE, [
            'id',
            'title',
            'content',
            'is_published',
            'author_id',
            'created_at',
            'updated_at',
        ], [
            'is_published' => 1,
            'ORDER' => [
                'updated_at' => 'DESC',
            ],
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db()->get(self::TABLE, [
            '[>]personnel' => ['author_id' => 'id'],
        ], [
            self::TABLE . '.id',
            self::TABLE . '.title',
            self::TABLE . '.content',
            self::TABLE . '.is_published',
            self::TABLE . '.author_id',
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
     * @return array<string, mixed>
     */
    public function create(string $title, string $content, bool $isPublished, ?int $authorId): array
    {
        $trimmedTitle = trim($title);
        $trimmedContent = trim($content);

        if ($trimmedTitle === '') {
            throw new \InvalidArgumentException(__('kb_title_required'));
        }

        if ($trimmedContent === '') {
            throw new \InvalidArgumentException(__('kb_content_required'));
        }

        $this->db()->insert(self::TABLE, [
            'title' => $trimmedTitle,
            'content' => $trimmedContent,
            'is_published' => $isPublished ? 1 : 0,
            'author_id' => $authorId,
        ]);

        $created = $this->findById((int) $this->db()->id());

        if ($created === null) {
            throw new \RuntimeException(__('kb_create_error'));
        }

        return $created;
    }

    /**
     * @param array{title?: string, content?: string, is_published?: bool} $payload
     *
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $payload): ?array
    {
        if ($this->findById($id) === null) {
            return null;
        }

        $update = [];

        if (array_key_exists('title', $payload)) {
            $trimmedTitle = trim((string) $payload['title']);

            if ($trimmedTitle === '') {
                throw new \InvalidArgumentException(__('kb_title_required'));
            }

            $update['title'] = $trimmedTitle;
        }

        if (array_key_exists('content', $payload)) {
            $trimmedContent = trim((string) $payload['content']);

            if ($trimmedContent === '') {
                throw new \InvalidArgumentException(__('kb_content_required'));
            }

            $update['content'] = $trimmedContent;
        }

        if (array_key_exists('is_published', $payload)) {
            $update['is_published'] = $payload['is_published'] ? 1 : 0;
        }

        if ($update !== []) {
            $this->db()->update(self::TABLE, $update, ['id' => $id]);
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $this->db()->delete(self::TABLE, ['id' => $id]);

        return !$this->db()->has(self::TABLE, ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['is_published'] = (int) ($row['is_published'] ?? 0) === 1;
        $row['author_id'] = isset($row['author_id']) && $row['author_id'] !== null
            ? (int) $row['author_id']
            : null;

        if (array_key_exists('author_name', $row)) {
            $authorName = trim((string) ($row['author_name'] ?? ''));
            $row['author_name'] = $authorName !== '' ? $authorName : null;
        }

        return $row;
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
