<?php

declare(strict_types=1);

/**
 * Emergency one-shot: reset admin password and establish a dashboard session.
 * DELETE THIS FILE immediately after use.
 */

require __DIR__ . '/../vendor/autoload.php';

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
$db = $bootstrap['db']->getConnection();

$adminEmail = 'admin@betech.local';
$newHash = password_hash('Betech2026!', PASSWORD_DEFAULT);

$db->update('users', ['password_hash' => $newHash], ['email' => $adminEmail]);

$adminId = $db->get('users', 'id', ['email' => $adminEmail]);
$adminRole = $db->get('users', 'role', ['email' => $adminEmail]) ?? 'super_admin';

if ($adminId === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Admin user not found: ' . $adminEmail;
    exit(1);
}

session_start();

$userId = (int) $adminId;
$role = (string) $adminRole;

// Keys used by SessionAuthService (dashboard auth).
$_SESSION['auth_user_id'] = $userId;
$_SESSION['auth_user_role'] = $role;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Legacy / requested session keys (harmless if unused).
$_SESSION['user_id'] = $userId;
$_SESSION['role'] = $role;

header('Location: /');
exit;
