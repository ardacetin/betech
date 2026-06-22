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
        ?string $notes = null
    ): void {
        $this->db()->insert('asset_histories', [
            'asset_id' => $assetId,
            'action' => $action,
            'user_id' => $this->normalizeOptionalUserId($userId),
            'target_personnel_id' => $this->normalizeOptionalPersonnelId($targetPersonnelId),
            'notes' => $notes,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findRecent(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));

        $rows = $this->db()->select('asset_histories', [
            '[>]assets' => ['asset_id' => 'id'],
            '[>]personnel(target_personnel)' => ['target_personnel_id' => 'id'],
            '[>]users(actor)' => ['user_id' => 'id'],
        ], [
            'asset_histories.id',
            'asset_histories.asset_id',
            'asset_histories.action',
            'asset_histories.user_id',
            'asset_histories.target_personnel_id',
            'asset_histories.notes',
            'asset_histories.created_at',
            'assets.name(asset_name)',
            'target_personnel.name(target_personnel_name)',
            'actor.name(actor_name)',
        ], [
            'ORDER' => [
                'asset_histories.created_at' => 'DESC',
                'asset_histories.id' => 'DESC',
            ],
            'LIMIT' => $limit,
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
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
                'asset_histories.created_at' => 'DESC',
                'asset_histories.id' => 'DESC',
            ],
        ]);

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
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

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
