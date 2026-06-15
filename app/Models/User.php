<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class User
{
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_TECHNICIAN = 'technician';
    public const ROLE_END_USER = 'end_user';
    /** @deprecated Personnel records live in the personnel table; kept for session/RBAC compatibility. */
    public const ROLE_PERSONNEL = 'end_user';

    public const PROVIDER_LOCAL = 'local';
    public const PROVIDER_LDAP = 'ldap';
    public const PROVIDER_GOOGLE = 'google';
    public const PROVIDER_MICROSOFT = 'microsoft';

    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllSystemUsers(): array
    {
        $rows = $this->db()->select('users', [
            'id',
            'name',
            'email',
            'role',
        ], [
            'role' => [self::ROLE_SUPER_ADMIN, self::ROLE_TECHNICIAN],
            'ORDER' => ['name' => 'ASC'],
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function createSystemUser(string $name, string $email, string $role, string $password): array
    {
        $trimmedName = trim($name);
        $normalizedEmail = strtolower(trim($email));
        $normalizedRole = $this->normalizeRole($role);

        if ($trimmedName === '') {
            throw new \InvalidArgumentException(__('system_user_name_required'));
        }

        if ($normalizedEmail === '' || filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException(__('system_user_email_invalid'));
        }

        if (!$this->isOperationalRole($normalizedRole)) {
            throw new \InvalidArgumentException(__('system_user_role_invalid'));
        }

        if (strlen($password) < 8) {
            throw new \InvalidArgumentException(__('system_user_password_required'));
        }

        if ($this->findByEmail($normalizedEmail) !== null) {
            throw new \InvalidArgumentException(__('manual_user_email_taken'));
        }

        $this->db()->insert('users', [
            'name' => $trimmedName,
            'email' => $normalizedEmail,
            'role' => $normalizedRole,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        $created = $this->findSystemUserById((int) $this->db()->id());

        if ($created === null) {
            throw new \RuntimeException(__('system_user_create_error'));
        }

        return $created;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function updateSystemUser(
        int $userId,
        ?string $role = null,
        ?string $name = null,
        ?string $email = null,
        ?string $password = null
    ): ?array {
        $existing = $this->findSystemUserById($userId);

        if ($existing === null) {
            return null;
        }

        $payload = [];

        if ($name !== null) {
            $trimmedName = trim($name);

            if ($trimmedName === '') {
                throw new \InvalidArgumentException(__('system_user_name_required'));
            }

            $payload['name'] = $trimmedName;
        }

        if ($email !== null) {
            $normalizedEmail = strtolower(trim($email));

            if ($normalizedEmail === '' || filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException(__('system_user_email_invalid'));
            }

            $duplicate = $this->findByEmail($normalizedEmail);

            if ($duplicate !== null && (int) $duplicate['id'] !== $userId) {
                throw new \InvalidArgumentException(__('manual_user_email_taken'));
            }

            $payload['email'] = $normalizedEmail;
        }

        if ($role !== null) {
            $normalizedRole = $this->normalizeRole($role);

            if (!$this->isOperationalRole($normalizedRole)) {
                throw new \InvalidArgumentException(__('system_user_role_invalid'));
            }

            if ((string) $existing['role'] === self::ROLE_SUPER_ADMIN
                && $normalizedRole !== self::ROLE_SUPER_ADMIN
                && $this->countUsersByRole(self::ROLE_SUPER_ADMIN) <= 1) {
                throw new \InvalidArgumentException(__('system_user_last_super_admin'));
            }

            $payload['role'] = $normalizedRole;
        }

        $trimmedPassword = $password !== null ? trim($password) : null;

        if ($trimmedPassword !== null && $trimmedPassword !== '') {
            if (strlen($trimmedPassword) < 8) {
                throw new \InvalidArgumentException(__('system_user_password_required'));
            }

            $payload['password_hash'] = password_hash($trimmedPassword, PASSWORD_DEFAULT);
        }

        if ($payload !== []) {
            $this->db()->update('users', $payload, ['id' => $userId]);
        }

        return $this->findSystemUserById($userId);
    }

    public function deleteSystemUser(int $userId): bool
    {
        $existing = $this->findSystemUserById($userId);

        if ($existing === null) {
            return false;
        }

        if ((string) $existing['role'] === self::ROLE_SUPER_ADMIN
            && $this->countUsersByRole(self::ROLE_SUPER_ADMIN) <= 1) {
            throw new \InvalidArgumentException(__('system_user_last_super_admin'));
        }

        $this->db()->delete('users', [
            'id' => $userId,
        ]);

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSystemUserById(int $userId): ?array
    {
        $row = $this->db()->get('users', [
            'id',
            'name',
            'email',
            'role',
        ], [
            'id' => $userId,
        ]);

        if ($row === null) {
            return null;
        }

        $normalized = $this->normalizeRow($row);

        if (!$this->isOperationalRole((string) $normalized['role'])) {
            return null;
        }

        return $normalized;
    }

    public function countUsersByRole(string $role): int
    {
        return $this->db()->count('users', [
            'role' => $this->normalizeRole($role),
        ]);
    }

    public function findById(int $userId): ?array
    {
        $row = $this->db()->get('users', [
            'id',
            'name',
            'email',
            'role',
        ], [
            'id' => $userId,
        ]);

        return $row === null ? null : $this->normalizeRow($row);
    }

    public function findByEmail(string $email): ?array
    {
        $normalizedEmail = strtolower(trim($email));

        if ($normalizedEmail === '') {
            return null;
        }

        $row = $this->db()->get('users', [
            'id',
            'name',
            'email',
            'password_hash',
            'role',
        ], [
            'email' => $normalizedEmail,
        ]);

        return $row === null ? null : $this->normalizeRow($row);
    }

    public function updatePasswordHash(int $userId, string $plainPassword): void
    {
        if ($userId <= 0 || $plainPassword === '') {
            return;
        }

        $this->db()->update('users', [
            'password_hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
        ], [
            'id' => $userId,
        ]);
    }

    public function findRoleById(int $userId): string
    {
        $role = $this->db()->get('users', 'role', ['id' => $userId]);

        return $this->normalizeRole(is_string($role) ? $role : null);
    }

    public function isOperationalRole(string $role): bool
    {
        return in_array($role, [self::ROLE_SUPER_ADMIN, self::ROLE_TECHNICIAN], true);
    }

    public function isSuperAdmin(string $role): bool
    {
        return $role === self::ROLE_SUPER_ADMIN;
    }

    public function normalizeRole(?string $role): string
    {
        return self::normalizeRoleStatic($role);
    }

    public static function normalizeRoleStatic(?string $role): string
    {
        $normalized = strtolower(trim((string) $role));

        return match ($normalized) {
            self::ROLE_SUPER_ADMIN, self::ROLE_TECHNICIAN => $normalized,
            default => self::ROLE_END_USER,
        };
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['role'] = $this->normalizeRole(isset($row['role']) ? (string) $row['role'] : null);

        return $row;
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
