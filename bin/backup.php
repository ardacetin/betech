#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Access Denied: CLI only\n");
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

use App\Services\AppLogger;
use App\Services\ClientIpResolver;
use App\Services\DatabaseBackupService;
use App\Services\R2BackupStorage;
use Dotenv\Dotenv;

$rootPath = dirname(__DIR__);

Dotenv::createImmutable($rootPath)->safeLoad();

/** @var array<string, mixed> $appConfig */
$appConfig = require $rootPath . '/config/app.php';
/** @var array<string, mixed> $databaseConfig */
$databaseConfig = require $rootPath . '/config/database.php';
/** @var array<string, string> $r2Config */
$r2Config = require $rootPath . '/config/r2.php';

$clientIpResolver = new ClientIpResolver($appConfig['trusted_proxies']);
$appLogger = new AppLogger($rootPath . '/logs', 'app.log', $clientIpResolver);

$service = new DatabaseBackupService(
    $databaseConfig,
    new R2BackupStorage($r2Config, $appLogger),
    $appLogger
);

$result = $service->run();

if ($result['success']) {
    echo $result['message'] . "\n";
    exit(0);
}

fwrite(STDERR, 'Error: ' . $result['message'] . "\n");
exit(1);
