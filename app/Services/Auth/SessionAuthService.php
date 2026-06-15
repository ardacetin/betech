<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;

class SessionAuthService
{
    private const SESSION_USER_ID = 'auth_user_id';
    private const SESSION_USER_ROLE = 'auth_user_role';

    public function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public function login(int $userId, string $role = User::ROLE_END_USER): void
    {
        $this->ensureSessionStarted();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_ID] = $userId;
        $_SESSION[self::SESSION_USER_ROLE] = User::normalizeRoleStatic($role);
    }

    public function logout(): void
    {
        $this->ensureSessionStarted();
        unset($_SESSION[self::SESSION_USER_ID], $_SESSION[self::SESSION_USER_ROLE]);
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

    public function role(): string
    {
        $this->ensureSessionStarted();

        if (isset($_SESSION[self::SESSION_USER_ROLE])) {
            return User::normalizeRoleStatic((string) $_SESSION[self::SESSION_USER_ROLE]);
        }

        return User::ROLE_END_USER;
    }

    public function setRole(string $role): void
    {
        $this->ensureSessionStarted();
        $_SESSION[self::SESSION_USER_ROLE] = User::normalizeRoleStatic($role);
    }

    /**
     * @param list<string> $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role(), $roles, true);
    }
}
