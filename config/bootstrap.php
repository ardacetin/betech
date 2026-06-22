<?php

declare(strict_types=1);

use App\Controllers\AnalyticsController;
use App\Controllers\AssetController;
use App\Controllers\AuditLogController;
use App\Controllers\BackupController;
use App\Controllers\CategoryController;
use App\Controllers\IpNetworkController;
use App\Controllers\LicenseController;
use App\Controllers\LocationController;
use App\Controllers\AssetTutanakController;
use App\Controllers\AssetViewController;
use App\Controllers\AuthController;
use App\Controllers\ConsumableController;
use App\Controllers\KnowledgeBaseController;
use App\Controllers\DashboardController;
use App\Controllers\EndUserController;
use App\Controllers\TicketController;
use App\Controllers\HealthController;
use App\Controllers\InventoryImportController;
use App\Controllers\SettingsController;
use App\Controllers\UserController;
use App\Handlers\HttpErrorHandler;
use App\Http\HttpErrorResponses;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\EndUserMiddleware;
use App\Middleware\LanguageMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Consumable;
use App\Models\KnowledgeBaseArticle;
use App\Models\IpAddress;
use App\Models\IpNetwork;
use App\Models\License;
use App\Models\Ticket;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Personnel;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\AppLogger;
use App\Services\AssetCsvImportService;
use App\Services\AuditChangeFormatter;
use App\Services\AuditLogger;
use App\Services\Auth\LdapAuthenticator;
use App\Services\Auth\SessionAuthService;
use App\Services\Auth\UserIntegrationFactory;
use App\Services\ClientIpResolver;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseService;
use App\Services\R2BackupStorage;
use App\Services\EndUserContextService;
use App\Services\InventoryImportService;
use App\Services\IpAddressGenerator;
use App\Services\IpamCsvImportService;
use App\Services\LoginAttemptService;
use App\Services\Mail\MailConfigResolver;
use App\Services\Mail\MailService;
use App\Services\Mail\TicketNotificationService;
use App\Services\QrCodeService;
use App\Services\Translator;
use App\Services\ViewRenderer;
use App\Services\ZimmetTutanakService;
use Dotenv\Dotenv;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;

$rootPath = dirname(__DIR__);

$dotenv = Dotenv::createImmutable($rootPath);
$dotenv->safeLoad();

$appConfig = require $rootPath . '/config/app.php';
$databaseConfig = require $rootPath . '/config/database.php';

$isHttps = request_is_https();

$databaseService = new DatabaseService($databaseConfig);
$loginAttemptService = new LoginAttemptService($databaseService);
$clientIpResolver = new ClientIpResolver($appConfig['trusted_proxies']);

$app = AppFactory::create();

$translator = new Translator($rootPath . '/lang');
Translator::initialize($translator);

$viewRenderer = new ViewRenderer($rootPath . '/views');
$httpErrorResponses = new HttpErrorResponses($viewRenderer);

$app->add(new LanguageMiddleware($translator));
$app->addBodyParsingMiddleware();

$userModel = new User($databaseService);
$personnelModel = new Personnel($databaseService);
$sessionAuthService = new SessionAuthService();
$endUserContextService = new EndUserContextService($sessionAuthService, $userModel, $personnelModel);
$publicPaths = [
    '/login',
    '/api/login',
    '/logout',
    '/assets/view/{id}',
];
$app->add(new RoleMiddleware($sessionAuthService, $httpErrorResponses, $publicPaths, RoleMiddleware::defaultRules()));
$app->add(new AuthMiddleware($sessionAuthService, $publicPaths, $personnelModel));
$app->add(new CsrfMiddleware($sessionAuthService, ['/login', '/api/login']));
$app->add(new RateLimitMiddleware($loginAttemptService, $clientIpResolver));
$app->add(new SecurityHeadersMiddleware($isHttps));

$displayErrorDetails = $appConfig['display_error_details'] && !$appConfig['is_production'];
$appLogger = new AppLogger($rootPath . '/logs', 'app.log', $clientIpResolver);
$appLogger->registerGlobalHandlers();
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);
$errorHandler = new HttpErrorHandler(
    $app->getCallableResolver(),
    $app->getResponseFactory(),
    $appLogger,
    $httpErrorResponses,
    $isHttps
);
$errorMiddleware->setDefaultErrorHandler($errorHandler);
$errorMiddleware->setErrorHandler(HttpNotFoundException::class, $errorHandler);
$errorMiddleware->setErrorHandler(HttpForbiddenException::class, $errorHandler);

$assetModel = new Asset($databaseService);
$assetHistoryModel = new AssetHistory($databaseService);
$categoryModel = new Category($databaseService);
$locationModel = new Location($databaseService);
$licenseModel = new License($databaseService);
$ipAddressGenerator = new IpAddressGenerator();
$ipNetworkModel = new IpNetwork($databaseService, $ipAddressGenerator);
$ipAddressModel = new IpAddress($databaseService);
$ipamCsvImportService = new IpamCsvImportService($ipNetworkModel, $ipAddressModel, $assetModel, $ipAddressGenerator);
$consumableModel = new Consumable($databaseService);
$knowledgeBaseArticleModel = new KnowledgeBaseArticle($databaseService);
$settingModel = new Setting($databaseService);
$userIntegrationFactory = new UserIntegrationFactory($databaseService, $settingModel);
$qrCodeService = new QrCodeService($appConfig['url']);
$analyticsService = new AnalyticsService($databaseService);
$zimmetTutanakService = new ZimmetTutanakService();
$assetCsvImportService = new AssetCsvImportService($assetModel, $categoryModel, $locationModel);
$inventoryImportService = new InventoryImportService($assetModel, $categoryModel, $locationModel, $personnelModel);
$ldapAuthenticator = new LdapAuthenticator($settingModel);
$auditLogModel = new AuditLog($databaseService);
$auditChangeFormatter = new AuditChangeFormatter();
$auditLogger = new AuditLogger($auditLogModel, $auditChangeFormatter, $clientIpResolver);
$inventoryImportController = new InventoryImportController(
    $inventoryImportService,
    $assetHistoryModel,
    $sessionAuthService,
    $auditLogger
);
    $authController = new AuthController(
        $appConfig,
        $personnelModel,
        $sessionAuthService,
        $loginAttemptService,
        $clientIpResolver,
        $ldapAuthenticator,
        $viewRenderer,
        $auditLogger,
        $appLogger
    );
$healthController = new HealthController($appConfig, $assetModel, $categoryModel, $viewRenderer, $qrCodeService, $analyticsService, $settingModel, $userModel, $personnelModel, $sessionAuthService, $endUserContextService);
$assetController = new AssetController($assetModel, $assetHistoryModel, $userIntegrationFactory, $personnelModel, $userModel, $locationModel, $categoryModel, $assetCsvImportService, $sessionAuthService, $clientIpResolver, $endUserContextService, $auditLogger);
$assetViewController = new AssetViewController($appConfig, $assetModel, $categoryModel, $viewRenderer);
$assetTutanakController = new AssetTutanakController($assetModel, $settingModel, $personnelModel, $userModel, $userIntegrationFactory, $zimmetTutanakService, $viewRenderer, $sessionAuthService, $endUserContextService);
$userController = new UserController($userIntegrationFactory, $personnelModel, $assetModel, $assetHistoryModel, $settingModel, $sessionAuthService, $clientIpResolver);
$analyticsController = new AnalyticsController($analyticsService);
$dashboardController = new DashboardController($analyticsService, $assetHistoryModel, $ipNetworkModel, $auditLogModel);
$mailConfigResolver = new MailConfigResolver($settingModel);
$mailService = new MailService($mailConfigResolver, $appLogger);
$settingsController = new SettingsController(
    $settingModel,
    $mailService,
    $mailConfigResolver,
    $viewRenderer,
    $sessionAuthService,
    $userModel,
    $appConfig['url'],
    $auditLogger
);
$categoryController = new CategoryController($categoryModel, $sessionAuthService, $auditLogger);
$locationController = new LocationController($locationModel);
$licenseController = new LicenseController($licenseModel);
$ipNetworkController = new IpNetworkController($ipNetworkModel, $ipAddressModel, $assetModel, $ipamCsvImportService);
$consumableController = new ConsumableController($consumableModel);
$knowledgeBaseController = new KnowledgeBaseController($knowledgeBaseArticleModel, $sessionAuthService, $appLogger);
$ticketModel = new Ticket($databaseService);
$ticketNotificationService = new TicketNotificationService(
    $mailService,
    $mailConfigResolver,
    $viewRenderer,
    $userModel,
    $appLogger,
    $appConfig['url']
);
$ticketController = new TicketController(
    $ticketModel,
    $userModel,
    $assetModel,
    $sessionAuthService,
    $endUserContextService,
    $ticketNotificationService,
    $auditLogger
);
$endUserController = new EndUserController($assetModel, $endUserContextService);
$auditLogController = new AuditLogController($auditLogModel, $auditChangeFormatter);
/** @var array<string, string> $r2Config */
$r2Config = require $rootPath . '/config/r2.php';
$r2BackupStorage = new R2BackupStorage($r2Config, $appLogger);
$databaseBackupService = new DatabaseBackupService($databaseConfig, $r2BackupStorage, $appLogger);
$backupController = new BackupController($databaseBackupService, $sessionAuthService, $auditLogger);

$adminMiddleware = new AdminMiddleware($sessionAuthService, $userModel, $httpErrorResponses);
$endUserMiddleware = new EndUserMiddleware($sessionAuthService, $userModel, $httpErrorResponses);

$app->get('/login', [$authController, 'showLoginForm']);
$app->post('/login', [$authController, 'login']);
$app->post('/api/login', [$authController, 'apiLogin']);
$app->get('/logout', [$authController, 'logout']);
$app->get('/unauthorized', [$authController, 'showUnauthorized']);
$app->get('/', [$healthController, 'index']);
$app->get('/assets/view/{id}', [$assetViewController, 'show']);

$app->group('', function ($group) use ($endUserController): void {
    $group->get('/api/my/assets', [$endUserController, 'assets']);
})->add($endUserMiddleware);

// Helpdesk tickets + published knowledge base: Auth + RoleMiddleware only (no AdminMiddleware).
$app->group('', function ($group) use ($ticketController, $assetTutanakController, $assetController, $knowledgeBaseController): void {
    $group->get('/api/knowledge-base/published', [$knowledgeBaseController, 'published']);
    $group->get('/api/tickets', [$ticketController, 'index']);
    $group->post('/api/tickets', [$ticketController, 'store']);
    $group->get('/api/tickets/{id}', [$ticketController, 'show']);
    $group->post('/api/tickets/{id}/comments', [$ticketController, 'addComment']);
    $group->get('/api/assets/{id}/tutanak', [$assetTutanakController, 'show']);
    $group->get('/api/assets/{id}/history', [$assetController, 'history']);
});

$app->group('', function ($group) use (
    $analyticsController,
    $dashboardController,
    $settingsController,
    $backupController,
    $auditLogController,
    $categoryController,
    $locationController,
    $licenseController,
    $ipNetworkController,
    $consumableController,
    $knowledgeBaseController,
    $ticketController,
    $userController,
    $assetController,
    $inventoryImportController
): void {
    $group->get('/api/analytics/summary', [$analyticsController, 'summary']);
    $group->get('/api/dashboard/stats', [$dashboardController, 'stats']);
    $group->get('/api/settings', [$settingsController, 'show']);
    $group->put('/api/settings', [$settingsController, 'update']);
    $group->post('/api/settings/smtp/test', [$settingsController, 'sendTestSmtp']);
    $group->get('/api/backups', [$backupController, 'index']);
    $group->post('/api/backups', [$backupController, 'store']);
    $group->get('/api/backups/{filename}/download', [$backupController, 'download']);
    $group->get('/api/audit-logs', [$auditLogController, 'index']);
    $group->get('/api/categories', [$categoryController, 'index']);
    $group->post('/api/categories', [$categoryController, 'store']);
    $group->put('/api/categories/{id}', [$categoryController, 'update']);
    $group->delete('/api/categories/{id}', [$categoryController, 'destroy']);
    $group->get('/api/locations', [$locationController, 'index']);
    $group->post('/api/locations', [$locationController, 'store']);
    $group->put('/api/locations/{id}', [$locationController, 'update']);
    $group->delete('/api/locations/{id}', [$locationController, 'destroy']);
    $group->get('/api/licenses', [$licenseController, 'index']);
    $group->post('/api/licenses', [$licenseController, 'store']);
    $group->get('/api/licenses/{id}', [$licenseController, 'show']);
    $group->put('/api/licenses/{id}', [$licenseController, 'update']);
    $group->delete('/api/licenses/{id}', [$licenseController, 'destroy']);
    $group->post('/api/licenses/{id}/assign', [$licenseController, 'assign']);
    $group->post('/api/licenses/{id}/unassign', [$licenseController, 'unassign']);
    $group->get('/api/licenses/{id}/assignments', [$licenseController, 'assignments']);
    $group->get('/api/ip-networks', [$ipNetworkController, 'index']);
    $group->post('/api/ip-networks', [$ipNetworkController, 'store']);
    $group->get('/api/ip-networks/import/template', [$ipNetworkController, 'networkImportTemplate']);
    $group->post('/api/ip-networks/import', [$ipNetworkController, 'importNetworks']);
    $group->get('/api/ip-addresses/import/template', [$ipNetworkController, 'addressImportTemplate']);
    $group->post('/api/ip-addresses/import', [$ipNetworkController, 'importAddresses']);
    $group->get('/api/ip-networks/{id}', [$ipNetworkController, 'show']);
    $group->put('/api/ip-networks/{id}', [$ipNetworkController, 'update']);
    $group->delete('/api/ip-networks/{id}', [$ipNetworkController, 'destroy']);
    $group->get('/api/ip-networks/{id}/addresses', [$ipNetworkController, 'addresses']);
    $group->get('/api/ip-networks/{id}/export', [$ipNetworkController, 'exportNetworkAddresses']);
    $group->post('/api/ip-networks/{id}/generate', [$ipNetworkController, 'generateAddresses']);
    $group->put('/api/ip-addresses/{id}', [$ipNetworkController, 'updateAddress']);
    $group->get('/api/consumables', [$consumableController, 'index']);
    $group->post('/api/consumables', [$consumableController, 'store']);
    $group->get('/api/consumables/{id}', [$consumableController, 'show']);
    $group->put('/api/consumables/{id}', [$consumableController, 'update']);
    $group->delete('/api/consumables/{id}', [$consumableController, 'destroy']);
    $group->post('/api/consumables/{id}/checkout', [$consumableController, 'checkout']);
    $group->post('/api/consumables/{id}/restock', [$consumableController, 'restock']);
    $group->get('/api/knowledge-base', [$knowledgeBaseController, 'index']);
    $group->post('/api/knowledge-base', [$knowledgeBaseController, 'store']);
    $group->get('/api/knowledge-base/{id}', [$knowledgeBaseController, 'show']);
    $group->put('/api/knowledge-base/{id}', [$knowledgeBaseController, 'update']);
    $group->delete('/api/knowledge-base/{id}', [$knowledgeBaseController, 'destroy']);
    $group->put('/api/tickets/{id}', [$ticketController, 'update']);
    $group->delete('/api/tickets/{id}', [$ticketController, 'destroy']);
    $group->get('/api/assets/{id}/licenses', [$licenseController, 'forAsset']);
    $group->get('/api/personnel', [$userController, 'personnelIndex']);
    $group->post('/api/personnel', [$userController, 'storePersonnel']);
    $group->post('/api/personnel/sync', [$userController, 'personnelSync']);
    $group->post('/api/personnel/sync-ldap', [$userController, 'personnelSyncLdap']);
    $group->get('/api/personnel/search', [$userController, 'searchPersonnel']);
    $group->post('/api/personnel/{id}/offboard', [$userController, 'offboard']);
    $group->put('/api/personnel/{id}/role', [$userController, 'updatePersonnelRole']);
    $group->post('/api/assets', [$assetController, 'store']);
    $group->get('/api/assets/import/template', [$assetController, 'importTemplate']);
    $group->get('/api/assets/export', [$assetController, 'exportCsv']);
    $group->post('/api/assets/import', [$assetController, 'importCsv']);
    $group->get('/api/inventory/import/template', [$inventoryImportController, 'template']);
    $group->post('/api/inventory/import', [$inventoryImportController, 'import']);
    $group->put('/api/assets/{id}', [$assetController, 'update']);
    $group->post('/api/assets/{id}/assign', [$assetController, 'assign']);
    $group->post('/api/assets/{id}/return', [$assetController, 'returnToStorage']);
    $group->post('/api/assets/{id}/transfer', [$assetController, 'transfer']);
    $group->delete('/api/assets/{id}', [$assetController, 'destroy']);
})->add($adminMiddleware);

return [
    'app' => $app,
    'db' => $databaseService,
];
