<?php

declare(strict_types=1);

$environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? 'local')));

return [
    'env' => $environment,
    'is_production' => $environment === 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'display_error_details' => filter_var($_ENV['DISPLAY_ERROR_DETAILS'] ?? $_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost:8080',
];
