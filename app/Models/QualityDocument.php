<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use App\Services\QualityDocumentStorageService;
use InvalidArgumentException;
use Medoo\Medoo;
use RuntimeException;

class QualityDocument
{
    private const TABLE = 'quality_documents';

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
        $rows = $this->db()->select(self::TABLE, [
            '[>]personnel' => ['uploaded_by' => 'id'],
        ], [
            self::TABLE . '.id',
            self::TABLE . '.title',
            self::TABLE . '.filename',
            self::TABLE . '.file_path',
            self::TABLE . '.file_size',
            self::TABLE . '.uploaded_by',
            self::TABLE . '.created_at',
            'personnel.name(uploaded_by_name)',
        ], [
            'ORDER' => [
                self::TABLE . '.created_at' => 'DESC',
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
    public function create(
        string $title,
        string $filename,
        string $filePath,
        string $fileSize,
        ?int $uploadedBy
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
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
