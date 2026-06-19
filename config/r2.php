<?php

declare(strict_types=1);

return [
    'account_id' => trim((string) ($_ENV['R2_ACCOUNT_ID'] ?? '')),
    'access_key_id' => trim((string) ($_ENV['R2_ACCESS_KEY_ID'] ?? '')),
    'secret_access_key' => (string) ($_ENV['R2_SECRET_ACCESS_KEY'] ?? ''),
    'bucket' => trim((string) ($_ENV['R2_BUCKET_NAME'] ?? 'beykoz-betech')),
];
