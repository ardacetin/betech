<?php

declare(strict_types=1);

use App\Controllers\AnalyticsController;
use App\Controllers\AssetController;
use App\Controllers\AssetTutanakController;
use App\Controllers\AssetViewController;
use App\Controllers\AuthController;
use App\Controllers\HealthController;
use App\Controllers\SettingsController;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;
use App\Middleware\LanguageMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\Auth\LdapAuthenticator;
use App\Services\Auth\OAuthService;
use App\Services\Auth\SessionAuthService;
use App\Services\Auth\UserIntegrationFactory;
use App\Services\DatabaseService;
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

$databaseService = new DatabaseService($databaseConfig);

$app = AppFactory::create();

$translator = new Translator($rootPath . '/lang');
Translator::initialize($translator);

$app->add(new LanguageMiddleware($translator));
$app->addBodyParsingMiddleware();

$userModel = new User($databaseService);
$sessionAuthService = new SessionAuthService();
$publicPaths = [
    '/login',
    '/logout',
    '/auth/oauth/{provider}',
    '/auth/callback/{provider}',
    '/assets/view/{id}',
];
$app->add(new RoleMiddleware($sessionAuthService, $publicPaths, RoleMiddleware::defaultRules()));
$app->add(new AuthMiddleware($sessionAuthService, $publicPaths, $userModel));

$app->addErrorMiddleware(
    $appConfig['debug'],
    $appConfig['debug'],
    $appConfig['debug']
);

$assetModel = new Asset($databaseService);
$assetHistoryModel = new AssetHistory($databaseService);
$categoryModel = new Category($databaseService);
$settingModel = new Setting($databaseService);
$userIntegrationFactory = new UserIntegrationFactory($databaseService, $settingModel);
$viewRenderer = new ViewRenderer($rootPath . '/views');
$qrCodeService = new QrCodeService($appConfig['url']);
$analyticsService = new AnalyticsService($databaseService);
$zimmetTutanakService = new ZimmetTutanakService();
$ldapAuthenticator = new LdapAuthenticator($settingModel);
$oauthService = new OAuthService($settingModel, $appConfig['url']);
$authController = new AuthController(
    $appConfig,
    $settingModel,
    $userModel,
    $sessionAuthService,
    $ldapAuthenticator,
    $oauthService,
    $viewRenderer
);
$healthController = new HealthController($appConfig, $assetModel, $categoryModel, $viewRenderer, $qrCodeService, $analyticsService, $settingModel, $userModel, $sessionAuthService);
$assetController = new AssetController($assetModel, $assetHistoryModel, $userIntegrationFactory, $userModel, $sessionAuthService);
$assetViewController = new AssetViewController($appConfig, $assetModel, $categoryModel, $viewRenderer);
$assetTutanakController = new AssetTutanakController($assetModel, $settingModel, $userModel, $userIntegrationFactory, $zimmetTutanakService, $viewRenderer, $sessionAuthService);
$userController = new UserController($userIntegrationFactory, $userModel, $assetModel, $assetHistoryModel, $settingModel);
$analyticsController = new AnalyticsController($analyticsService);
$settingsController = new SettingsController($settingModel);

$app->get('/login', [$authController, 'showLoginForm']);
$app->post('/login', [$authController, 'login']);
$app->get('/logout', [$authController, 'logout']);
$app->get('/auth/oauth/{provider}', [$authController, 'startOAuth']);
$app->get('/auth/callback/{provider}', [$authController, 'handleOAuthCallback']);
$app->get('/', [$healthController, 'index']);
$app->get('/assets/view/{id}', [$assetViewController, 'show']);
$app->get('/api/analytics/summary', [$analyticsController, 'summary']);
$app->get('/api/settings', [$settingsController, 'show']);
$app->put('/api/settings', [$settingsController, 'update']);
$app->get('/api/users', [$userController, 'index']);
$app->get('/api/users/search', [$userController, 'search']);
$app->post('/api/users/{id}/offboard', [$userController, 'offboard']);
$app->get('/api/assets/{id}/tutanak', [$assetTutanakController, 'show']);
$app->post('/api/assets', [$assetController, 'store']);
$app->put('/api/assets/{id}', [$assetController, 'update']);
$app->post('/api/assets/{id}/return', [$assetController, 'returnToStorage']);
$app->post('/api/assets/{id}/transfer', [$assetController, 'transfer']);
$app->delete('/api/assets/{id}', [$assetController, 'destroy']);
$app->get('/api/assets/{id}/history', [$assetController, 'history']);

return [
    'app' => $app,
    'db' => $databaseService,
];
