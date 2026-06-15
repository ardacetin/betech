<?php

declare(strict_types=1);

use App\Controllers\HealthController;
use App\Services\DatabaseService;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

$rootPath = dirname(__DIR__);

$dotenv = Dotenv::createImmutable($rootPath);
$dotenv->safeLoad();

$appConfig = require $rootPath . '/config/app.php';
$databaseConfig = require $rootPath . '/config/database.php';

$databaseService = new DatabaseService($databaseConfig);

$app = AppFactory::create();

$app->addErrorMiddleware(
    $appConfig['debug'],
    $appConfig['debug'],
    $appConfig['debug']
);

$healthController = new HealthController($appConfig);

$app->get('/', [$healthController, 'index']);

return [
    'app' => $app,
    'db' => $databaseService,
];
