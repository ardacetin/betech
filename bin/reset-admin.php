<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die('Access Denied: CLI only');
}

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Medoo\Medoo;

$rootPath = dirname(__DIR__);

Dotenv::createImmutable($rootPath)->safeLoad();

/** @var array<string, mixed> $databaseConfig */
$databaseConfig = require $rootPath . '/config/database.php';
$db = new Medoo($databaseConfig);

$adminEmail = 'admin@betech.local';
$newHash = password_hash('Betech2026!', PASSWORD_DEFAULT);

if (!$db->has('users', ['email' => $adminEmail])) {
    fwrite(STDERR, "Error: Admin user not found ({$adminEmail}).\n");
    exit(1);
}

$db->update('users', ['password_hash' => $newHash], ['email' => $adminEmail]);

$storedHash = $db->get('users', 'password_hash', ['email' => $adminEmail]);

if (!is_string($storedHash) || !password_verify('Betech2026!', $storedHash)) {
    fwrite(STDERR, "Error: Password update could not be verified.\n");
    exit(1);
}

echo "Success: Admin password securely reset to Betech2026!\n";
