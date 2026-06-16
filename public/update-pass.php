<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Medoo\Medoo;

header('Content-Type: text/plain; charset=utf-8');

try {
    $rootPath = dirname(__DIR__);
    Dotenv::createImmutable($rootPath)->safeLoad();

    $databaseConfig = require $rootPath . '/config/database.php';
    $db = new Medoo($databaseConfig);

    $adminEmail = 'admin@betech.local';
    $plainPassword = '123456';

    $db->update('users', [
        'password_hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
    ], [
        'email' => $adminEmail,
    ]);

    if (!$db->has('users', ['email' => $adminEmail])) {
        http_response_code(404);
        echo 'Hata: admin@betech.local bulunamadı.';
        exit(1);
    }

    $stored = $db->get('users', ['password_hash'], ['email' => $adminEmail]);
    $storedHash = (string) ($stored['password_hash'] ?? '');

    if ($storedHash === '' || !password_verify($plainPassword, $storedHash)) {
        http_response_code(500);
        echo 'Hata: Şifre güncellenemedi veya doğrulanamadı.';
        exit(1);
    }

    echo 'Şifre kesin olarak güncellendi. Artık 123456 ile giriş yapabilirsiniz.';
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Hata: ' . $exception->getMessage();
    exit(1);
}
