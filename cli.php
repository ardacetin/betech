#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Access Denied: CLI only\n");
    exit(1);
}

require __DIR__ . '/vendor/autoload.php';

use App\Models\Personnel;
use App\Services\DatabaseService;
use Dotenv\Dotenv;

$rootPath = __DIR__;

Dotenv::createImmutable($rootPath)->safeLoad();

/** @var array<string, mixed> $databaseConfig */
$databaseConfig = require $rootPath . '/config/database.php';
$databaseService = new DatabaseService($databaseConfig);
$personnelModel = new Personnel($databaseService);

$command = strtolower(trim((string) ($argv[1] ?? '')));

if ($command !== 'make:admin') {
    fwrite(STDERR, "Usage: php cli.php make:admin <username>\n");
    exit(1);
}

$username = trim((string) ($argv[2] ?? ''));

if ($username === '') {
    fwrite(STDERR, "Error: Username is required.\n");
    exit(1);
}

$person = $personnelModel->promoteToAdminByUsername($username);

if ($person === null) {
    fwrite(STDERR, "Error: Personnel record not found for username '{$username}'. Sign in once via LDAP first.\n");
    exit(1);
}

echo sprintf(
    "Success: '%s' (%s) is now a system administrator.\n",
    (string) ($person['name'] ?? $username),
    (string) ($person['email'] ?? '')
);
