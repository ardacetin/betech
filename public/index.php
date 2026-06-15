<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Services\DatabaseInitializer;

$bootstrap = require __DIR__ . '/../config/bootstrap.php';

$databaseInitializer = new DatabaseInitializer(
    $bootstrap['db'],
    dirname(__DIR__) . '/database/schema.sql'
);

$initializationResult = $databaseInitializer->initialize();

if (!$initializationResult->isSuccessful()) {
    $message = $initializationResult->getMessage() ?? 'Database initialization failed.';

    error_log('[Betech] ' . $message);

    http_response_code(503);
    header('Content-Type: application/json');

    echo json_encode([
        'status' => 'error',
        'message' => $message,
    ], JSON_THROW_ON_ERROR);

    exit(1);
}

$bootstrap['app']->run();
