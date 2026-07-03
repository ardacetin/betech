#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Access Denied: CLI only\n");
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

use App\Models\IpAddress;
use App\Services\DatabaseService;
use App\Services\PingHeartbeatService;
use Dotenv\Dotenv;

$rootPath = dirname(__DIR__);

Dotenv::createImmutable($rootPath)->safeLoad();

/** @var array<string, mixed> $databaseConfig */
$databaseConfig = require $rootPath . '/config/database.php';

$databaseService = new DatabaseService($databaseConfig);
$ipAddressModel = new IpAddress($databaseService);
$service = new PingHeartbeatService($ipAddressModel);

try {
    $result = $service->run();
    echo sprintf(
        "Ping heartbeat complete: %d checked, %d online, %d offline\n",
        $result['checked'],
        $result['online'],
        $result['offline']
    );
    exit(0);
} catch (\Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n");
    exit(1);
}
