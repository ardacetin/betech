<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Services\DatabaseInitializer;
use Dotenv\Dotenv;

$rootPath = dirname(__DIR__);

Dotenv::createImmutable($rootPath)->safeLoad();

$isHttps = request_is_https();

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

$bootstrap = require __DIR__ . '/../config/bootstrap.php';

$databaseInitializer = new DatabaseInitializer(
    $bootstrap['db'],
    dirname(__DIR__) . '/database/schema.sql',
    dirname(__DIR__) . '/database/seeds.sql'
);

$initializationResult = $databaseInitializer->initialize();

if (!$initializationResult->isSuccessful()) {
    $message = $initializationResult->getMessage() ?? 'Database initialization failed.';
    $isProduction = strtolower(trim((string) ($_ENV['APP_ENV'] ?? 'local'))) === 'production';

    error_log('[Betech] ' . $message);

    http_response_code(503);
    header('Content-Type: application/json');

    echo json_encode([
        'status' => 'error',
        'message' => $isProduction ? 'Veritabanı başlatılamadı. Lütfen sistem yöneticisiyle iletişime geçin.' : $message,
    ], JSON_THROW_ON_ERROR);

    exit(1);
}

foreach ($initializationResult->getWarnings() as $warning) {
    error_log('[Betech] Database warning: ' . $warning);
}

$bootstrap['app']->run();
