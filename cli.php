#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Access Denied: CLI only\n");
    exit(1);
}

require __DIR__ . '/vendor/autoload.php';

use App\Commands\FetchEmailsCommand;
use App\Models\Asset;
use App\Models\AssetComponent;
use App\Models\AssetCustomField;
use App\Models\AssetRegistry;
use App\Models\AssetsGlobalRegistry;
use App\Models\AssetType;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\Consumable;
use App\Models\IpNetwork;
use App\Models\License;
use App\Models\Personnel;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AppLogger;
use App\Services\AssetColumnSchemaService;
use App\Services\AssetMutationLogger;
use App\Services\AssetTypeTableService;
use App\Services\AuditChangeFormatter;
use App\Services\AuditLogger;
use App\Services\Auth\SessionAuthService;
use App\Services\Automation\AutomationEngine;
use App\Services\ClientIpResolver;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseService;
use App\Services\DdlIdentifierGuard;
use App\Services\EndUserContextService;
use App\Services\FileStorageCache;
use App\Services\IpAddressGenerator;
use App\Services\R2BackupStorage;
use App\Services\Mail\DailySummaryNotificationService;
use App\Services\Mail\ImapConfigResolver;
use App\Services\Mail\ImapInboxFetcher;
use App\Services\Mail\InboundEmailTicketService;
use App\Services\Mail\MailConfigResolver;
use App\Services\Mail\MailService;
use App\Services\Notifications\HealthScannerNotificationService;
use App\Services\Notifications\TelegramNotifier;
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
        "Success: '%s' (%s) is now a Bilgi İşlem administrator.\n",
        (string) ($person['name'] ?? $username),
        (string) ($person['email'] ?? '')
    );

    exit(0);
}

if ($command === 'mail:fetch_inbox') {
    $settingModel = new Setting($databaseService);
    $mailConfigResolver = new MailConfigResolver($settingModel);
    $imapConfigResolver = new ImapConfigResolver($settingModel, $mailConfigResolver);
    $imapInboxFetcher = new ImapInboxFetcher($imapConfigResolver, $appLogger);
    $service = new InboundEmailTicketService(
        $imapInboxFetcher,
        new Personnel($databaseService),
        new Ticket($databaseService),
        $appLogger
    );
    $commandRunner = new FetchEmailsCommand($service);
    exit($commandRunner->run());
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

if ($command === 'notify:health_scan') {
    $settingModel = new Setting($databaseService);
    $mailConfigResolver = new MailConfigResolver($settingModel);
    $mailService = new MailService($mailConfigResolver, $appLogger);
    $viewRenderer = new ViewRenderer($rootPath . '/views');
    $ipAddressGenerator = new IpAddressGenerator();

    $service = new HealthScannerNotificationService(
        new IpNetwork($databaseService, $ipAddressGenerator),
        new License($databaseService),
        new Consumable($databaseService),
        $settingModel,
        new Personnel($databaseService),
        $mailService,
        new TelegramNotifier($appLogger),
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

if ($command === 'automation:run') {
    $settingModel = new Setting($databaseService);
    $mailConfigResolver = new MailConfigResolver($settingModel);
    $mailService = new MailService($mailConfigResolver, $appLogger);
    $viewRenderer = new ViewRenderer($rootPath . '/views');
    $ddlIdentifierGuard = new DdlIdentifierGuard();
    $fileStorageCache = new FileStorageCache($rootPath . '/storage/cache');
    $assetTypeTableService = new AssetTypeTableService($databaseService, $ddlIdentifierGuard, $fileStorageCache);
    $assetTypeModel = new AssetType($databaseService, $assetTypeTableService, $ddlIdentifierGuard);
    $assetRegistryModel = new AssetRegistry($databaseService);
    $assetsGlobalRegistryModel = new AssetsGlobalRegistry($databaseService);
    $assetCustomFieldModel = new AssetCustomField($databaseService, $assetTypeTableService, $assetTypeModel, $ddlIdentifierGuard);
    $assetComponentModel = new AssetComponent($databaseService, $assetTypeModel, $assetTypeTableService);
    $userModel = new User($databaseService);
    $personnelModel = new Personnel($databaseService);
    $endUserContextService = new EndUserContextService(new SessionAuthService(), $userModel, $personnelModel);
    $auditLogger = new AuditLogger(new AuditLog($databaseService), new AuditChangeFormatter(), $clientIpResolver);
    $assetMutationLogger = new AssetMutationLogger($auditLogger, $endUserContextService, $userModel, $assetTypeModel);
    $assetColumnSchemaService = new AssetColumnSchemaService(
        $databaseService,
        $settingModel,
        $assetTypeTableService,
        $assetCustomFieldModel,
        $assetComponentModel,
        $fileStorageCache
    );
    $assetModel = new Asset(
        $databaseService,
        $assetColumnSchemaService,
        $assetTypeTableService,
        $assetRegistryModel,
        $assetsGlobalRegistryModel,
        $assetTypeModel,
        $assetMutationLogger
    );

    $engine = new AutomationEngine(
        new AutomationRule($databaseService),
        new License($databaseService),
        new Consumable($databaseService),
        $assetModel,
        $settingModel,
        $personnelModel,
        $userModel,
        $mailService,
        $mailConfigResolver,
        $viewRenderer,
        $appLogger,
        (string) $appConfig['url']
    );

    $result = $engine->runScheduled();

    if ($result['skipped'] && !$result['success']) {
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

fwrite(STDERR, "Usage:\n");
fwrite(STDERR, "  php cli.php make:admin <username>\n");
fwrite(STDERR, "  php cli.php mail:fetch_inbox\n");
fwrite(STDERR, "  php cli.php notify:daily_summary\n");
fwrite(STDERR, "  php cli.php notify:health_scan\n");
fwrite(STDERR, "  php cli.php backup:database\n");
fwrite(STDERR, "  php cli.php automation:run\n");
exit(1);
