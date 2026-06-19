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

    private const DIRECTORY_SYNC_INSERT_CHUNK = 500;

    private const DIRECTORY_SYNC_UPDATE_CHUNK = 200;

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
        $total = count($directoryUsers);

        $stats = $this->db()->action(function (Medoo $db) use ($directoryUsers, $provider, $total): array {
            return $this->performBulkDirectorySync($db, $directoryUsers, $provider, $total);
        });

        return is_array($stats)
            ? $stats
            : [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'total' => $total,
            ];
    }

    /**
     * @param list<array{id?: string, external_id?: string, name?: string, email?: string, department?: string|null, title?: string|null}> $directoryUsers
     *
     * @return array{created: int, updated: int, skipped: int, total: int}
     */
    private function performBulkDirectorySync(Medoo $db, array $directoryUsers, string $provider, int $total): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'total' => $total,
        ];

        $normalizedProvider = $this->normalizeProvider($provider);
        $existingIndex = $this->loadDirectorySyncIndex($db);
        $insertRows = [];
        $updateRows = [];

        foreach ($directoryUsers as $directoryUser) {
            $payload = $this->normalizeDirectoryPayload($directoryUser, $normalizedProvider);

            if ($payload === null) {
                $stats['skipped']++;
                continue;
            }

            $existing = $this->findExistingInDirectoryIndex(
                $existingIndex,
                $payload['external_id'],
                $payload['email']
            );

            if ($existing !== null) {
                $updatePayload = $payload;

                if (($existing['status'] ?? self::STATUS_ACTIVE) === self::STATUS_OFFBOARDED) {
                    unset($updatePayload['provider']);
                }

                if (!$this->directoryRecordChanged($existing, $updatePayload)) {
                    $stats['skipped']++;
                    continue;
                }

                $updateRows[] = [
                    'id' => (int) $existing['id'],
                    'data' => $updatePayload,
                ];
                $stats['updated']++;
                continue;
            }

            $insertRows[] = [
                'status' => self::STATUS_ACTIVE,
                ...$payload,
            ];
            $stats['created']++;
        }

        unset($directoryUsers, $existingIndex);

        $this->bulkInsertPersonnel($db, $insertRows);
        unset($insertRows);

        $this->bulkUpdatePersonnel($db, $updateRows);
        unset($updateRows);

        return $stats;
    }

    /**
     * @return array{by_external_id: array<string, array<string, mixed>>, by_email: array<string, array<string, mixed>>}
     */
    private function loadDirectorySyncIndex(Medoo $db): array
    {
        $rows = $db->select('personnel', [
            'id',
            'status',
            'external_id',
            'name',
            'email',
            'department',
            'title',
            'provider',
        ]);

        $byExternalId = [];
        $byEmail = [];

        foreach ($rows as $row) {
            $externalId = strtolower(trim((string) ($row['external_id'] ?? '')));

            if ($externalId !== '') {
                $byExternalId[$externalId] = $row;
            }

            $email = strtolower(trim((string) ($row['email'] ?? '')));

            if ($email !== '') {
                $byEmail[$email] = $row;
            }
        }

        unset($rows);

        return [
            'by_external_id' => $byExternalId,
            'by_email' => $byEmail,
        ];
    }

    /**
     * @param array{by_external_id: array<string, array<string, mixed>>, by_email: array<string, array<string, mixed>>} $index
     *
     * @return array<string, mixed>|null
     */
    private function findExistingInDirectoryIndex(array $index, string $externalId, string $email): ?array
    {
        $externalKey = strtolower(trim($externalId));

        if ($externalKey !== '' && isset($index['by_external_id'][$externalKey])) {
            return $index['by_external_id'][$externalKey];
        }

        $emailKey = strtolower(trim($email));

        if ($emailKey !== '' && isset($index['by_email'][$emailKey])) {
            return $index['by_email'][$emailKey];
        }

        return null;
    }

    /**
     * @param array{id?: string, external_id?: string, name?: string, email?: string, department?: string|null, title?: string|null} $directoryUser
     *
     * @return array{name: string, email: string, department: string|null, title: string|null, provider: string, external_id: string}|null
     */
    private function normalizeDirectoryPayload(array $directoryUser, string $provider): ?array
    {
        $externalId = trim((string) ($directoryUser['external_id'] ?? $directoryUser['id'] ?? ''));

        if ($externalId === '') {
            return null;
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

        return [
            'name' => $name,
            'email' => $email,
            'department' => $department,
            'title' => $title,
            'provider' => $provider,
            'external_id' => $externalId,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function bulkInsertPersonnel(Medoo $db, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, self::DIRECTORY_SYNC_INSERT_CHUNK) as $chunk) {
            $db->insert('personnel', $chunk);
        }
    }

    /**
     * @param list<array{id: int, data: array<string, mixed>}> $updates
     */
    private function bulkUpdatePersonnel(Medoo $db, array $updates): void
    {
        if ($updates === []) {
            return;
        }

        foreach (array_chunk($updates, self::DIRECTORY_SYNC_UPDATE_CHUNK) as $chunk) {
            $ids = [];
            $fieldsPresent = [];

            foreach ($chunk as $update) {
                $ids[] = (int) $update['id'];

                foreach (array_keys($update['data']) as $field) {
                    $fieldsPresent[$field] = true;
                }
            }

            $params = [];
            $setClauses = [];

            foreach (array_keys($fieldsPresent) as $field) {
                $caseSql = ['CASE `id`'];
                $hasBranch = false;

                foreach ($chunk as $update) {
                    if (!array_key_exists($field, $update['data'])) {
                        continue;
                    }

                    $caseSql[] = 'WHEN ? THEN ?';
                    $params[] = (int) $update['id'];
                    $params[] = $update['data'][$field];
                    $hasBranch = true;
                }

                if (!$hasBranch) {
                    continue;
                }

                $caseSql[] = sprintf('ELSE `%s` END', $field);
                $setClauses[] = sprintf('`%s` = %s', $field, implode(' ', $caseSql));
            }

            if ($setClauses === []) {
                continue;
            }

            $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = sprintf(
                'UPDATE `personnel` SET %s WHERE `id` IN (%s)',
                implode(', ', $setClauses),
                $idPlaceholders
            );

            $db->query($sql, [...$params, ...$ids]);
        }
    }

    /**
     * @param array{id?: string, external_id?: string, name?: string, email?: string, department?: string|null, title?: string|null} $directoryUser
     *
     * @return 'created'|'updated'|'skipped'
     */
    public function upsertFromDirectory(array $directoryUser, string $provider): string
    {
        $normalizedProvider = $this->normalizeProvider($provider);
        $payload = $this->normalizeDirectoryPayload($directoryUser, $normalizedProvider);

        if ($payload === null) {
            return 'skipped';
        }

        $existing = $this->db()->get('personnel', [
            'id',
            'status',
            'external_id',
            'name',
            'email',
            'department',
            'title',
            'provider',
        ], [
            'OR' => [
                'external_id' => $payload['external_id'],
                'email' => $payload['email'],
            ],
        ]);

        if ($existing !== null) {
            $updatePayload = $payload;

            if (($existing['status'] ?? self::STATUS_ACTIVE) === self::STATUS_OFFBOARDED) {
                unset($updatePayload['provider']);
            }

            if (!$this->directoryRecordChanged($existing, $updatePayload)) {
                return 'skipped';
            }

            $this->db()->update('personnel', $updatePayload, ['id' => (int) $existing['id']]);

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

    /**
     * @return list<array{id: string, external_id: string, name: string, email: string, department: string|null}>
     */
    public function searchActive(string $query, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $trimmedQuery = trim($query);

        try {
            return $this->selectSearchActive($trimmedQuery, $limit, true);
        } catch (\Throwable) {
            return $this->selectSearchActive($trimmedQuery, $limit, false);
        }
    }

    /**
     * @return list<array{id: string, external_id: string, name: string, email: string, department: string|null}>
     */
    private function selectSearchActive(string $trimmedQuery, int $limit, bool $extendedSchema): array
    {
        $and = [
            'status[!]' => self::STATUS_OFFBOARDED,
        ];

        if ($trimmedQuery !== '') {
            $or = [
                'name[~]' => $trimmedQuery,
                'email[~]' => $trimmedQuery,
            ];

            if ($extendedSchema) {
                $or['department[~]'] = $trimmedQuery;
                $or['external_id[~]'] = $trimmedQuery;
                $or['title[~]'] = $trimmedQuery;
            }

            $and['OR'] = $or;
        }

        $rows = $this->db()->select('personnel', [
            'id',
            'external_id',
            'name',
            'email',
            'department',
        ], [
            'AND' => $and,
            'ORDER' => ['name' => 'ASC'],
            'LIMIT' => $limit,
        ]);

        if (!is_array($rows)) {
            throw new \RuntimeException('Personnel search query failed.');
        }

        return array_map(
            fn (array $row): array => $this->formatSearchResult($row),
            $rows
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: string, external_id: string, name: string, email: string, department: string|null}
     */
    private function formatSearchResult(array $row): array
    {
        return [
            'id' => (string) ($row['id'] ?? ''),
            'external_id' => (string) ($row['external_id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'department' => isset($row['department']) && $row['department'] !== null && $row['department'] !== ''
                ? (string) $row['department']
                : null,
        ];
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
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $payload
     */
    private function directoryRecordChanged(array $existing, array $payload): bool
    {
        $fields = ['name', 'email', 'department', 'title', 'external_id'];

        foreach ($fields as $field) {
            $existingValue = $existing[$field] ?? null;
            $incomingValue = $payload[$field] ?? null;

            if ($existingValue === null || $existingValue === '') {
                $existingValue = null;
            } else {
                $existingValue = (string) $existingValue;
            }

            if ($incomingValue === null || $incomingValue === '') {
                $incomingValue = null;
            } else {
                $incomingValue = (string) $incomingValue;
            }

            if ($existingValue !== $incomingValue) {
                return true;
            }
        }

        if (array_key_exists('provider', $payload)) {
            $existingProvider = $this->normalizeProvider((string) ($existing['provider'] ?? self::PROVIDER_LOCAL));
            $incomingProvider = $this->normalizeProvider((string) $payload['provider']);

            if ($existingProvider !== $incomingProvider) {
                return true;
            }
        }

        return false;
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
