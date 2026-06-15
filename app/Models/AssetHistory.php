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
        ?int $targetUserId = null,
        ?string $notes = null
    ): void {
        $this->db()->insert('asset_histories', [
            'asset_id' => $assetId,
            'action' => $action,
            'user_id' => $userId,
            'target_user_id' => $targetUserId,
            'notes' => $notes,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByAssetId(int $assetId): array
    {
        $rows = $this->db()->select('asset_histories', [
            '[>]users(target_user)' => ['target_user_id' => 'id'],
            '[>]users(actor)' => ['user_id' => 'id'],
        ], [
            'asset_histories.id',
            'asset_histories.asset_id',
            'asset_histories.action',
            'asset_histories.user_id',
            'asset_histories.target_user_id',
            'asset_histories.notes',
            'asset_histories.created_at',
            'target_user_name' => 'target_user.name',
            'actor_name' => 'actor.name',
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

        if (array_key_exists('target_user_id', $row) && $row['target_user_id'] !== null) {
            $row['target_user_id'] = (int) $row['target_user_id'];
        }

        return $row;
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
