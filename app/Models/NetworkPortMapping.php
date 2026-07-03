<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class NetworkPortMapping
{
    public function __construct(
        private readonly DatabaseService $databaseService,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySource(string $sourceAssetType, int $sourceAssetId): ?array
    {
        if (!$this->tableExists() || $sourceAssetId <= 0 || trim($sourceAssetType) === '') {
            return null;
        }

        $row = $this->db()->get('network_port_mappings', '*', [
            'source_asset_type' => trim($sourceAssetType),
            'source_asset_id' => $sourceAssetId,
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySwitchAndPort(int $switchAssetId, string $portNumber): ?array
    {
        if (!$this->tableExists() || $switchAssetId <= 0 || trim($portNumber) === '') {
            return null;
        }

        $row = $this->db()->get('network_port_mappings', '*', [
            'switch_asset_id' => $switchAssetId,
            'port_number' => trim($portNumber),
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllBySwitchId(int $switchAssetId): array
    {
        if (!$this->tableExists() || $switchAssetId <= 0) {
            return [];
        }

        $rows = $this->db()->select('network_port_mappings', '*', [
            'switch_asset_id' => $switchAssetId,
            'ORDER' => ['port_number' => 'ASC'],
        ]);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    public function countBySwitchId(int $switchAssetId): int
    {
        if (!$this->tableExists() || $switchAssetId <= 0) {
            return 0;
        }

        return (int) $this->db()->count('network_port_mappings', [
            'switch_asset_id' => $switchAssetId,
        ]);
    }

    public function deleteBySwitchAndPort(int $switchAssetId, string $portNumber): void
    {
        if (!$this->tableExists() || $switchAssetId <= 0 || trim($portNumber) === '') {
            return;
        }

        $this->db()->delete('network_port_mappings', [
            'switch_asset_id' => $switchAssetId,
            'port_number' => trim($portNumber),
        ]);
    }

    public function deleteBySource(string $sourceAssetType, int $sourceAssetId): void
    {
        if (!$this->tableExists() || $sourceAssetId <= 0 || trim($sourceAssetType) === '') {
            return;
        }

        $this->db()->delete('network_port_mappings', [
            'source_asset_type' => trim($sourceAssetType),
            'source_asset_id' => $sourceAssetId,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insert(array $row): void
    {
        if (!$this->tableExists()) {
            return;
        }

        $this->db()->insert('network_port_mappings', $row);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'source_asset_type' => (string) ($row['source_asset_type'] ?? ''),
            'source_asset_id' => (int) ($row['source_asset_id'] ?? 0),
            'switch_asset_id' => (int) ($row['switch_asset_id'] ?? 0),
            'port_number' => (string) ($row['port_number'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function tableExists(): bool
    {
        $statement = $this->db()->query("SHOW TABLES LIKE 'network_port_mappings'");

        return $statement !== false && $statement->rowCount() > 0;
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
