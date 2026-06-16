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

    $db->delete('users', [
        'email' => $adminEmail,
    ]);

    $db->insert('users', [
        'name' => 'Sistem Yöneticisi',
        'email' => $adminEmail,
        'password_hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
        'role' => 'super_admin',
    ]);

    $stored = $db->get('users', [
        'password_hash',
        'role',
    ], [
        'email' => $adminEmail,
    ]);

    if ($stored === null) {
        http_response_code(500);
        echo 'Hata: admin@betech.local kaydı oluşturulamadı.';
        exit(1);
    }

    $storedHash = (string) ($stored['password_hash'] ?? '');

    if ($storedHash === '' || !password_verify($plainPassword, $storedHash)) {
        http_response_code(500);
        echo 'Hata: Şifre doğrulanamadı. Veritabanı güncellemesini kontrol edin.';
        exit(1);
    }

    echo 'Balyoz operasyonu tamamlandı. Admin hesabı sıfırdan oluşturuldu. Şifre: 123456';
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Hata: ' . $exception->getMessage();
    exit(1);
}
