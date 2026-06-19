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
        private readonly string $backupDirectory,
        private readonly array $databaseConfig,
        private readonly ?AppLogger $appLogger = null
    ) {
    }

    /**
     * @return array{success: bool, message: string, filename?: string, size_bytes?: int, deleted_count?: int}
     */
    public function run(): array
    {
        $this->ensureBackupDirectory();

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
        $sqlPath = $this->backupDirectory . '/backup_' . $timestamp . '.sql';
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
        } finally {
            @unlink($defaultsFile);
            @unlink($sqlPath);
        }

        $deletedCount = $this->purgeExpiredBackups();
        $archiveSize = filesize($archivePath);

        if ($archiveSize === false) {
            return $this->failure('Unable to read backup file size.');
        }

        $filename = basename($archivePath);

        $this->log('backup.database.success', [
            'filename' => $filename,
            'size_bytes' => $archiveSize,
            'deleted_count' => $deletedCount,
        ]);

        return [
            'success' => true,
            'message' => sprintf(
                'Database backup created at %s (%s). Retention cleanup removed %d file(s) older than %d days.',
                $filename,
                $this->formatBytes($archiveSize),
                $deletedCount,
                self::RETENTION_DAYS
            ),
            'filename' => $filename,
            'size_bytes' => $archiveSize,
            'deleted_count' => $deletedCount,
        ];
    }

    /**
     * @return list<array{filename: string, size_bytes: int, size_label: string, created_at: string}>
     */
    public function listBackups(): array
    {
        $this->ensureBackupDirectory();

        $files = glob($this->backupDirectory . '/backup_*.sql.gz');

        if ($files === false) {
            return [];
        }

        $backups = [];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $filename = basename($file);

            if (!$this->isValidBackupFilename($filename)) {
                continue;
            }

            $sizeBytes = filesize($file);
            $modifiedAt = filemtime($file);

            if ($sizeBytes === false || $modifiedAt === false) {
                continue;
            }

            $backups[] = [
                'filename' => $filename,
                'size_bytes' => $sizeBytes,
                'size_label' => $this->formatBytes($sizeBytes),
                'created_at' => date('Y-m-d H:i:s', $modifiedAt),
            ];
        }

        usort(
            $backups,
            static fn (array $left, array $right): int => strcmp($right['created_at'], $left['created_at'])
        );

        return $backups;
    }

    public function resolveBackupPath(string $filename): ?string
    {
        if (!$this->isValidBackupFilename($filename)) {
            return null;
        }

        $path = $this->backupDirectory . '/' . $filename;

        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $realBackupDir = realpath($this->backupDirectory);
        $realFile = realpath($path);

        if ($realBackupDir === false || $realFile === false) {
            return null;
        }

        if (!str_starts_with($realFile, $realBackupDir . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realFile;
    }

    public function purgeExpiredBackups(): int
    {
        $deletedCount = 0;
        $cutoff = time() - (self::RETENTION_DAYS * 86400);
        $files = glob($this->backupDirectory . '/backup_*.sql.gz');

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $filename = basename($file);

            if (!$this->isValidBackupFilename($filename)) {
                continue;
            }

            $modifiedAt = filemtime($file);

            if ($modifiedAt !== false && $modifiedAt < $cutoff && @unlink($file)) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    private function ensureBackupDirectory(): void
    {
        if (is_dir($this->backupDirectory)) {
            return;
        }

        if (!mkdir($this->backupDirectory, 0750, true) && !is_dir($this->backupDirectory)) {
            throw new RuntimeException('Unable to create backup directory: ' . $this->backupDirectory);
        }

        $htaccessPath = $this->backupDirectory . '/.htaccess';

        if (!is_file($htaccessPath)) {
            file_put_contents($htaccessPath, "Deny from all\n");
        }
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
