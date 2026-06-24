<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use App\Services\ListPagination;
use Medoo\Medoo;

class Personnel
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_OFFBOARDED = 'offboarded';

    public const ROLE_USER = 'user';
    public const ROLE_ADMIN = 'admin';

    public const PROVIDER_LOCAL = 'local';
    public const PROVIDER_LDAP = 'ldap';
    public const PROVIDER_GOOGLE = 'google';
    public const PROVIDER_MICROSOFT = 'microsoft';

    public const PAGE_SIZE = ListPagination::PAGE_SIZE;

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
        $perPage = ListPagination::PAGE_SIZE;
        $conditions = $this->buildFilter($search);
        $total = (int) $this->db()->count('personnel', $conditions);
        $offset = ListPagination::offset($page, $perPage);

        $rows = $this->db()->select('personnel', [
            'id',
            'name',
            'email',
            'department',
            'title',
            'external_id',
            'provider',
            'status',
            'role',
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
            'pagination' => ListPagination::meta($page, $total, $perPage),
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
        $knownEmails = $existingIndex['by_email'];

        foreach ($directoryUsers as $directoryUser) {
            try {
                $result = $this->syncDirectoryUser($db, $directoryUser, $normalizedProvider, $knownEmails);

                if ($result === 'created') {
                    $stats['created']++;
                } elseif ($result === 'updated') {
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }
            } catch (\Throwable) {
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    /**
     * @param array<string, array<string, mixed>> $knownEmails
     *
     * @return 'created'|'updated'|'skipped'
     */
    private function syncDirectoryUser(
        Medoo $db,
        array $directoryUser,
        string $normalizedProvider,
        array &$knownEmails
    ): string {
        $payload = $this->normalizeDirectoryPayload($directoryUser, $normalizedProvider);

        if ($payload === null) {
            return 'skipped';
        }

        $emailKey = strtolower(trim($payload['email']));
        $existing = $knownEmails[$emailKey] ?? null;

        if ($existing !== null) {
            return $this->updateDirectoryUserByEmail($db, $existing, $payload, $knownEmails, $emailKey);
        }

        return $this->insertDirectoryUser($db, $payload, $knownEmails, $emailKey);
    }

    /**
     * @param array<string, mixed> $existing
     * @param array{name: string, email: string, department: string|null, title: string|null, provider: string, external_id: string} $payload
     * @param array<string, array<string, mixed>> $knownEmails
     *
     * @return 'updated'|'skipped'
     */
    private function updateDirectoryUserByEmail(
        Medoo $db,
        array $existing,
        array $payload,
        array &$knownEmails,
        string $emailKey
    ): string {
        $personnelId = (int) ($existing['id'] ?? 0);

        if ($personnelId <= 0) {
            return 'skipped';
        }

        $updatePayload = [
            'name' => $payload['name'],
            'department' => $payload['department'],
            'title' => $payload['title'],
            'external_id' => $payload['external_id'],
        ];

        if (($existing['status'] ?? self::STATUS_ACTIVE) !== self::STATUS_OFFBOARDED) {
            $updatePayload['provider'] = $payload['provider'];
        }

        if (!$this->directoryRecordChanged($existing, $updatePayload)) {
            return 'skipped';
        }

        $db->update('personnel', $updatePayload, ['id' => $personnelId]);

        $knownEmails[$emailKey] = array_merge($existing, $updatePayload, [
            'email' => $payload['email'],
        ]);

        return 'updated';
    }

    /**
     * @param array{name: string, email: string, department: string|null, title: string|null, provider: string, external_id: string} $payload
     * @param array<string, array<string, mixed>> $knownEmails
     *
     * @return 'created'|'updated'|'skipped'
     */
    private function insertDirectoryUser(
        Medoo $db,
        array $payload,
        array &$knownEmails,
        string $emailKey
    ): string {
        $insertPayload = [
            'status' => self::STATUS_ACTIVE,
            'role' => self::ROLE_USER,
            ...$payload,
        ];

        try {
            $db->insert('personnel', $insertPayload);
        } catch (\Throwable $exception) {
            if (!$this->isDuplicateEmailException($exception)) {
                throw $exception;
            }

            $existing = $db->get('personnel', [
                'id',
                'status',
                'external_id',
                'name',
                'email',
                'department',
                'title',
                'provider',
            ], [
                'email' => $emailKey,
            ]);

            if (!is_array($existing) || $existing === []) {
                return 'skipped';
            }

            $knownEmails[$emailKey] = $existing;

            return $this->updateDirectoryUserByEmail($db, $existing, $payload, $knownEmails, $emailKey) === 'updated'
                ? 'updated'
                : 'skipped';
        }

        $knownEmails[$emailKey] = [
            'id' => (int) $db->id(),
            'status' => self::STATUS_ACTIVE,
            'external_id' => $payload['external_id'],
            'name' => $payload['name'],
            'email' => $payload['email'],
            'department' => $payload['department'],
            'title' => $payload['title'],
            'provider' => $payload['provider'],
        ];

        return 'created';
    }

    private function isDuplicateEmailException(\Throwable $exception): bool
    {
        if ($exception instanceof \PDOException && (string) $exception->getCode() === '23000') {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'duplicate entry')
            && str_contains($message, 'uq_personnel_email');
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

            $db->query($sql, $this->normalizePositionalBindParameters([...$params, ...$ids]));
        }
    }

    /**
     * Medoo binds parameters by array key; PDO positional placeholders require 1-based indexes.
     *
     * @param list<mixed> $values
     *
     * @return array<int, mixed>
     */
    private function normalizePositionalBindParameters(array $values): array
    {
        $normalized = [];
        $position = 1;

        foreach ($values as $value) {
            $normalized[$position] = $value;
            ++$position;
        }

        return $normalized;
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
            'role' => self::ROLE_USER,
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
            'role' => self::ROLE_USER,
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

    public function findByExternalId(string $externalId): ?array
    {
        $normalizedExternalId = strtolower(trim($externalId));

        if ($normalizedExternalId === '') {
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
            'role',
            'created_at',
        ], [
            'external_id' => $normalizedExternalId,
        ]);

        if ($row === null) {
            $row = $this->db()->get('personnel', [
                'id',
                'name',
                'email',
                'department',
                'title',
                'external_id',
                'provider',
                'status',
                'role',
                'created_at',
            ], [
                'external_id' => trim($externalId),
            ]);
        }

        return $row === null ? null : $this->normalizeRow($row);
    }

    /**
     * @param array{
     *     name?: string,
     *     email?: string,
     *     external_id?: string,
     *     department?: string|null,
     *     title?: string|null,
     *     auth_provider?: string,
     *     provider?: string
     * } $profile
     */
    public function refreshProfileFromAuth(int $personnelId, array $profile): ?array
    {
        $existing = $this->findById($personnelId);

        if ($existing === null) {
            return null;
        }

        $payload = [
            'name' => trim((string) ($profile['name'] ?? $existing['name'] ?? '')),
            'email' => strtolower(trim((string) ($profile['email'] ?? $existing['email'] ?? ''))),
            'department' => isset($profile['department']) && $profile['department'] !== null
                ? trim((string) $profile['department'])
                : ($existing['department'] ?? null),
            'title' => isset($profile['title']) && $profile['title'] !== null
                ? trim((string) $profile['title'])
                : ($existing['title'] ?? null),
            'external_id' => trim((string) ($profile['external_id'] ?? $existing['external_id'] ?? '')),
            'provider' => $this->normalizeProvider((string) ($profile['provider'] ?? $profile['auth_provider'] ?? Personnel::PROVIDER_LDAP)),
            'status' => self::STATUS_ACTIVE,
        ];

        if ($payload['name'] === '') {
            $payload['name'] = (string) ($existing['name'] ?? $payload['email']);
        }

        if ($payload['email'] === '') {
            return $existing;
        }

        if ($payload['external_id'] === '') {
            unset($payload['external_id']);
        }

        $this->db()->update('personnel', $payload, ['id' => $personnelId]);

        return $this->findById($personnelId);
    }

    public function updateAccessRole(int $personnelId, string $role): ?array
    {
        $normalizedRole = User::normalizeRoleStatic($role);

        if (!in_array($normalizedRole, [self::ROLE_USER, self::ROLE_ADMIN], true)) {
            throw new \InvalidArgumentException(__('personnel_role_invalid'));
        }

        $existing = $this->findById($personnelId);

        if ($existing === null) {
            return null;
        }

        if ((string) ($existing['role'] ?? self::ROLE_USER) === self::ROLE_ADMIN
            && $normalizedRole === self::ROLE_USER
            && $this->countByRole(self::ROLE_ADMIN) <= 1) {
            throw new \InvalidArgumentException(__('personnel_last_admin'));
        }

        $this->db()->update('personnel', [
            'role' => $normalizedRole,
        ], [
            'id' => $personnelId,
        ]);

        return $this->findById($personnelId);
    }

    public function countByRole(string $role): int
    {
        return $this->db()->count('personnel', [
            'role' => User::normalizeRoleStatic($role),
        ]);
    }

    /**
     * @return list<string>
     */
    public function findAdminEmails(): array
    {
        $rows = $this->db()->select('personnel', ['email'], [
            'role' => self::ROLE_ADMIN,
            'status' => self::STATUS_ACTIVE,
        ]);

        $emails = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $email = strtolower(trim((string) ($row['email'] ?? '')));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $emails[$email] = true;
            }
        }

        return array_keys($emails);
    }

    public function promoteToAdminByUsername(string $username): ?array
    {
        $person = $this->findByExternalId($username);

        if ($person === null) {
            return null;
        }

        return $this->updateAccessRole((int) $person['id'], self::ROLE_ADMIN);
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
            'role',
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
            'role',
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

        $bind = [];
        $placeholderParts = [];

        foreach ($personnelIds as $index => $personnelId) {
            $placeholder = ':personnel_id_' . $index;
            $placeholderParts[] = $placeholder;
            $bind[$placeholder] = $personnelId;
        }

        $statement = $this->db()->query(
            'SELECT p.id AS personnel_id, COUNT(a.id) AS asset_count
             FROM personnel p
             LEFT JOIN assets a ON (
                 LOWER(TRIM(a.assigned_to)) = LOWER(TRIM(p.email))
                 OR LOWER(TRIM(a.assigned_to)) = LOWER(TRIM(p.name))
             )
             WHERE p.id IN (' . implode(', ', $placeholderParts) . ')
             GROUP BY p.id',
            $bind
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
        $row['role'] = User::normalizeRoleStatic(isset($row['role']) ? (string) $row['role'] : self::ROLE_USER);

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
