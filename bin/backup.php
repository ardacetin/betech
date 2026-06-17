<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die('Access Denied: CLI only');
}

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$rootPath = dirname(__DIR__);

Dotenv::createImmutable($rootPath)->safeLoad();

/** @var array<string, mixed> $databaseConfig */
$databaseConfig = require $rootPath . '/config/database.php';

$host = trim((string) ($databaseConfig['host'] ?? ''));
$port = (int) ($databaseConfig['port'] ?? 3306);
$database = trim((string) ($databaseConfig['database'] ?? ''));
$username = trim((string) ($databaseConfig['username'] ?? ''));
$password = (string) ($databaseConfig['password'] ?? '');

if ($host === '' || $database === '' || $username === '') {
    fwrite(
        STDERR,
        "Error: Database credentials are incomplete. Set DB_HOST, DB_DATABASE, and DB_USERNAME in .env.\n"
    );
    exit(1);
}

$backupDir = $rootPath . '/storage/backups';

if (!is_dir($backupDir) && !mkdir($backupDir, 0750, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Error: Unable to create backup directory: {$backupDir}\n");
    exit(1);
}

$htaccessPath = $backupDir . '/.htaccess';

if (!is_file($htaccessPath)) {
    $written = file_put_contents($htaccessPath, "Deny from all\n");

    if ($written === false) {
        fwrite(STDERR, "Error: Unable to secure backup directory with .htaccess.\n");
        exit(1);
    }
}

$mysqldump = resolveExecutable('mysqldump');

if ($mysqldump === null) {
    fwrite(STDERR, "Error: mysqldump was not found. Install the MySQL client tools.\n");
    exit(1);
}

$gzip = resolveExecutable('gzip');

if ($gzip === null) {
    fwrite(STDERR, "Error: gzip was not found.\n");
    exit(1);
}

$timestamp = date('Y-m-d_H-i-s');
$sqlPath = $backupDir . '/backup_' . $timestamp . '.sql';
$archivePath = $sqlPath . '.gz';
$defaultsFile = createMysqlDefaultsFile($host, $port, $username, $password);

try {
    $dumpResult = runMysqldump($mysqldump, $defaultsFile, $database, $sqlPath);

    if ($dumpResult['exit_code'] !== 0) {
        @unlink($sqlPath);
        fwrite(STDERR, "Error: mysqldump failed.\n");

        if ($dumpResult['stderr'] !== '') {
            fwrite(STDERR, $dumpResult['stderr'] . "\n");
        }

        exit(1);
    }

    if (!is_file($sqlPath) || filesize($sqlPath) === 0) {
        @unlink($sqlPath);
        fwrite(STDERR, "Error: mysqldump produced an empty backup file.\n");
        exit(1);
    }

    $gzipCommand = sprintf('%s -9 %s', escapeshellarg($gzip), escapeshellarg($sqlPath));
    $gzipOutput = [];
    $gzipExitCode = 0;
    exec($gzipCommand . ' 2>&1', $gzipOutput, $gzipExitCode);

    if ($gzipExitCode !== 0 || !is_file($archivePath)) {
        @unlink($sqlPath);
        @unlink($archivePath);
        fwrite(STDERR, "Error: gzip compression failed.\n");

        if ($gzipOutput !== []) {
            fwrite(STDERR, implode("\n", $gzipOutput) . "\n");
        }

        exit(1);
    }
} finally {
    @unlink($defaultsFile);
    @unlink($sqlPath);
}

$deletedCount = purgeExpiredBackups($backupDir, 14);
$archiveSize = filesize($archivePath);

if ($archiveSize === false) {
    fwrite(STDERR, "Error: Unable to read backup file size.\n");
    exit(1);
}

echo sprintf(
    "Success: Database backup created at %s (%s). Retention cleanup removed %d file(s) older than 14 days.\n",
    basename($archivePath),
    formatBytes($archiveSize),
    $deletedCount
);

/**
 * @return array{exit_code: int, stderr: string}
 */
function runMysqldump(string $mysqldump, string $defaultsFile, string $database, string $sqlPath): array
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

function createMysqlDefaultsFile(string $host, int $port, string $username, string $password): string
{
    $defaultsFile = tempnam(sys_get_temp_dir(), 'betech_mysqldump_');

    if ($defaultsFile === false) {
        fwrite(STDERR, "Error: Unable to create temporary MySQL credentials file.\n");
        exit(1);
    }

    $config = sprintf(
        "[client]\nhost=%s\nport=%d\nuser=%s\npassword=%s\n",
        escapeMysqlConfigValue($host),
        $port,
        escapeMysqlConfigValue($username),
        escapeMysqlConfigValue($password)
    );

    if (file_put_contents($defaultsFile, $config) === false) {
        @unlink($defaultsFile);
        fwrite(STDERR, "Error: Unable to write temporary MySQL credentials file.\n");
        exit(1);
    }

    chmod($defaultsFile, 0600);

    return $defaultsFile;
}

function escapeMysqlConfigValue(string $value): string
{
    if (preg_match('/[\s#;"\'\\\\]/', $value) === 1) {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    return $value;
}

function resolveExecutable(string $binary): ?string
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

function purgeExpiredBackups(string $backupDir, int $retentionDays): int
{
    $deletedCount = 0;
    $cutoff = time() - ($retentionDays * 86400);
    $pattern = $backupDir . '/backup_*.sql.gz';
    $files = glob($pattern);

    if ($files === false) {
        return 0;
    }

    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }

        $modifiedAt = filemtime($file);

        if ($modifiedAt !== false && $modifiedAt < $cutoff) {
            if (@unlink($file)) {
                $deletedCount++;
            }
        }
    }

    return $deletedCount;
}

function formatBytes(int $bytes): string
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
