<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class User
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_OFFBOARDED = 'offboarded';

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
