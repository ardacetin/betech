<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class User
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_OFFBOARDED = 'offboarded';

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
            'created_at',
        ], [
            'ORDER' => ['name' => 'ASC'],
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
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
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['status'] = (string) ($row['status'] ?? self::STATUS_ACTIVE);

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
