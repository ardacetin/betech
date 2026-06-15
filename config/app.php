<?php

declare(strict_types=1);

$environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? 'local')));

$trustedProxies = array_values(array_filter(
    array_map('trim', explode(',', (string) ($_ENV['TRUSTED_PROXIES'] ?? '*'))),
    static fn (string $entry): bool => $entry !== ''
));

if ($trustedProxies === []) {
    $trustedProxies = ['*'];
}

return [
    'env' => $environment,
    'is_production' => $environment === 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'display_error_details' => filter_var($_ENV['DISPLAY_ERROR_DETAILS'] ?? $_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost:8080',
    'trusted_proxies' => $trustedProxies,
];
