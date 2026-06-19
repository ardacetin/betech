<?php

declare(strict_types=1);

namespace App\Services;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use RuntimeException;

class R2BackupStorage
{
    public const PRESIGNED_URL_TTL_SECONDS = 900;

    private const BACKUP_KEY_PREFIX = 'backup_';

    private const BACKUP_FILENAME_PATTERN = '/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql\.gz$/';

    private readonly S3Client $client;

    /**
     * @param array<string, string> $config
     */
    public function __construct(
        private readonly array $config,
        private readonly ?AppLogger $appLogger = null
    ) {
        $this->client = $this->createClient();
    }

    public function isConfigured(): bool
    {
        return $this->config['account_id'] !== ''
            && $this->config['access_key_id'] !== ''
            && $this->config['secret_access_key'] !== ''
            && $this->config['bucket'] !== '';
    }

    public function upload(string $localPath, string $objectKey): void
    {
        $this->assertConfigured();

        if (!is_file($localPath) || !is_readable($localPath)) {
            throw new RuntimeException('Local backup file is not readable.');
        }

        try {
            $this->client->putObject([
                'Bucket' => $this->config['bucket'],
                'Key' => $objectKey,
                'SourceFile' => $localPath,
                'ContentType' => 'application/gzip',
            ]);

            $this->client->headObject([
                'Bucket' => $this->config['bucket'],
                'Key' => $objectKey,
            ]);
        } catch (AwsException $exception) {
            $this->log('backup.r2.upload_failed', [
                'object_key' => $objectKey,
                'error' => $exception->getAwsErrorMessage() ?: $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'Failed to upload backup to Cloudflare R2: ' . ($exception->getAwsErrorMessage() ?: $exception->getMessage()),
                0,
                $exception
            );
        }

        $this->log('backup.r2.upload_success', [
            'object_key' => $objectKey,
            'bucket' => $this->config['bucket'],
        ]);
    }

    /**
     * @return list<array{filename: string, size_bytes: int, size_label: string, created_at: string}>
     */
    public function listBackups(): array
    {
        $this->assertConfigured();

        $backups = [];
        $continuationToken = null;

        try {
            do {
                $params = [
                    'Bucket' => $this->config['bucket'],
                    'Prefix' => self::BACKUP_KEY_PREFIX,
                ];

                if ($continuationToken !== null) {
                    $params['ContinuationToken'] = $continuationToken;
                }

                $result = $this->client->listObjectsV2($params);
                $objects = $result['Contents'] ?? [];

                foreach ($objects as $object) {
                    if (!is_array($object)) {
                        continue;
                    }

                    $key = (string) ($object['Key'] ?? '');

                    if (!$this->isValidBackupKey($key)) {
                        continue;
                    }

                    $sizeBytes = (int) ($object['Size'] ?? 0);
                    $lastModified = $object['LastModified'] ?? null;
                    $timestamp = $lastModified instanceof \DateTimeInterface
                        ? $lastModified->getTimestamp()
                        : null;

                    if ($timestamp === null) {
                        continue;
                    }

                    $backups[] = [
                        'filename' => $key,
                        'size_bytes' => $sizeBytes,
                        'size_label' => $this->formatBytes($sizeBytes),
                        'created_at' => date('Y-m-d H:i:s', $timestamp),
                    ];
                }

                $continuationToken = $result['IsTruncated'] ?? false
                    ? (string) ($result['NextContinuationToken'] ?? '')
                    : null;

                if ($continuationToken === '') {
                    $continuationToken = null;
                }
            } while ($continuationToken !== null);
        } catch (AwsException $exception) {
            $this->log('backup.r2.list_failed', [
                'error' => $exception->getAwsErrorMessage() ?: $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'Failed to list backups from Cloudflare R2: ' . ($exception->getAwsErrorMessage() ?: $exception->getMessage()),
                0,
                $exception
            );
        }

        usort(
            $backups,
            static fn (array $left, array $right): int => strcmp($right['created_at'], $left['created_at'])
        );

        return $backups;
    }

    public function objectExists(string $objectKey): bool
    {
        if (!$this->isValidBackupKey($objectKey)) {
            return false;
        }

        $this->assertConfigured();

        try {
            $this->client->headObject([
                'Bucket' => $this->config['bucket'],
                'Key' => $objectKey,
            ]);

            return true;
        } catch (AwsException $exception) {
            $statusCode = (int) ($exception->getStatusCode() ?? 0);

            if ($statusCode === 404) {
                return false;
            }

            throw new RuntimeException(
                'Failed to verify backup in Cloudflare R2: ' . ($exception->getAwsErrorMessage() ?: $exception->getMessage()),
                0,
                $exception
            );
        }
    }

    public function createPresignedDownloadUrl(string $objectKey): string
    {
        if (!$this->isValidBackupKey($objectKey)) {
            throw new RuntimeException('Invalid backup filename.');
        }

        $this->assertConfigured();

        if (!$this->objectExists($objectKey)) {
            throw new RuntimeException('Backup file not found in Cloudflare R2.');
        }

        try {
            $command = $this->client->getCommand('GetObject', [
                'Bucket' => $this->config['bucket'],
                'Key' => $objectKey,
                'ResponseContentDisposition' => 'attachment; filename="' . $objectKey . '"',
                'ResponseContentType' => 'application/gzip',
            ]);

            $request = $this->client->createPresignedRequest(
                $command,
                '+' . self::PRESIGNED_URL_TTL_SECONDS . ' seconds'
            );

            return (string) $request->getUri();
        } catch (AwsException $exception) {
            $this->log('backup.r2.presign_failed', [
                'object_key' => $objectKey,
                'error' => $exception->getAwsErrorMessage() ?: $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'Failed to generate download URL: ' . ($exception->getAwsErrorMessage() ?: $exception->getMessage()),
                0,
                $exception
            );
        }
    }

    public function purgeExpired(int $retentionDays): int
    {
        $this->assertConfigured();

        $deletedCount = 0;
        $cutoff = time() - ($retentionDays * 86400);
        $continuationToken = null;

        try {
            do {
                $params = [
                    'Bucket' => $this->config['bucket'],
                    'Prefix' => self::BACKUP_KEY_PREFIX,
                ];

                if ($continuationToken !== null) {
                    $params['ContinuationToken'] = $continuationToken;
                }

                $result = $this->client->listObjectsV2($params);
                $objects = $result['Contents'] ?? [];

                foreach ($objects as $object) {
                    if (!is_array($object)) {
                        continue;
                    }

                    $key = (string) ($object['Key'] ?? '');

                    if (!$this->isValidBackupKey($key)) {
                        continue;
                    }

                    $lastModified = $object['LastModified'] ?? null;
                    $timestamp = $lastModified instanceof \DateTimeInterface
                        ? $lastModified->getTimestamp()
                        : null;

                    if ($timestamp === null || $timestamp >= $cutoff) {
                        continue;
                    }

                    $this->client->deleteObject([
                        'Bucket' => $this->config['bucket'],
                        'Key' => $key,
                    ]);

                    $deletedCount++;
                }

                $continuationToken = $result['IsTruncated'] ?? false
                    ? (string) ($result['NextContinuationToken'] ?? '')
                    : null;

                if ($continuationToken === '') {
                    $continuationToken = null;
                }
            } while ($continuationToken !== null);
        } catch (AwsException $exception) {
            $this->log('backup.r2.purge_failed', [
                'error' => $exception->getAwsErrorMessage() ?: $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'Failed to purge expired backups from Cloudflare R2: ' . ($exception->getAwsErrorMessage() ?: $exception->getMessage()),
                0,
                $exception
            );
        }

        if ($deletedCount > 0) {
            $this->log('backup.r2.purge_success', [
                'deleted_count' => $deletedCount,
                'retention_days' => $retentionDays,
            ]);
        }

        return $deletedCount;
    }

    private function createClient(): S3Client
    {
        $accountId = $this->config['account_id'];

        return new S3Client([
            'version' => 'latest',
            'region' => 'auto',
            'endpoint' => $accountId !== ''
                ? 'https://' . $accountId . '.r2.cloudflarestorage.com'
                : 'https://r2.cloudflarestorage.com',
            'credentials' => [
                'key' => $this->config['access_key_id'],
                'secret' => $this->config['secret_access_key'],
            ],
        ]);
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException(
                'Cloudflare R2 is not configured. Set R2_ACCOUNT_ID, R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, and R2_BUCKET_NAME in .env.'
            );
        }
    }

    private function isValidBackupKey(string $key): bool
    {
        return preg_match(self::BACKUP_FILENAME_PATTERN, $key) === 1;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $message, array $context = []): void
    {
        $this->appLogger?->log($message, $context);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        }

        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        return round($bytes / 1073741824, 2) . ' GB';
    }
}
