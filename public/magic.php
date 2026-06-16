<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Medoo\Medoo;

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

try {
    $databaseConfig = require $rootPath . '/config/database.php';
    $db = new Medoo($databaseConfig);

    $db->query('ALTER TABLE users MODIFY COLUMN password_hash VARCHAR(255)');

    $adminEmail = 'admin@betech.local';
    $plainPassword = '123456';

    $db->delete('users', [
        'email' => $adminEmail,
    ]);

    $insertPayload = [
        'name' => 'Sistem Yöneticisi',
        'email' => $adminEmail,
        'password_hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
        'role' => 'super_admin',
    ];

    $providerColumn = $db->query("SHOW COLUMNS FROM users LIKE 'provider'");

    if ($providerColumn !== false && $providerColumn->rowCount() > 0) {
        $insertPayload['provider'] = 'local';
    }

    $db->insert('users', $insertPayload);

    $user = $db->get('users', [
        'id',
        'email',
        'password_hash',
        'role',
    ], [
        'email' => $adminEmail,
    ]);

    if ($user === null) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Hata: admin@betech.local oluşturulamadı.';
        exit(1);
    }

    $storedHash = (string) ($user['password_hash'] ?? '');

    if ($storedHash === '' || strlen($storedHash) < 60 || !password_verify($plainPassword, $storedHash)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Hata: password_hash sütunu hâlâ geçersiz veya kısaltılmış.';
        exit(1);
    }

    session_start();
    session_regenerate_id(true);

    $_SESSION['auth_user_id'] = (int) $user['id'];
    $_SESSION['auth_user_role'] = 'super_admin';
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header('Location: /');
    exit;
} catch (Throwable $exception) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Hata: ' . $exception->getMessage();
    exit(1);
}
