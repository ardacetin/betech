<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class DatabaseBackupService
{
    public const RETENTION_DAYS = 7;

    private const BACKUP_FILENAME_PATTERN = '/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql\.gz$/';

    /**
     * @param array<string, mixed> $databaseConfig
     */
    public function __construct(
        private readonly array $databaseConfig,
        private readonly R2BackupStorage $r2Storage,
        private readonly ?AppLogger $appLogger = null
    ) {
    }

    /**
     * @return array{success: bool, message: string, filename?: string, size_bytes?: int, deleted_count?: int}
     */
    public function run(): array
    {
        if (!$this->r2Storage->isConfigured()) {
            return $this->failure(
                'Cloudflare R2 is not configured. Set R2_ACCOUNT_ID, R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, and R2_BUCKET_NAME in .env.'
            );
        }

        $host = trim((string) ($this->databaseConfig['host'] ?? ''));
        $port = (int) ($this->databaseConfig['port'] ?? 3306);
        $database = trim((string) ($this->databaseConfig['database'] ?? ''));
        $username = trim((string) ($this->databaseConfig['username'] ?? ''));
        $password = (string) ($this->databaseConfig['password'] ?? '');

        if ($host === '' || $database === '' || $username === '') {
            return $this->failure('Database credentials are incomplete. Set DB_HOST, DB_DATABASE, and DB_USERNAME.');
        }

        $mysqldump = $this->resolveExecutable('mysqldump');

        if ($mysqldump === null) {
            return $this->failure('mysqldump was not found. Install the MySQL client tools.');
        }

        $gzip = $this->resolveExecutable('gzip');

        if ($gzip === null) {
            return $this->failure('gzip was not found.');
        }

        $timestamp = date('Y-m-d_H-i-s');
        $filename = 'backup_' . $timestamp . '.sql.gz';
        $tempBase = tempnam(sys_get_temp_dir(), 'betech_backup_');

        if ($tempBase === false) {
            return $this->failure('Unable to create temporary backup file.');
        }

        @unlink($tempBase);

        $sqlPath = $tempBase . '.sql';
        $archivePath = $sqlPath . '.gz';
        $defaultsFile = $this->createMysqlDefaultsFile($host, $port, $username, $password);

        try {
            $dumpResult = $this->runMysqldump($mysqldump, $defaultsFile, $database, $sqlPath);

            if ($dumpResult['exit_code'] !== 0) {
                @unlink($sqlPath);

                $message = 'mysqldump failed.';

                if ($dumpResult['stderr'] !== '') {
                    $message .= ' ' . $dumpResult['stderr'];
                }

                return $this->failure($message);
            }

            if (!is_file($sqlPath) || filesize($sqlPath) === 0) {
                @unlink($sqlPath);

                return $this->failure('mysqldump produced an empty backup file.');
            }

            $gzipCommand = sprintf('%s -9 %s', escapeshellarg($gzip), escapeshellarg($sqlPath));
            $gzipOutput = [];
            $gzipExitCode = 0;
            exec($gzipCommand . ' 2>&1', $gzipOutput, $gzipExitCode);

            if ($gzipExitCode !== 0 || !is_file($archivePath)) {
                @unlink($sqlPath);
                @unlink($archivePath);

                $message = 'gzip compression failed.';

                if ($gzipOutput !== []) {
                    $message .= ' ' . implode(' ', $gzipOutput);
                }

                return $this->failure($message);
            }

            $archiveSize = filesize($archivePath);

            if ($archiveSize === false) {
                @unlink($archivePath);

                return $this->failure('Unable to read backup file size.');
            }

            try {
                $this->r2Storage->upload($archivePath, $filename);
            } catch (RuntimeException $exception) {
                return $this->failure($exception->getMessage());
            } finally {
                @unlink($archivePath);
            }

            $deletedCount = $this->purgeExpiredBackups();

            $this->log('backup.database.success', [
                'filename' => $filename,
                'size_bytes' => $archiveSize,
                'deleted_count' => $deletedCount,
                'storage' => 'r2',
            ]);

            return [
                'success' => true,
                'message' => sprintf(
                    'Database backup uploaded to Cloudflare R2 as %s (%s). Retention cleanup removed %d object(s) older than %d days.',
                    $filename,
                    $this->formatBytes($archiveSize),
                    $deletedCount,
                    self::RETENTION_DAYS
                ),
                'filename' => $filename,
                'size_bytes' => $archiveSize,
                'deleted_count' => $deletedCount,
            ];
        } finally {
            @unlink($defaultsFile);
            @unlink($sqlPath);
            @unlink($archivePath);
        }
    }

    /**
     * @return list<array{filename: string, size_bytes: int, size_label: string, created_at: string}>
     */
    public function listBackups(): array
    {
        return $this->r2Storage->listBackups();
    }

    public function createDownloadUrl(string $filename): ?string
    {
        if (!$this->isValidBackupFilename($filename)) {
            return null;
        }

        try {
            return $this->r2Storage->createPresignedDownloadUrl($filename);
        } catch (RuntimeException) {
            return null;
        }
    }

    public function purgeExpiredBackups(): int
    {
        return $this->r2Storage->purgeExpired(self::RETENTION_DAYS);
    }

    private function isValidBackupFilename(string $filename): bool
    {
        return preg_match(self::BACKUP_FILENAME_PATTERN, $filename) === 1;
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function failure(string $message): array
    {
        $this->log('backup.database.failed', [
            'reason' => $message,
        ]);

        return [
            'success' => false,
            'message' => $message,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $message, array $context = []): void
    {
        $this->appLogger?->log($message, $context);
    }

    /**
     * @return array{exit_code: int, stderr: string}
     */
    private function runMysqldump(string $mysqldump, string $defaultsFile, string $database, string $sqlPath): array
    {
        $command = [
            $mysqldump,
            '--defaults-extra-file=' . $defaultsFile,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set=utf8mb4',
            '--result-file=' . $sqlPath,
            $database,
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return [
                'exit_code' => 1,
                'stderr' => 'Unable to start mysqldump process.',
            ];
        }

        fclose($pipes[0]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'exit_code' => $exitCode,
            'stderr' => trim((string) $stderr),
        ];
    }

    private function createMysqlDefaultsFile(string $host, int $port, string $username, string $password): string
    {
        $defaultsFile = tempnam(sys_get_temp_dir(), 'betech_mysqldump_');

        if ($defaultsFile === false) {
            throw new RuntimeException('Unable to create temporary MySQL credentials file.');
        }

        $config = sprintf(
            "[client]\nhost=%s\nport=%d\nuser=%s\npassword=%s\n",
            $this->escapeMysqlConfigValue($host),
            $port,
            $this->escapeMysqlConfigValue($username),
            $this->escapeMysqlConfigValue($password)
        );

        if (file_put_contents($defaultsFile, $config) === false) {
            @unlink($defaultsFile);

            throw new RuntimeException('Unable to write temporary MySQL credentials file.');
        }

        chmod($defaultsFile, 0600);

        return $defaultsFile;
    }

    private function escapeMysqlConfigValue(string $value): string
    {
        if (preg_match('/[\s#;"\'\\\\]/', $value) === 1) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }

        return $value;
    }

    private function resolveExecutable(string $binary): ?string
    {
        $command = PHP_OS_FAMILY === 'Windows'
            ? 'where ' . escapeshellarg($binary)
            : 'command -v ' . escapeshellarg($binary) . ' 2>/dev/null';

        $path = trim((string) shell_exec($command));

        if ($path === '' || !is_executable($path)) {
            return null;
        }

        return $path;
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
