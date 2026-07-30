<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

class TicketAttachmentStorageService
{
    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'txt',
        'csv',
        'zip',
    ];

    /** @var list<string> */
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        'sh', 'bash', 'zsh', 'js', 'mjs', 'cjs', 'exe', 'bat', 'cmd', 'com', 'htaccess',
    ];

    private const MAX_BYTES = 5_242_880; // 5 MB

    private readonly string $storageDirectory;

    public function __construct(string $projectRoot)
    {
        $this->storageDirectory = rtrim($projectRoot, '/') . '/storage/ticket-attachments';
    }

    /**
     * @return array{
     *     stored_filename: string,
     *     relative_path: string,
     *     absolute_path: string,
     *     original_filename: string,
     *     file_size: int,
     *     mime_type: string
     * }
     */
    public function storeUploadedFile(UploadedFileInterface $file): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(__('ticket_attachment_upload_error'));
        }

        $originalFilename = basename((string) ($file->getClientFilename() ?? 'attachment'));
        $extension = $this->resolveSafeExtension($originalFilename);

        if (!$this->isAllowedExtension($extension)) {
            throw new InvalidArgumentException(__('ticket_attachment_extension_not_allowed'));
        }

        $this->assertNotBlockedFilename($originalFilename);

        $sizeBytes = $file->getSize();

        if ($sizeBytes === null || $sizeBytes <= 0) {
            throw new InvalidArgumentException(__('ticket_attachment_upload_error'));
        }

        if ($sizeBytes > self::MAX_BYTES) {
            throw new InvalidArgumentException(__('ticket_attachment_file_too_large'));
        }

        $this->ensureStorageDirectoryExists();

        $storedFilename = $this->generateStoredFilename($extension);
        $absolutePath = $this->storageDirectory . '/' . $storedFilename;
        $relativePath = 'storage/ticket-attachments/' . $storedFilename;

        $file->moveTo($absolutePath);

        $this->assertStoredFileSafe($absolutePath, $extension);

        $mimeType = function_exists('mime_content_type')
            ? (string) (mime_content_type($absolutePath) ?: '')
            : (string) ($file->getClientMediaType() ?? '');

        return [
            'stored_filename' => $storedFilename,
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'original_filename' => $originalFilename,
            'file_size' => (int) $sizeBytes,
            'mime_type' => $mimeType,
        ];
    }

    public function resolveAbsolutePath(string $relativePath): string
    {
        $normalizedRelative = str_replace('\\', '/', trim($relativePath));
        $normalizedRelative = ltrim($normalizedRelative, '/');

        if (!str_starts_with($normalizedRelative, 'storage/ticket-attachments/')) {
            throw new InvalidArgumentException(__('ticket_attachment_not_found'));
        }

        $filename = basename($normalizedRelative);
        $absolutePath = $this->storageDirectory . '/' . $filename;

        if (!is_file($absolutePath)) {
            throw new InvalidArgumentException(__('ticket_attachment_not_found'));
        }

        $realStorage = realpath($this->storageDirectory);
        $realFile = realpath($absolutePath);

        if ($realStorage === false || $realFile === false || !str_starts_with($realFile, $realStorage . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException(__('ticket_attachment_not_found'));
        }

        return $realFile;
    }

    public function deleteByRelativePath(string $relativePath): void
    {
        try {
            $absolutePath = $this->resolveAbsolutePath($relativePath);
        } catch (InvalidArgumentException) {
            return;
        }

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function ensureStorageDirectoryExists(): void
    {
        if (is_dir($this->storageDirectory)) {
            return;
        }

        if (!mkdir($this->storageDirectory, 0775, true) && !is_dir($this->storageDirectory)) {
            throw new RuntimeException(__('ticket_attachment_upload_error'));
        }
    }

    private function generateStoredFilename(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }

    private function resolveSafeExtension(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    private function isAllowedExtension(string $extension): bool
    {
        return in_array($extension, self::ALLOWED_EXTENSIONS, true)
            || ($extension === 'jpg' && in_array('jpeg', self::ALLOWED_EXTENSIONS, true));
    }

    private function assertNotBlockedFilename(string $filename): void
    {
        $lower = strtolower($filename);

        foreach (self::BLOCKED_EXTENSIONS as $blocked) {
            if (str_ends_with($lower, '.' . $blocked)) {
                throw new InvalidArgumentException(__('ticket_attachment_extension_not_allowed'));
            }
        }
    }

    private function assertStoredFileSafe(string $absolutePath, string $extension): void
    {
        if (!is_file($absolutePath)) {
            throw new RuntimeException(__('ticket_attachment_upload_error'));
        }

        $detectedExtension = $this->resolveSafeExtension(basename($absolutePath));

        if (!$this->isAllowedExtension($detectedExtension) || $detectedExtension !== $extension) {
            unlink($absolutePath);
            throw new InvalidArgumentException(__('ticket_attachment_extension_not_allowed'));
        }
    }
}
