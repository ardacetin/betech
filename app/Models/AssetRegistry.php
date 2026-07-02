<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class AssetRegistry
{
    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db()->get('asset_registry', [
            'id',
            'asset_type_id',
        ], [
            'id' => $id,
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        $row['id'] = (int) $row['id'];
        $row['asset_type_id'] = (int) $row['asset_type_id'];

        return $row;
    }

    public function register(int $id, int $assetTypeId): void
    {
        $existing = $this->findById($id);

        if ($existing !== null) {
            $this->db()->update('asset_registry', [
                'asset_type_id' => $assetTypeId,
            ], [
                'id' => $id,
            ]);

            return;
        }

        $this->db()->insert('asset_registry', [
            'id' => $id,
            'asset_type_id' => $assetTypeId,
        ]);
    }

    public function unregister(int $id): void
    {
        $this->db()->delete('asset_registry', [
            'id' => $id,
        ]);
    }

    public function resolveTypeId(int $assetId): ?int
    {
        $row = $this->findById($assetId);

        return $row !== null ? (int) $row['asset_type_id'] : null;
    }
}
