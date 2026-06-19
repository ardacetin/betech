#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Access Denied: CLI only\n");
    exit(1);
}

require __DIR__ . '/vendor/autoload.php';

use App\Models\Consumable;
use App\Models\License;
use App\Models\Personnel;
use App\Models\Setting;
use App\Models\Ticket;
use App\Services\AppLogger;
use App\Services\ClientIpResolver;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseService;
use App\Services\R2BackupStorage;
use App\Services\Mail\DailySummaryNotificationService;
use App\Services\Mail\MailConfigResolver;
use App\Services\Mail\MailService;
use App\Services\Translator;
use App\Services\ViewRenderer;
use Dotenv\Dotenv;

$rootPath = __DIR__;

Dotenv::createImmutable($rootPath)->safeLoad();

/** @var array<string, mixed> $appConfig */
$appConfig = require $rootPath . '/config/app.php';
/** @var array<string, mixed> $databaseConfig */
$databaseConfig = require $rootPath . '/config/database.php';

$translator = new Translator($rootPath . '/lang');
Translator::initialize($translator);
$translator->setLocale('tr');

$databaseService = new DatabaseService($databaseConfig);
$clientIpResolver = new ClientIpResolver($appConfig['trusted_proxies']);
$appLogger = new AppLogger($rootPath . '/logs', 'app.log', $clientIpResolver);

$command = strtolower(trim((string) ($argv[1] ?? '')));

if ($command === 'make:admin') {
    $personnelModel = new Personnel($databaseService);
    $username = trim((string) ($argv[2] ?? ''));

    if ($username === '') {
        fwrite(STDERR, "Error: Username is required.\n");
        exit(1);
    }

    $person = $personnelModel->promoteToAdminByUsername($username);

    if ($person === null) {
        fwrite(STDERR, "Error: Personnel record not found for username '{$username}'. Sign in once via LDAP first.\n");
        exit(1);
    }

    echo sprintf(
        "Success: '%s' (%s) is now a system administrator.\n",
        (string) ($person['name'] ?? $username),
        (string) ($person['email'] ?? '')
    );

    exit(0);
}

if ($command === 'notify:daily_summary') {
    $settingModel = new Setting($databaseService);
    $mailConfigResolver = new MailConfigResolver($settingModel);
    $mailService = new MailService($mailConfigResolver, $appLogger);
    $viewRenderer = new ViewRenderer($rootPath . '/views');

    $service = new DailySummaryNotificationService(
        new License($databaseService),
        new Consumable($databaseService),
        new Ticket($databaseService),
        $settingModel,
        new Personnel($databaseService),
        $mailService,
        $viewRenderer,
        $appLogger,
        $appConfig['url']
    );

    $result = $service->run();

    if ($result['skipped']) {
        echo $result['message'] . "\n";
        exit(0);
    }

    if ($result['success']) {
        echo $result['message'] . "\n";
        exit(0);
    }

    fwrite(STDERR, $result['message'] . "\n");
    exit(1);
}

if ($command === 'backup:database') {
    /** @var array<string, string> $r2Config */
    $r2Config = require $rootPath . '/config/r2.php';
    $backupService = new DatabaseBackupService(
        $databaseConfig,
        new R2BackupStorage($r2Config, $appLogger),
        $appLogger
    );

    $result = $backupService->run();

    if ($result['success']) {
        echo $result['message'] . "\n";
        exit(0);
    }

    fwrite(STDERR, 'Error: ' . $result['message'] . "\n");
    exit(1);
}

fwrite(STDERR, "Usage:\n");
fwrite(STDERR, "  php cli.php make:admin <username>\n");
fwrite(STDERR, "  php cli.php notify:daily_summary\n");
fwrite(STDERR, "  php cli.php backup:database\n");
exit(1);
