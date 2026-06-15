<?php

declare(strict_types=1);

use App\Controllers\AssetController;
use App\Controllers\HealthController;
use App\Controllers\UserController;
use App\Middleware\LanguageMiddleware;
use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\Category;
use App\Services\Auth\UserIntegrationFactory;
use App\Services\DatabaseService;
use App\Services\Translator;
use App\Services\ViewRenderer;
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

$app->addErrorMiddleware(
    $appConfig['debug'],
    $appConfig['debug'],
    $appConfig['debug']
);

$assetModel = new Asset($databaseService);
$assetHistoryModel = new AssetHistory($databaseService);
$categoryModel = new Category($databaseService);
$userIntegrationFactory = new UserIntegrationFactory($databaseService);
$viewRenderer = new ViewRenderer($rootPath . '/views');
$healthController = new HealthController($appConfig, $assetModel, $categoryModel, $viewRenderer);
$assetController = new AssetController($assetModel, $assetHistoryModel, $userIntegrationFactory);
$userController = new UserController($userIntegrationFactory);

$app->get('/', [$healthController, 'index']);
$app->get('/api/users/search', [$userController, 'search']);
$app->post('/api/assets', [$assetController, 'store']);
$app->put('/api/assets/{id}', [$assetController, 'update']);
$app->get('/api/assets/{id}/history', [$assetController, 'history']);

return [
    'app' => $app,
    'db' => $databaseService,
];
