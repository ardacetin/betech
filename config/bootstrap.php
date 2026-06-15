<?php

declare(strict_types=1);

use App\Controllers\AssetController;
use App\Controllers\HealthController;
use App\Models\Asset;
use App\Models\Category;
use App\Services\DatabaseService;
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

$app->addBodyParsingMiddleware();

$app->addErrorMiddleware(
    $appConfig['debug'],
    $appConfig['debug'],
    $appConfig['debug']
);

$assetModel = new Asset($databaseService);
$categoryModel = new Category($databaseService);
$viewRenderer = new ViewRenderer($rootPath . '/views');
$healthController = new HealthController($appConfig, $assetModel, $categoryModel, $viewRenderer);
$assetController = new AssetController($assetModel);

$app->get('/', [$healthController, 'index']);
$app->post('/api/assets', [$assetController, 'store']);

return [
    'app' => $app,
    'db' => $databaseService,
];
