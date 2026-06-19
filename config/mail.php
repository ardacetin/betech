<?php

declare(strict_types=1);

$supportAddresses = array_values(array_filter(array_map(
    static fn (string $entry): string => strtolower(trim($entry)),
    explode(',', (string) ($_ENV['MAIL_SUPPORT_TO'] ?? ''))
)));

return [
    'enabled' => filter_var($_ENV['MAIL_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
    'host' => trim((string) ($_ENV['MAIL_HOST'] ?? '')),
    'port' => (int) ($_ENV['MAIL_PORT'] ?? 587),
    'username' => trim((string) ($_ENV['MAIL_USERNAME'] ?? '')),
    'password' => (string) ($_ENV['MAIL_PASSWORD'] ?? ''),
    'encryption' => strtolower(trim((string) ($_ENV['MAIL_ENCRYPTION'] ?? 'tls'))),
    'from_address' => strtolower(trim((string) ($_ENV['MAIL_FROM_ADDRESS'] ?? ''))),
    'from_name' => trim((string) ($_ENV['MAIL_FROM_NAME'] ?? 'Betech ITMS')),
    'support_addresses' => $supportAddresses,
];
