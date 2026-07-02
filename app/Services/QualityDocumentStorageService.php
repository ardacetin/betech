<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

class QualityDocumentStorageService
{
    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'png'];

    /** @var list<string> */
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        'sh', 'bash', 'zsh', 'js', 'mjs', 'cjs', 'exe', 'bat', 'cmd', 'com', 'htaccess',
    ];

    private const MAX_BYTES = 26214400;

    private readonly string $storageDirectory;

    public function __construct(string $projectRoot)
    {
        $this->storageDirectory = rtrim($projectRoot, '/') . '/storage/documents';
    }

    /**
     * @return array{stored_filename: string, relative_path: string, absolute_path: string, original_filename: string, file_size: string}
     */
    public function storeUploadedFile(UploadedFileInterface $file): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(__('quality_document_upload_error'));
        }

        $originalFilename = basename((string) ($file->getClientFilename() ?? 'document'));
        $extension = $this->resolveSafeExtension($originalFilename);

        if (!$this->isAllowedExtension($extension)) {
            throw new InvalidArgumentException(__('quality_document_extension_not_allowed'));
        }

        $this->assertNotBlockedFilename($originalFilename);

        $sizeBytes = $file->getSize();

        if ($sizeBytes === null || $sizeBytes <= 0) {
            throw new InvalidArgumentException(__('quality_document_upload_error'));
        }

        if ($sizeBytes > self::MAX_BYTES) {
            throw new InvalidArgumentException(__('quality_document_file_too_large'));
        }

        $this->ensureStorageDirectoryExists();

        $storedFilename = $this->generateStoredFilename($extension);
        $absolutePath = $this->storageDirectory . '/' . $storedFilename;
        $relativePath = 'storage/documents/' . $storedFilename;

        $file->moveTo($absolutePath);

        $this->assertStoredFileSafe($absolutePath, $extension);

        return [
            'stored_filename' => $storedFilename,
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'original_filename' => $originalFilename,
            'file_size' => self::formatBytes((int) $sizeBytes),
        ];
    }

    public function resolveAbsolutePath(string $relativePath): string
    {
        $normalizedRelative = str_replace('\\', '/', trim($relativePath));
        $normalizedRelative = ltrim($normalizedRelative, '/');

        if ($normalizedRelative === ''
            || str_contains($normalizedRelative, '..')
            || !str_starts_with($normalizedRelative, 'storage/documents/')) {
            throw new InvalidArgumentException(__('quality_document_invalid_path'));
        }

        $basename = basename($normalizedRelative);
        $candidate = $this->storageDirectory . '/' . $basename;
        $storageRealPath = realpath($this->storageDirectory);
        $fileRealPath = realpath($candidate);

        if ($storageRealPath === false
            || $fileRealPath === false
            || !str_starts_with($fileRealPath, $storageRealPath)) {
            throw new InvalidArgumentException(__('quality_document_invalid_path'));
        }

        if (!is_file($fileRealPath)) {
            throw new RuntimeException(__('quality_document_not_found'));
        }

        return $fileRealPath;
    }

    public function deleteStoredFile(string $relativePath): void
    {
        try {
            $absolutePath = $this->resolveAbsolutePath($relativePath);
        } catch (InvalidArgumentException) {
            return;
        }

        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' GB';
        }

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    private function ensureStorageDirectoryExists(): void
    {
        if (is_dir($this->storageDirectory)) {
            return;
        }

        if (!mkdir($this->storageDirectory, 0750, true) && !is_dir($this->storageDirectory)) {
            throw new RuntimeException(__('quality_document_storage_unavailable'));
        }
    }

    private function generateStoredFilename(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }

    private function resolveSafeExtension(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension === 'jpeg') {
            return 'jpg';
        }

        return $extension;
    }

    private function isAllowedExtension(string $extension): bool
    {
        return $extension !== '' && in_array($extension, self::ALLOWED_EXTENSIONS, true);
    }

    private function assertNotBlockedFilename(string $filename): void
    {
        $lowerName = strtolower($filename);
        $segments = array_filter(explode('.', $lowerName));

        foreach ($segments as $segment) {
            if (in_array($segment, self::BLOCKED_EXTENSIONS, true)) {
                throw new InvalidArgumentException(__('quality_document_extension_not_allowed'));
            }
        }
    }

    private function assertStoredFileSafe(string $absolutePath, string $extension): void
    {
        if (!is_file($absolutePath)) {
            throw new RuntimeException(__('quality_document_upload_error'));
        }

        $detectedExtension = $this->resolveSafeExtension(basename($absolutePath));

        if (!$this->isAllowedExtension($detectedExtension) || $detectedExtension !== $extension) {
            unlink($absolutePath);
            throw new InvalidArgumentException(__('quality_document_extension_not_allowed'));
        }

        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($absolutePath) ?: '';
            $allowedMimes = $this->allowedMimeTypesForExtension($extension);

            if ($allowedMimes !== [] && !in_array($mimeType, $allowedMimes, true)) {
                unlink($absolutePath);
                throw new InvalidArgumentException(__('quality_document_extension_not_allowed'));
            }
        }
    }

    /**
     * @return list<string>
     */
    private function allowedMimeTypesForExtension(string $extension): array
    {
        return match ($extension) {
            'pdf' => ['application/pdf'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'jpg' => ['image/jpeg'],
            'png' => ['image/png'],
            default => [],
        };
    }
}
