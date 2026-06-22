<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

while (ob_get_level() > 0) {
    ob_end_clean();
}

register_shutdown_function(static function (): void {
    $error = error_get_last();

    if ($error === null) {
        return;
    }

    if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        http_response_code(500);
    }

    echo '<pre style="margin:1rem;padding:1rem;background:#fee;border:1px solid #f99;color:#900;white-space:pre-wrap;">';
    echo 'FATAL: ' . htmlspecialchars((string) $error['message'], ENT_QUOTES, 'UTF-8') . "\n";
    echo 'in ' . htmlspecialchars((string) $error['file'], ENT_QUOTES, 'UTF-8') . ':' . (int) $error['line'];
    echo '</pre>';
});

require __DIR__ . '/../vendor/autoload.php';

use App\Services\DatabaseInitializer;
use App\Services\DeferredTaskRunner;
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
DeferredTaskRunner::run();
