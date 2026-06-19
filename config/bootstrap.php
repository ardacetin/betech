<?php

declare(strict_types=1);

use App\Controllers\AnalyticsController;
use App\Controllers\AssetController;
use App\Controllers\AuditLogController;
use App\Controllers\CategoryController;
use App\Controllers\IpNetworkController;
use App\Controllers\LicenseController;
use App\Controllers\LocationController;
use App\Controllers\AssetTutanakController;
use App\Controllers\AssetViewController;
use App\Controllers\AuthController;
use App\Controllers\ConsumableController;
use App\Controllers\DashboardController;
use App\Controllers\EndUserController;
use App\Controllers\TicketController;
use App\Controllers\HealthController;
use App\Controllers\SettingsController;
use App\Controllers\UserController;
use App\Handlers\HttpErrorHandler;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\LanguageMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Consumable;
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
use App\Services\DatabaseService;
use App\Services\EndUserContextService;
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
$app->add(new RoleMiddleware($sessionAuthService, $publicPaths, RoleMiddleware::defaultRules()));
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
    $isHttps
);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

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
$settingModel = new Setting($databaseService);
$userIntegrationFactory = new UserIntegrationFactory($databaseService, $settingModel);
$viewRenderer = new ViewRenderer($rootPath . '/views');
$qrCodeService = new QrCodeService($appConfig['url']);
$analyticsService = new AnalyticsService($databaseService);
$zimmetTutanakService = new ZimmetTutanakService();
$assetCsvImportService = new AssetCsvImportService($assetModel, $categoryModel, $locationModel);
$ldapAuthenticator = new LdapAuthenticator($settingModel);
$auditLogModel = new AuditLog($databaseService);
$auditChangeFormatter = new AuditChangeFormatter();
$auditLogger = new AuditLogger($auditLogModel, $auditChangeFormatter, $clientIpResolver);
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
$userController = new UserController($userIntegrationFactory, $userModel, $personnelModel, $assetModel, $assetHistoryModel, $settingModel, $sessionAuthService, $clientIpResolver);
$analyticsController = new AnalyticsController($analyticsService);
$dashboardController = new DashboardController($analyticsService, $assetHistoryModel);
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

$app->get('/login', [$authController, 'showLoginForm']);
$app->post('/login', [$authController, 'login']);
$app->post('/api/login', [$authController, 'apiLogin']);
$app->get('/logout', [$authController, 'logout']);
$app->get('/', [$healthController, 'index']);
$app->get('/api/my/assets', [$endUserController, 'assets']);
$app->get('/assets/view/{id}', [$assetViewController, 'show']);
$app->get('/api/analytics/summary', [$analyticsController, 'summary']);
$app->get('/api/dashboard/stats', [$dashboardController, 'stats']);
$app->get('/api/settings', [$settingsController, 'show']);
$app->put('/api/settings', [$settingsController, 'update']);
$app->post('/api/settings/smtp/test', [$settingsController, 'sendTestSmtp']);
$app->get('/api/audit-logs', [$auditLogController, 'index']);
$app->get('/api/categories', [$categoryController, 'index']);
$app->post('/api/categories', [$categoryController, 'store']);
$app->put('/api/categories/{id}', [$categoryController, 'update']);
$app->delete('/api/categories/{id}', [$categoryController, 'destroy']);
$app->get('/api/locations', [$locationController, 'index']);
$app->post('/api/locations', [$locationController, 'store']);
$app->put('/api/locations/{id}', [$locationController, 'update']);
$app->delete('/api/locations/{id}', [$locationController, 'destroy']);
$app->get('/api/licenses', [$licenseController, 'index']);
$app->post('/api/licenses', [$licenseController, 'store']);
$app->get('/api/licenses/{id}', [$licenseController, 'show']);
$app->put('/api/licenses/{id}', [$licenseController, 'update']);
$app->delete('/api/licenses/{id}', [$licenseController, 'destroy']);
$app->post('/api/licenses/{id}/assign', [$licenseController, 'assign']);
$app->post('/api/licenses/{id}/unassign', [$licenseController, 'unassign']);
$app->get('/api/licenses/{id}/assignments', [$licenseController, 'assignments']);
$app->get('/api/ip-networks', [$ipNetworkController, 'index']);
$app->post('/api/ip-networks', [$ipNetworkController, 'store']);
$app->get('/api/ip-networks/import/template', [$ipNetworkController, 'networkImportTemplate']);
$app->post('/api/ip-networks/import', [$ipNetworkController, 'importNetworks']);
$app->get('/api/ip-addresses/import/template', [$ipNetworkController, 'addressImportTemplate']);
$app->post('/api/ip-addresses/import', [$ipNetworkController, 'importAddresses']);
$app->get('/api/ip-networks/{id}', [$ipNetworkController, 'show']);
$app->put('/api/ip-networks/{id}', [$ipNetworkController, 'update']);
$app->delete('/api/ip-networks/{id}', [$ipNetworkController, 'destroy']);
$app->get('/api/ip-networks/{id}/addresses', [$ipNetworkController, 'addresses']);
$app->get('/api/ip-networks/{id}/export', [$ipNetworkController, 'exportNetworkAddresses']);
$app->post('/api/ip-networks/{id}/generate', [$ipNetworkController, 'generateAddresses']);
$app->put('/api/ip-addresses/{id}', [$ipNetworkController, 'updateAddress']);
$app->get('/api/consumables', [$consumableController, 'index']);
$app->post('/api/consumables', [$consumableController, 'store']);
$app->get('/api/consumables/{id}', [$consumableController, 'show']);
$app->put('/api/consumables/{id}', [$consumableController, 'update']);
$app->delete('/api/consumables/{id}', [$consumableController, 'destroy']);
$app->post('/api/consumables/{id}/checkout', [$consumableController, 'checkout']);
$app->post('/api/consumables/{id}/restock', [$consumableController, 'restock']);
$app->get('/api/tickets', [$ticketController, 'index']);
$app->post('/api/tickets', [$ticketController, 'store']);
$app->get('/api/tickets/{id}', [$ticketController, 'show']);
$app->put('/api/tickets/{id}', [$ticketController, 'update']);
$app->delete('/api/tickets/{id}', [$ticketController, 'destroy']);
$app->post('/api/tickets/{id}/comments', [$ticketController, 'addComment']);
$app->get('/api/assets/{id}/licenses', [$licenseController, 'forAsset']);
$app->get('/api/personnel', [$userController, 'personnelIndex']);
$app->post('/api/personnel', [$userController, 'storePersonnel']);
$app->post('/api/personnel/sync', [$userController, 'personnelSync']);
$app->post('/api/personnel/sync-ldap', [$userController, 'personnelSyncLdap']);
$app->get('/api/personnel/search', [$userController, 'searchPersonnel']);
$app->post('/api/personnel/{id}/offboard', [$userController, 'offboard']);
$app->put('/api/personnel/{id}/role', [$userController, 'updatePersonnelRole']);
$app->get('/api/users', [$userController, 'index']);
$app->post('/api/users', [$userController, 'store']);
$app->put('/api/users/{id}', [$userController, 'update']);
$app->delete('/api/users/{id}', [$userController, 'destroy']);
$app->get('/api/assets/{id}/tutanak', [$assetTutanakController, 'show']);
$app->post('/api/assets', [$assetController, 'store']);
$app->get('/api/assets/import/template', [$assetController, 'importTemplate']);
$app->get('/api/assets/export', [$assetController, 'exportCsv']);
$app->post('/api/assets/import', [$assetController, 'importCsv']);
$app->put('/api/assets/{id}', [$assetController, 'update']);
$app->post('/api/assets/{id}/assign', [$assetController, 'assign']);
$app->post('/api/assets/{id}/return', [$assetController, 'returnToStorage']);
$app->post('/api/assets/{id}/transfer', [$assetController, 'transfer']);
$app->delete('/api/assets/{id}', [$assetController, 'destroy']);
$app->get('/api/assets/{id}/history', [$assetController, 'history']);

return [
    'app' => $app,
    'db' => $databaseService,
];
