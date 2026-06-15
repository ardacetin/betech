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
    $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

    $updatePayload = [
        'password_hash' => $passwordHash,
        'role' => 'super_admin',
        'auth_provider' => 'local',
        'provider_subject' => null,
        'status' => 'active',
    ];

    if ($db->has('users', ['email' => $adminEmail])) {
        $db->update('users', $updatePayload, [
            'email' => $adminEmail,
        ]);
    } else {
        $db->insert('users', [
            'external_id' => 'admin',
            'name' => 'Sistem Yöneticisi',
            'email' => $adminEmail,
            'department' => 'IT',
            ...$updatePayload,
        ]);
    }

    $stored = $db->get('users', [
        'password_hash',
        'role',
        'auth_provider',
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

    echo 'Admin hesabı sıfırlandı. Lütfen giriş yapın ve bu dosyayı silin.';
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Hata: ' . $exception->getMessage();
    exit(1);
}
