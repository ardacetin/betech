<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class User
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_OFFBOARDED = 'offboarded';

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_TECHNICIAN = 'technician';
    public const ROLE_END_USER = 'end_user';
    /** @deprecated Alias kept for personnel/asset-holder records (stored as end_user in DB). */
    public const ROLE_PERSONNEL = 'end_user';

    public const PERSONNEL_PAGE_SIZE = 50;

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
    public function findAllForPersonnel(): array
    {
        $rows = $this->db()->select('users', [
            'id',
            'external_id',
            'name',
            'email',
            'department',
            'status',
            'role',
            'auth_provider',
            'created_at',
        ], [
            'role' => self::ROLE_END_USER,
            'ORDER' => ['name' => 'ASC'],
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
    }

    /**
     * @return array{
     *     data: list<array<string, mixed>>,
     *     pagination: array{page: int, per_page: int, total: int, total_pages: int}
     * }
     */
    public function findPersonnelPaginated(int $page, int $perPage, ?string $search = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $conditions = $this->buildPersonnelFilter($search);
        $total = $this->db()->count('users', $conditions);
        $offset = ($page - 1) * $perPage;

        $rows = $this->db()->select('users', [
            'id',
            'external_id',
            'name',
            'email',
            'department',
            'status',
            'role',
            'auth_provider',
            'created_at',
        ], [
            ...$conditions,
            'ORDER' => ['name' => 'ASC'],
            'LIMIT' => [$offset, $perPage],
        ]);

        return [
            'data' => array_map(
                fn (array $row): array => $this->normalizeRow($row),
                $rows
            ),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * @param list<array{id?: string, external_id?: string, name?: string, email?: string, department?: string|null}> $directoryUsers
     *
     * @return array{created: int, updated: int, skipped: int, total: int}
     */
    public function syncPersonnelDirectory(array $directoryUsers, string $authProvider): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'total' => count($directoryUsers),
        ];

        foreach ($directoryUsers as $directoryUser) {
            $outcome = $this->upsertPersonnelFromDirectory($directoryUser, $authProvider);
            $stats[$outcome]++;
        }

        return $stats;
    }

    /**
     * @param array{id?: string, external_id?: string, name?: string, email?: string, department?: string|null} $directoryUser
     *
     * @return 'created'|'updated'|'skipped'
     */
    public function upsertPersonnelFromDirectory(array $directoryUser, string $authProvider): string
    {
        $externalId = trim((string) ($directoryUser['external_id'] ?? $directoryUser['id'] ?? ''));

        if ($externalId === '') {
            return 'skipped';
        }

        $email = strtolower(trim((string) ($directoryUser['email'] ?? '')));

        if ($email === '') {
            $email = sprintf(
                '%s@directory.local',
                preg_replace('/[^a-z0-9._-]+/i', '-', $externalId) ?: 'user'
            );
        }

        $name = trim((string) ($directoryUser['name'] ?? $externalId));
        $department = isset($directoryUser['department']) && $directoryUser['department'] !== null
            ? trim((string) $directoryUser['department'])
            : null;
        $department = $department === '' ? null : $department;
        $normalizedProvider = $this->normalizeAuthProvider($authProvider);

        $existing = $this->db()->get('users', [
            'id',
            'role',
            'status',
            'external_id',
        ], [
            'OR' => [
                'external_id' => $externalId,
                'email' => $email,
            ],
        ]);

        if ($existing !== null && $this->isOperationalRole((string) $existing['role'])) {
            return 'skipped';
        }

        $payload = [
            'name' => $name,
            'email' => $email,
            'department' => $department,
            'role' => self::ROLE_END_USER,
            'auth_provider' => $normalizedProvider,
            'provider_subject' => $externalId,
        ];

        if ($existing !== null) {
            if (($existing['status'] ?? self::STATUS_ACTIVE) === self::STATUS_OFFBOARDED) {
                unset($payload['role']);
            }

            if ((string) ($existing['external_id'] ?? '') === '') {
                $payload['external_id'] = $externalId;
            }

            $this->db()->update('users', $payload, ['id' => (int) $existing['id']]);

            return 'updated';
        }

        $this->db()->insert('users', [
            'external_id' => $externalId,
            'status' => self::STATUS_ACTIVE,
            ...$payload,
        ]);

        return 'created';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPersonnelFilter(?string $search): array
    {
        $conditions = [
            'role' => self::ROLE_END_USER,
        ];

        $trimmedSearch = trim((string) $search);

        if ($trimmedSearch !== '') {
            $conditions['OR'] = [
                'name[~]' => $trimmedSearch,
                'email[~]' => $trimmedSearch,
                'department[~]' => $trimmedSearch,
            ];
        }

        return $conditions;
    }

    private function normalizeAuthProvider(string $authProvider): string
    {
        $normalized = strtolower(trim($authProvider));

        return match ($normalized) {
            self::PROVIDER_LDAP => self::PROVIDER_LDAP,
            self::PROVIDER_GOOGLE => self::PROVIDER_GOOGLE,
            self::PROVIDER_MICROSOFT => self::PROVIDER_MICROSOFT,
            default => self::PROVIDER_LOCAL,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllSystemUsers(): array
    {
        $rows = $this->db()->select('users', [
            'id',
            'external_id',
            'name',
            'email',
            'department',
            'status',
            'role',
            'auth_provider',
            'last_login_at',
            'created_at',
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

        $externalId = 'sys-' . bin2hex(random_bytes(8));

        $this->db()->insert('users', [
            'external_id' => $externalId,
            'name' => $trimmedName,
            'email' => $normalizedEmail,
            'department' => null,
            'status' => self::STATUS_ACTIVE,
            'role' => $normalizedRole,
            'auth_provider' => self::PROVIDER_LOCAL,
            'provider_subject' => null,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        $created = $this->findById((int) $this->db()->id());

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
        $existing = $this->findById($userId);

        if ($existing === null || !$this->isOperationalRole((string) $existing['role'])) {
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

        if ($password !== null && $password !== '') {
            if (strlen($password) < 8) {
                throw new \InvalidArgumentException(__('system_user_password_required'));
            }

            $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ($payload !== []) {
            $this->db()->update('users', $payload, ['id' => $userId]);
        }

        return $this->findById($userId);
    }

    public function countUsersByRole(string $role): int
    {
        return $this->db()->count('users', [
            'role' => $this->normalizeRole($role),
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    public function findById(int $userId): ?array
    {
        $row = $this->db()->get('users', [
            'id',
            'external_id',
            'name',
            'email',
            'department',
            'status',
            'role',
            'created_at',
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
            'external_id',
            'name',
            'email',
            'department',
            'status',
            'auth_provider',
            'provider_subject',
            'password_hash',
            'role',
            'created_at',
        ], [
            'email' => $normalizedEmail,
        ]);

        return $row === null ? null : $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function authenticateLocal(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);

        if ($user === null) {
            return null;
        }

        if (($user['status'] ?? self::STATUS_ACTIVE) !== self::STATUS_ACTIVE) {
            return null;
        }

        $passwordHash = (string) ($user['password_hash'] ?? '');

        if ($passwordHash === '' || !password_verify($password, $passwordHash)) {
            return null;
        }

        $this->touchLastLogin((int) $user['id']);

        return $user;
    }

    /**
     * Provision or refresh a user authenticated via LDAP, Google, or Microsoft.
     *
     * @param array{
     *     name: string,
     *     email: string,
     *     external_id: string,
     *     department?: string|null,
     *     auth_provider: string,
     *     provider_subject?: string|null
     * } $profile
     *
     * @return array<string, mixed>
     */
    public function provisionFromAuth(array $profile): array
    {
        $email = strtolower(trim($profile['email']));
        $externalId = trim($profile['external_id']);
        $authProvider = trim($profile['auth_provider']);
        $providerSubject = trim((string) ($profile['provider_subject'] ?? $externalId));

        if ($email === '' || $externalId === '') {
            throw new \InvalidArgumentException('Authenticated profile must include email and external_id.');
        }

        $payload = [
            'name' => trim($profile['name']) !== '' ? trim($profile['name']) : $email,
            'email' => $email,
            'department' => isset($profile['department']) && $profile['department'] !== null
                ? trim((string) $profile['department'])
                : null,
            'status' => self::STATUS_ACTIVE,
            'role' => self::ROLE_END_USER,
            'auth_provider' => $authProvider !== '' ? $authProvider : self::PROVIDER_LOCAL,
            'provider_subject' => $providerSubject !== '' ? $providerSubject : null,
        ];

        $existing = $this->db()->get('users', ['id'], [
            'OR' => [
                'external_id' => $externalId,
                'email' => $email,
            ],
        ]);

        if ($existing !== null) {
            $localId = (int) $existing['id'];
            $this->db()->update('users', $payload, ['id' => $localId]);
        } else {
            $this->db()->insert('users', [
                'external_id' => $externalId,
                ...$payload,
            ]);
            $localId = (int) $this->db()->id();
        }

        $this->touchLastLogin($localId);

        $user = $this->findById($localId);

        if ($user === null) {
            throw new \RuntimeException('Failed to load provisioned user.');
        }

        return $user;
    }

    public function touchLastLogin(int $userId): void
    {
        $this->db()->update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
        ], [
            'id' => $userId,
        ]);
    }

    /**
     * Create a local-only user for manual assignment when directory search has no match.
     *
     * @return array{id: string, external_id: string, name: string, email: string, department: string|null}
     */
    public function createManual(string $name, string $email): array
    {
        $trimmedName = trim($name);
        $normalizedEmail = strtolower(trim($email));

        if ($trimmedName === '') {
            throw new \InvalidArgumentException(__('manual_user_name_required'));
        }

        if ($normalizedEmail === '' || filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException(__('manual_user_email_invalid'));
        }

        if ($this->findByEmail($normalizedEmail) !== null) {
            throw new \InvalidArgumentException(__('manual_user_email_taken'));
        }

        $externalId = 'manual-' . bin2hex(random_bytes(8));

        $this->db()->insert('users', [
            'external_id' => $externalId,
            'name' => $trimmedName,
            'email' => $normalizedEmail,
            'department' => null,
            'status' => self::STATUS_ACTIVE,
            'role' => self::ROLE_END_USER,
            'auth_provider' => self::PROVIDER_LOCAL,
            'provider_subject' => null,
        ]);

        $localId = (int) $this->db()->id();

        return [
            'id' => (string) $localId,
            'external_id' => $externalId,
            'name' => $trimmedName,
            'email' => $normalizedEmail,
            'department' => null,
        ];
    }

    /**
     * Persist or refresh a directory user and return the local integration shape.
     *
     * @param array{id: string, external_id: string, name: string, email: string, department: string|null} $directoryUser
     *
     * @return array{id: string, external_id: string, name: string, email: string, department: string|null}
     */
    public function syncFromDirectory(array $directoryUser): array
    {
        $externalId = trim((string) ($directoryUser['external_id'] ?? $directoryUser['id'] ?? ''));

        if ($externalId === '') {
            return $directoryUser;
        }

        $payload = [
            'name' => trim((string) ($directoryUser['name'] ?? $externalId)),
            'email' => trim((string) ($directoryUser['email'] ?? '')),
            'department' => isset($directoryUser['department']) && $directoryUser['department'] !== null
                ? trim((string) $directoryUser['department'])
                : null,
            'status' => self::STATUS_ACTIVE,
        ];

        if ($payload['email'] === '') {
            $payload['email'] = sprintf('%s@directory.local', preg_replace('/[^a-z0-9._-]+/i', '-', $externalId) ?: 'user');
        }

        $existing = $this->db()->get('users', ['id'], ['external_id' => $externalId]);

        if ($existing !== null) {
            $this->db()->update('users', $payload, ['id' => (int) $existing['id']]);
            $localId = (int) $existing['id'];
        } else {
            $this->db()->insert('users', [
                'external_id' => $externalId,
                ...$payload,
            ]);
            $localId = (int) $this->db()->id();
        }

        return [
            'id' => (string) $localId,
            'external_id' => $externalId,
            'name' => $payload['name'],
            'email' => $payload['email'],
            'department' => $payload['department'],
        ];
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

    public function markOffboarded(int $userId): bool
    {
        if (!$this->db()->has('users', ['id' => $userId])) {
            return false;
        }

        $this->db()->update('users', [
            'status' => self::STATUS_OFFBOARDED,
        ], [
            'id' => $userId,
        ]);

        return true;
    }

    public function countAssignedAssets(int $userId): int
    {
        return $this->db()->count('assets', [
            'user_id' => $userId,
        ]);
    }

    /**
     * @return array<int, int> user_id => asset_count
     */
    public function assignedAssetCountsByUserId(): array
    {
        $statement = $this->db()->query(
            'SELECT user_id, COUNT(*) AS asset_count FROM assets WHERE user_id IS NOT NULL GROUP BY user_id'
        );

        if ($statement === false) {
            return [];
        }

        $counts = [];

        foreach ($statement->fetchAll() as $row) {
            if (!isset($row['user_id'])) {
                continue;
            }

            $counts[(int) $row['user_id']] = (int) $row['asset_count'];
        }

        return $counts;
    }

    /**
     * @param list<int> $userIds
     *
     * @return array<int, int>
     */
    public function assignedAssetCountsForUserIds(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $userIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($userIds === []) {
            return [];
        }

        $allCounts = $this->assignedAssetCountsByUserId();
        $filtered = [];

        foreach ($userIds as $userId) {
            if (isset($allCounts[$userId])) {
                $filtered[$userId] = $allCounts[$userId];
            }
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['status'] = (string) ($row['status'] ?? self::STATUS_ACTIVE);
        $row['role'] = $this->normalizeRole(isset($row['role']) ? (string) $row['role'] : null);

        if (array_key_exists('department', $row) && $row['department'] !== null) {
            $row['department'] = (string) $row['department'];
        }

        return $row;
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
