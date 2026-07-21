<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use App\Services\ListPagination;
use App\Services\QualityDocumentStorageService;
use App\Services\SortQuery;
use InvalidArgumentException;
use Medoo\Medoo;
use RuntimeException;

class QualityDocument
{
    private const TABLE = 'quality_documents';

    /** @var array<string, string> */
    public const SORTABLE_COLUMNS = [
        'title' => 'quality_documents.title',
        'created_at' => 'quality_documents.created_at',
        'file_size' => 'quality_documents.file_size',
        'uploaded_by' => 'personnel.name',
        'id' => 'quality_documents.id',
    ];

    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly QualityDocumentStorageService $storageService
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        return $this->findPaginated(1, ListPagination::PAGE_SIZE)['data'];
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
     * @return array{
     *     data: list<array<string, mixed>>,
     *     pagination: array{page: int, per_page: int, total: int, total_pages: int}
     * }
     */
    public function findPaginated(int $page = 1, int $perPage = ListPagination::PAGE_SIZE, ?array $order = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $total = (int) $this->db()->count(self::TABLE);
        $selectOptions = [
            'ORDER' => $order ?? [
                self::TABLE . '.created_at' => 'DESC',
                self::TABLE . '.id' => 'DESC',
            ],
            'LIMIT' => [ListPagination::offset($page, $perPage), $perPage],
        ];

        $rows = $this->db()->select(self::TABLE, [
            '[>]personnel' => ['uploaded_by' => 'id'],
        ], [
            self::TABLE . '.id',
            self::TABLE . '.title',
            self::TABLE . '.filename',
            self::TABLE . '.file_path',
            self::TABLE . '.file_size',
            self::TABLE . '.uploaded_by',
            self::TABLE . '.is_public',
            self::TABLE . '.created_at',
            'personnel.name(uploaded_by_name)',
        ], $selectOptions);

        return [
            'data' => array_map(
                fn (array $row): array => $this->normalizeRow($row),
                $rows
            ),
            'pagination' => ListPagination::meta($page, $total, $perPage),
        ];
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
            '[>]personnel' => ['uploaded_by' => 'id'],
        ], [
            self::TABLE . '.id',
            self::TABLE . '.title',
            self::TABLE . '.filename',
            self::TABLE . '.file_path',
            self::TABLE . '.file_size',
            self::TABLE . '.uploaded_by',
            self::TABLE . '.is_public',
            self::TABLE . '.created_at',
            'personnel.name(uploaded_by_name)',
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
    /**
     * @return list<array<string, mixed>>
     */
    public function findPublic(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        $rows = $this->db()->select(self::TABLE, [
            'id',
            'title',
            'filename',
            'file_path',
            'file_size',
            'uploaded_by',
            'is_public',
            'created_at',
        ], [
            'is_public' => 1,
            'ORDER' => [
                'created_at' => 'DESC',
                'id' => 'DESC',
            ],
            'LIMIT' => $limit,
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            is_array($rows) ? $rows : []
        );
    }

    public function create(
        string $title,
        string $filename,
        string $filePath,
        string $fileSize,
        ?int $uploadedBy,
        bool $isPublic = false
    ): array {
        $title = trim($title);

        if ($title === '') {
            throw new InvalidArgumentException(__('quality_document_title_required'));
        }

        if (mb_strlen($title) > 255) {
            throw new InvalidArgumentException(__('quality_document_title_too_long'));
        }

        $this->db()->insert(self::TABLE, [
            'title' => $title,
            'filename' => $filename,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'uploaded_by' => $uploadedBy !== null && $uploadedBy > 0 ? $uploadedBy : null,
            'is_public' => $isPublic ? 1 : 0,
        ]);

        $id = (int) $this->db()->id();

        if ($id <= 0) {
            throw new RuntimeException(__('quality_document_create_error'));
        }

        $document = $this->findById($id);

        if ($document === null) {
            throw new RuntimeException(__('quality_document_create_error'));
        }

        return $document;
    }

    public function setPublic(int $id, bool $isPublic): ?array
    {
        if ($id <= 0 || $this->findById($id) === null) {
            return null;
        }

        $this->db()->update(self::TABLE, [
            'is_public' => $isPublic ? 1 : 0,
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

        $document = $this->findById($id);

        if ($document === null) {
            return false;
        }

        $deleted = false;

        $this->db()->action(function (Medoo $database) use ($id, $document, &$deleted): void {
            $database->delete(self::TABLE, [
                'id' => $id,
            ]);

            if ($database->has(self::TABLE, ['id' => $id])) {
                throw new RuntimeException(__('quality_document_delete_error'));
            }

            $this->storageService->deleteStoredFile((string) ($document['file_path'] ?? ''));
            $deleted = true;
        });

        return $deleted;
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
            'filename' => (string) ($row['filename'] ?? ''),
            'file_path' => (string) ($row['file_path'] ?? ''),
            'file_size' => (string) ($row['file_size'] ?? ''),
            'uploaded_by' => isset($row['uploaded_by']) ? (int) $row['uploaded_by'] : null,
            'uploaded_by_name' => trim((string) ($row['uploaded_by_name'] ?? '')),
            'is_public' => (int) ($row['is_public'] ?? 0) === 1,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
