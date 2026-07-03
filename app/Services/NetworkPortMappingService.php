<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssetType;
use App\Models\NetworkPortMapping;
use RuntimeException;

class NetworkPortMappingService
{
    /**
     * @var list<string>
     */
    private const SWITCH_TYPE_SLUGS = [
        'ag_anahtarlari',
        'switchler',
        'switches',
        'ag-anahtarlari',
    ];

    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly NetworkPortMapping $networkPortMappingModel,
        private readonly AssetType $assetTypeModel,
        private readonly AssetTypeTableService $assetTypeTableService,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForSource(string $sourceAssetType, int $sourceAssetId): ?array
    {
        $mapping = $this->networkPortMappingModel->findBySource($sourceAssetType, $sourceAssetId);

        if ($mapping === null) {
            return null;
        }

        $switch = $this->findSwitchAssetById((int) $mapping['switch_asset_id']);

        return [
            ...$mapping,
            'switch_asset_tag' => (string) ($switch['asset_tag'] ?? ''),
            'switch_name' => (string) ($switch['name'] ?? ''),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSwitchAssets(): array
    {
        $switchSlugs = $this->resolveSwitchTypeSlugs();
        $switches = [];

        foreach ($switchSlugs as $slug) {
            $typeContext = $this->assetTypeTableService->resolveWhitelistedType($slug);

            if ($typeContext === null) {
                continue;
            }

            $tableName = $typeContext['table'];
            $connection = $this->databaseService->getConnection();

            if (!$this->tableExists($tableName)) {
                continue;
            }

            $rows = $connection->select($tableName, [
                'id',
                'asset_tag',
                'name',
                'model',
                'brand',
                'serial_number',
                'status',
            ], [
                'ORDER' => ['name' => 'ASC', 'asset_tag' => 'ASC'],
            ]);

            if (!is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $switches[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'asset_tag' => (string) ($row['asset_tag'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'model' => (string) ($row['model'] ?? ''),
                    'brand' => (string) ($row['brand'] ?? ''),
                    'serial_number' => (string) ($row['serial_number'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                    'asset_type_slug' => $slug,
                    'asset_type_name' => $this->resolveTypeName($slug),
                    'label' => $this->formatSwitchLabel($row),
                ];
            }
        }

        if ($switches !== []) {
            usort($switches, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));
        }

        return $switches;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function syncForSource(string $sourceAssetType, int $sourceAssetId, ?array $payload): void
    {
        if ($sourceAssetId <= 0 || trim($sourceAssetType) === '') {
            throw new RuntimeException(__('network_port_mapping_invalid_source'));
        }

        if (!$this->mappingTableExists()) {
            throw new RuntimeException(__('network_port_mapping_table_missing'));
        }

        $pdo = $this->databaseService->getConnection()->pdo;
        $pdo->beginTransaction();

        try {
            $this->db()->delete('network_port_mappings', [
                'source_asset_type' => trim($sourceAssetType),
                'source_asset_id' => $sourceAssetId,
            ]);

            if ($payload !== null) {
                $switchAssetId = (int) ($payload['switch_asset_id'] ?? 0);
                $portNumber = trim((string) ($payload['port_number'] ?? ''));

                if ($switchAssetId > 0 && $portNumber !== '') {
                    if ($this->findSwitchAssetById($switchAssetId) === null) {
                        throw new RuntimeException(__('network_port_mapping_invalid_switch'));
                    }

                    $this->db()->insert('network_port_mappings', [
                        'source_asset_type' => trim($sourceAssetType),
                        'source_asset_id' => $sourceAssetId,
                        'switch_asset_id' => $switchAssetId,
                        'port_number' => $portNumber,
                    ]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return list<string>
     */
    private function resolveSwitchTypeSlugs(): array
    {
        $slugs = self::SWITCH_TYPE_SLUGS;

        foreach ($this->assetTypeModel->findAll() as $assetType) {
            $slug = strtolower(trim((string) ($assetType['slug'] ?? '')));
            $name = strtolower(trim((string) ($assetType['name'] ?? '')));

            if ($slug === '') {
                continue;
            }

            if (
                str_contains($slug, 'switch')
                || str_contains($slug, 'anahtar')
                || str_contains($name, 'anahtar')
                || str_contains($name, 'switch')
            ) {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique(array_filter($slugs)));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function formatSwitchLabel(array $row): string
    {
        $tag = trim((string) ($row['asset_tag'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));

        if ($tag !== '' && $name !== '') {
            return sprintf('%s — %s', $tag, $name);
        }

        return $tag !== '' ? $tag : $name;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findSwitchAssetById(int $switchAssetId): ?array
    {
        if ($switchAssetId <= 0) {
            return null;
        }

        foreach ($this->listSwitchAssets() as $switch) {
            if ((int) ($switch['id'] ?? 0) === $switchAssetId) {
                return $switch;
            }
        }

        return null;
    }

    private function resolveTypeName(string $slug): string
    {
        foreach ($this->assetTypeModel->findAll() as $assetType) {
            if ((string) ($assetType['slug'] ?? '') === $slug) {
                return (string) ($assetType['name'] ?? $slug);
            }
        }

        return $slug;
    }

    private function mappingTableExists(): bool
    {
        return $this->tableExists('network_port_mappings');
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db()->query(sprintf("SHOW TABLES LIKE '%s'", str_replace("'", "''", $table)));

        return $statement !== false && $statement->rowCount() > 0;
    }

    private function db(): \Medoo\Medoo
    {
        return $this->databaseService->getConnection();
    }
}
