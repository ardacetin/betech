<?php

declare(strict_types=1);

return [
    'site_key' => (string) ($_ENV['TURNSTILE_SITE_KEY'] ?? '0x4AAAAAACLf0FH4wQScyWEe'),
    'secret_key' => (string) ($_ENV['TURNSTILE_SECRET_KEY'] ?? '0x4AAAAAACLf0Hl7sJa8xo-8wxSDJ8fCx88'),
];
