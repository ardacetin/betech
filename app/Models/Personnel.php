<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class Personnel
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_OFFBOARDED = 'offboarded';

    public const PROVIDER_LOCAL = 'local';
    public const PROVIDER_LDAP = 'ldap';
    public const PROVIDER_GOOGLE = 'google';
    public const PROVIDER_MICROSOFT = 'microsoft';

    public const PAGE_SIZE = 50;

    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    /**
     * @return array{
     *     data: list<array<string, mixed>>,
     *     pagination: array{page: int, per_page: int, total: int, total_pages: int}
     * }
     */
    public function findPaginated(int $page, int $perPage, ?string $search = null): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $conditions = $this->buildFilter($search);
        $total = $this->db()->count('personnel', $conditions);
        $offset = ($page - 1) * $perPage;

        $rows = $this->db()->select('personnel', [
            'id',
            'name',
            'email',
            'department',
            'title',
            'external_id',
            'provider',
            'status',
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
     * @param list<array{id?: string, external_id?: string, name?: string, email?: string, department?: string|null, title?: string|null}> $directoryUsers
     *
     * @return array{created: int, updated: int, skipped: int, total: int}
     */
    public function syncDirectory(array $directoryUsers, string $provider): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'total' => count($directoryUsers),
        ];

        foreach ($directoryUsers as $directoryUser) {
            $outcome = $this->upsertFromDirectory($directoryUser, $provider);
            $stats[$outcome]++;
        }

        return $stats;
    }

    /**
     * @param array{id?: string, external_id?: string, name?: string, email?: string, department?: string|null, title?: string|null} $directoryUser
     *
     * @return 'created'|'updated'|'skipped'
     */
    public function upsertFromDirectory(array $directoryUser, string $provider): string
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
        $title = isset($directoryUser['title']) && $directoryUser['title'] !== null
            ? trim((string) $directoryUser['title'])
            : null;
        $title = $title === '' ? null : $title;
        $normalizedProvider = $this->normalizeProvider($provider);

        $existing = $this->db()->get('personnel', [
            'id',
            'status',
            'external_id',
        ], [
            'OR' => [
                'external_id' => $externalId,
                'email' => $email,
            ],
        ]);

        $payload = [
            'name' => $name,
            'email' => $email,
            'department' => $department,
            'title' => $title,
            'provider' => $normalizedProvider,
            'external_id' => $externalId,
        ];

        if ($existing !== null) {
            if (($existing['status'] ?? self::STATUS_ACTIVE) === self::STATUS_OFFBOARDED) {
                unset($payload['provider']);
            }

            $this->db()->update('personnel', $payload, ['id' => (int) $existing['id']]);

            return 'updated';
        }

        $this->db()->insert('personnel', [
            'status' => self::STATUS_ACTIVE,
            ...$payload,
        ]);

        return 'created';
    }

    /**
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

        $this->db()->insert('personnel', [
            'external_id' => $externalId,
            'name' => $trimmedName,
            'email' => $normalizedEmail,
            'department' => null,
            'title' => null,
            'provider' => self::PROVIDER_LOCAL,
            'status' => self::STATUS_ACTIVE,
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

        $payload['email'] = strtolower($payload['email']);

        $existing = $this->db()->get('personnel', ['id'], [
            'OR' => [
                'external_id' => $externalId,
                'email' => $payload['email'],
            ],
        ]);

        if ($existing !== null) {
            $this->db()->update('personnel', $payload, ['id' => (int) $existing['id']]);
            $localId = (int) $existing['id'];
        } else {
            $this->db()->insert('personnel', [
                'external_id' => $externalId,
                'provider' => self::PROVIDER_LOCAL,
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

    /**
     * @param array{
     *     name: string,
     *     email: string,
     *     external_id: string,
     *     department?: string|null,
     *     title?: string|null,
     *     auth_provider?: string,
     *     provider?: string
     * } $profile
     *
     * @return array<string, mixed>
     */
    public function provisionFromAuth(array $profile): array
    {
        $email = strtolower(trim($profile['email']));
        $externalId = trim($profile['external_id']);
        $provider = trim((string) ($profile['provider'] ?? $profile['auth_provider'] ?? self::PROVIDER_LOCAL));

        if ($email === '' || $externalId === '') {
            throw new \InvalidArgumentException('Authenticated profile must include email and external_id.');
        }

        $this->upsertFromDirectory([
            'external_id' => $externalId,
            'name' => trim($profile['name']) !== '' ? trim($profile['name']) : $email,
            'email' => $email,
            'department' => $profile['department'] ?? null,
            'title' => $profile['title'] ?? null,
        ], $provider);

        $person = $this->findByEmail($email);

        if ($person === null) {
            throw new \RuntimeException('Failed to load provisioned personnel record.');
        }

        return $person;
    }

    public function findById(int $personnelId): ?array
    {
        $row = $this->db()->get('personnel', [
            'id',
            'name',
            'email',
            'department',
            'title',
            'external_id',
            'provider',
            'status',
            'created_at',
        ], [
            'id' => $personnelId,
        ]);

        return $row === null ? null : $this->normalizeRow($row);
    }

    public function findByEmail(string $email): ?array
    {
        $normalizedEmail = strtolower(trim($email));

        if ($normalizedEmail === '') {
            return null;
        }

        $row = $this->db()->get('personnel', [
            'id',
            'name',
            'email',
            'department',
            'title',
            'external_id',
            'provider',
            'status',
            'created_at',
        ], [
            'email' => $normalizedEmail,
        ]);

        return $row === null ? null : $this->normalizeRow($row);
    }

    public function markOffboarded(int $personnelId): bool
    {
        if (!$this->db()->has('personnel', ['id' => $personnelId])) {
            return false;
        }

        $this->db()->update('personnel', [
            'status' => self::STATUS_OFFBOARDED,
        ], [
            'id' => $personnelId,
        ]);

        return true;
    }

    /**
     * @param list<int> $personnelIds
     *
     * @return array<int, int>
     */
    public function assignedAssetCountsForIds(array $personnelIds): array
    {
        $personnelIds = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $personnelIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($personnelIds === []) {
            return [];
        }

        $statement = $this->db()->query(
            'SELECT personnel_id, COUNT(*) AS asset_count FROM assets WHERE personnel_id IS NOT NULL GROUP BY personnel_id'
        );

        if ($statement === false) {
            return [];
        }

        $allCounts = [];

        foreach ($statement->fetchAll() as $row) {
            if (!isset($row['personnel_id'])) {
                continue;
            }

            $allCounts[(int) $row['personnel_id']] = (int) $row['asset_count'];
        }

        $filtered = [];

        foreach ($personnelIds as $personnelId) {
            if (isset($allCounts[$personnelId])) {
                $filtered[$personnelId] = $allCounts[$personnelId];
            }
        }

        return $filtered;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFilter(?string $search): array
    {
        $conditions = [];
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

    private function normalizeProvider(string $provider): string
    {
        $normalized = strtolower(trim($provider));

        return match ($normalized) {
            self::PROVIDER_LDAP => self::PROVIDER_LDAP,
            self::PROVIDER_GOOGLE => self::PROVIDER_GOOGLE,
            self::PROVIDER_MICROSOFT => self::PROVIDER_MICROSOFT,
            default => self::PROVIDER_LOCAL,
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
        $row['status'] = (string) ($row['status'] ?? self::STATUS_ACTIVE);

        if (array_key_exists('department', $row) && $row['department'] !== null) {
            $row['department'] = (string) $row['department'];
        }

        if (array_key_exists('title', $row) && $row['title'] !== null) {
            $row['title'] = (string) $row['title'];
        }

        return $row;
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
