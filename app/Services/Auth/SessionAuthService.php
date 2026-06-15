<?php

declare(strict_types=1);

namespace App\Services\Auth;

class SessionAuthService
{
    private const SESSION_USER_ID = 'auth_user_id';

    public function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public function login(int $userId): void
    {
        $this->ensureSessionStarted();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_ID] = $userId;
    }

    public function logout(): void
    {
        $this->ensureSessionStarted();
        unset($_SESSION[self::SESSION_USER_ID]);
        session_regenerate_id(true);
    }

    public function isAuthenticated(): bool
    {
        $this->ensureSessionStarted();

        return isset($_SESSION[self::SESSION_USER_ID]) && (int) $_SESSION[self::SESSION_USER_ID] > 0;
    }

    public function userId(): ?int
    {
        $this->ensureSessionStarted();

        if (!isset($_SESSION[self::SESSION_USER_ID])) {
            return null;
        }

        $userId = (int) $_SESSION[self::SESSION_USER_ID];

        return $userId > 0 ? $userId : null;
    }
}
