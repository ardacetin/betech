<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class AssetHistory
{
    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    public function log(
        int $assetId,
        string $action,
        ?int $userId = null,
        ?int $targetPersonnelId = null,
        ?string $notes = null,
        ?string $assetType = null
    ): void {
        $payload = [
            'asset_id' => $assetId,
            'action' => $action,
            'user_id' => $this->normalizeOptionalUserId($userId),
            'target_personnel_id' => $this->normalizeOptionalPersonnelId($targetPersonnelId),
            'notes' => $notes,
        ];

        if ($this->columnExists('asset_type')) {
            $payload['asset_type'] = $assetType !== null && trim($assetType) !== '' ? trim($assetType) : null;
        }

        $this->db()->insert('asset_histories', $payload);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findRecent(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));

        $joins = [
            '[>]personnel(target_personnel)' => ['target_personnel_id' => 'id'],
            '[>]users(actor)' => ['user_id' => 'id'],
        ];
        $columns = [
            'asset_histories.id',
            'asset_histories.asset_id',
            'asset_histories.asset_type',
            'asset_histories.action',
            'asset_histories.user_id',
            'asset_histories.target_personnel_id',
            'asset_histories.notes',
            'asset_histories.created_at',
            'target_personnel.name(target_personnel_name)',
            'actor.name(actor_name)',
        ];

        if ($this->tableExists('assets_global_registry')) {
            $joins['[>]assets_global_registry(registry)'] = ['asset_id' => 'id'];
            $columns[] = 'registry.name(registry_asset_name)';
        }

        $joins['[>]assets'] = ['asset_id' => 'id'];
        $columns[] = 'assets.name(legacy_asset_name)';

        $rows = $this->db()->select('asset_histories', $joins, $columns, [
            'ORDER' => [
                'asset_histories.id' => 'DESC',
            ],
            'LIMIT' => $limit,
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            is_array($rows) ? $rows : []
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByAssetId(int $assetId): array
    {
        $rows = $this->db()->select('asset_histories', [
            '[>]personnel(target_personnel)' => ['target_personnel_id' => 'id'],
            '[>]users(actor)' => ['user_id' => 'id'],
        ], [
            'asset_histories.id',
            'asset_histories.asset_id',
            'asset_histories.asset_type',
            'asset_histories.action',
            'asset_histories.user_id',
            'asset_histories.target_personnel_id',
            'asset_histories.notes',
            'asset_histories.created_at',
            'target_personnel.name(target_personnel_name)',
            'actor.name(actor_name)',
        ], [
            'asset_histories.asset_id' => $assetId,
            'ORDER' => [
                'asset_histories.id' => 'DESC',
            ],
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            is_array($rows) ? $rows : []
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['asset_id'] = (int) $row['asset_id'];

        if (array_key_exists('user_id', $row) && $row['user_id'] !== null) {
            $row['user_id'] = (int) $row['user_id'];
        }

        if (array_key_exists('target_personnel_id', $row) && $row['target_personnel_id'] !== null) {
            $row['target_personnel_id'] = (int) $row['target_personnel_id'];
        }

        if (array_key_exists('target_personnel_name', $row)) {
            $row['target_user_name'] = $row['target_personnel_name'];
        }

        $registryName = trim((string) ($row['registry_asset_name'] ?? ''));
        $legacyName = trim((string) ($row['legacy_asset_name'] ?? ''));
        $row['asset_name'] = $registryName !== '' ? $registryName : $legacyName;

        unset($row['registry_asset_name'], $row['legacy_asset_name']);

        if (array_key_exists('asset_type', $row) && $row['asset_type'] !== null) {
            $row['asset_type'] = (string) $row['asset_type'];
        }

        return $row;
    }

    private function normalizeOptionalUserId(?int $userId): ?int
    {
        if ($userId === null || $userId <= 0) {
            return null;
        }

        if (!$this->db()->has('users', ['id' => $userId])) {
            return null;
        }

        return $userId;
    }

    private function normalizeOptionalPersonnelId(?int $personnelId): ?int
    {
        if ($personnelId === null || $personnelId <= 0) {
            return null;
        }

        if (!$this->db()->has('personnel', ['id' => $personnelId])) {
            return null;
        }

        return $personnelId;
    }

    private function tableExists(string $tableName): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/', $tableName)) {
            return false;
        }

        $statement = $this->db()->query(
            "SHOW TABLES LIKE '" . str_replace("'", "''", $tableName) . "'"
        );

        if ($statement === false) {
            return false;
        }

        return $statement->fetch() !== false;
    }

    private function columnExists(string $columnName): bool
    {
        $statement = $this->db()->query(
            'SELECT COUNT(*) AS total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ' . $this->db()->quote('asset_histories') . '
              AND COLUMN_NAME = ' . $this->db()->quote($columnName)
        );

        if ($statement === false) {
            return false;
        }

        $row = $statement->fetch();

        return (int) ($row['total'] ?? 0) > 0;
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
