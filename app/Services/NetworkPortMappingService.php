<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssetType;
use App\Models\AssetsGlobalRegistry;
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

    private const DEFAULT_TOTAL_PORTS = 24;

    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly NetworkPortMapping $networkPortMappingModel,
        private readonly AssetType $assetTypeModel,
        private readonly AssetTypeTableService $assetTypeTableService,
        private readonly AssetsGlobalRegistry $assetsGlobalRegistry,
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
        return array_map(
            static fn (array $switch): array => [
                'id' => (int) ($switch['id'] ?? 0),
                'asset_tag' => (string) ($switch['asset_tag'] ?? ''),
                'name' => (string) ($switch['name'] ?? ''),
                'model' => (string) ($switch['model'] ?? ''),
                'brand' => (string) ($switch['brand'] ?? ''),
                'serial_number' => (string) ($switch['serial_number'] ?? ''),
                'status' => (string) ($switch['status'] ?? ''),
                'asset_type_slug' => (string) ($switch['asset_type_slug'] ?? ''),
                'asset_type_name' => (string) ($switch['asset_type_name'] ?? ''),
                'label' => (string) ($switch['label'] ?? ''),
            ],
            $this->collectSwitchRows()
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSwitchDirectory(): array
    {
        $directory = [];

        foreach ($this->collectSwitchRows() as $switch) {
            $switchId = (int) ($switch['id'] ?? 0);
            $totalPorts = (int) ($switch['total_ports'] ?? self::DEFAULT_TOTAL_PORTS);

            if ($totalPorts <= 0) {
                $totalPorts = self::DEFAULT_TOTAL_PORTS;
            }

            $usedPorts = $this->networkPortMappingModel->countBySwitchId($switchId);

            $directory[] = [
                'id' => $switchId,
                'asset_tag' => (string) ($switch['asset_tag'] ?? ''),
                'name' => (string) ($switch['name'] ?? ''),
                'location' => (string) ($switch['location'] ?? ''),
                'building' => (string) ($switch['building'] ?? ''),
                'model' => (string) ($switch['model'] ?? ''),
                'brand' => (string) ($switch['brand'] ?? ''),
                'status' => (string) ($switch['status'] ?? ''),
                'asset_type_slug' => (string) ($switch['asset_type_slug'] ?? ''),
                'total_ports' => $totalPorts,
                'used_ports' => $usedPorts,
                'utilization_percent' => $totalPorts > 0
                    ? (int) round(($usedPorts / $totalPorts) * 100)
                    : 0,
                'label' => (string) ($switch['label'] ?? ''),
            ];
        }

        return $directory;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSwitchPortMatrix(int $switchAssetId): ?array
    {
        $switch = $this->findSwitchAssetById($switchAssetId);

        if ($switch === null) {
            return null;
        }

        $totalPorts = (int) ($switch['total_ports'] ?? self::DEFAULT_TOTAL_PORTS);

        if ($totalPorts <= 0) {
            $totalPorts = self::DEFAULT_TOTAL_PORTS;
        }

        $mappingByPort = [];

        foreach ($this->networkPortMappingModel->findAllBySwitchId($switchAssetId) as $mapping) {
            $portNumber = trim((string) ($mapping['port_number'] ?? ''));

            if ($portNumber === '') {
                continue;
            }

            $mappingByPort[$portNumber] = $this->enrichMapping($mapping);
        }

        $ports = [];

        for ($index = 1; $index <= $totalPorts; ++$index) {
            $portKey = (string) $index;
            $mapping = $mappingByPort[$portKey] ?? null;

            $ports[] = [
                'port_number' => $portKey,
                'occupied' => $mapping !== null,
                'mapping' => $mapping,
            ];
        }

        $portsPerRow = (int) ceil($totalPorts / 2);

        return [
            'switch' => [
                'id' => (int) ($switch['id'] ?? 0),
                'asset_tag' => (string) ($switch['asset_tag'] ?? ''),
                'name' => (string) ($switch['name'] ?? ''),
                'location' => (string) ($switch['location'] ?? ''),
                'building' => (string) ($switch['building'] ?? ''),
                'total_ports' => $totalPorts,
                'used_ports' => $this->networkPortMappingModel->countBySwitchId($switchAssetId),
                'asset_type_slug' => (string) ($switch['asset_type_slug'] ?? ''),
            ],
            'ports_per_row' => $portsPerRow,
            'ports' => $ports,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPortConfigContext(int $switchAssetId, string $portNumber): ?array
    {
        $switch = $this->findSwitchAssetById($switchAssetId);

        if ($switch === null) {
            return null;
        }

        $totalPorts = (int) ($switch['total_ports'] ?? self::DEFAULT_TOTAL_PORTS);

        if ($totalPorts <= 0) {
            $totalPorts = self::DEFAULT_TOTAL_PORTS;
        }

        $portIndex = (int) $portNumber;

        if ($portIndex < 1 || $portIndex > $totalPorts) {
            throw new RuntimeException(__('switch_port_invalid_port_number'));
        }

        $mapping = $this->networkPortMappingModel->findBySwitchAndPort($switchAssetId, (string) $portIndex);

        return [
            'switch' => [
                'id' => (int) ($switch['id'] ?? 0),
                'asset_tag' => (string) ($switch['asset_tag'] ?? ''),
                'name' => (string) ($switch['name'] ?? ''),
                'location' => (string) ($switch['location'] ?? ''),
                'building' => (string) ($switch['building'] ?? ''),
                'total_ports' => $totalPorts,
                'asset_type_slug' => (string) ($switch['asset_type_slug'] ?? ''),
            ],
            'port_number' => (string) $portIndex,
            'mapping' => $mapping !== null ? $this->enrichMapping($mapping) : null,
        ];
    }

    public function assignPort(
        int $switchAssetId,
        string $portNumber,
        string $sourceAssetType,
        int $sourceAssetId
    ): void {
        if (!$this->mappingTableExists()) {
            throw new RuntimeException(__('network_port_mapping_table_missing'));
        }

        $switch = $this->findSwitchAssetById($switchAssetId);

        if ($switch === null) {
            throw new RuntimeException(__('network_port_mapping_invalid_switch'));
        }

        $portIndex = (int) trim($portNumber);
        $totalPorts = (int) ($switch['total_ports'] ?? self::DEFAULT_TOTAL_PORTS);

        if ($totalPorts <= 0) {
            $totalPorts = self::DEFAULT_TOTAL_PORTS;
        }

        if ($portIndex < 1 || $portIndex > $totalPorts) {
            throw new RuntimeException(__('switch_port_invalid_port_number'));
        }

        $sourceType = trim($sourceAssetType);

        if ($sourceAssetId <= 0 || $sourceType === '') {
            throw new RuntimeException(__('network_port_mapping_invalid_source'));
        }

        $asset = $this->assetsGlobalRegistry->findById($sourceAssetId);

        if ($asset === null) {
            throw new RuntimeException(__('switch_port_asset_not_found'));
        }

        $resolvedType = (string) ($asset['asset_type_slug'] ?? $sourceType);

        if ($resolvedType !== $sourceType && $resolvedType !== '') {
            $sourceType = $resolvedType;
        }

        $pdo = $this->databaseService->getConnection()->pdo;
        $pdo->beginTransaction();

        try {
            $this->networkPortMappingModel->deleteBySwitchAndPort($switchAssetId, (string) $portIndex);
            $this->networkPortMappingModel->deleteBySource($sourceType, $sourceAssetId);

            $this->networkPortMappingModel->insert([
                'source_asset_type' => $sourceType,
                'source_asset_id' => $sourceAssetId,
                'switch_asset_id' => $switchAssetId,
                'port_number' => (string) $portIndex,
            ]);

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function disconnectPort(int $switchAssetId, string $portNumber): void
    {
        if (!$this->mappingTableExists()) {
            throw new RuntimeException(__('network_port_mapping_table_missing'));
        }

        if ($this->findSwitchAssetById($switchAssetId) === null) {
            throw new RuntimeException(__('network_port_mapping_invalid_switch'));
        }

        $this->networkPortMappingModel->deleteBySwitchAndPort(
            $switchAssetId,
            (string) (int) trim($portNumber)
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchConnectableAssets(string $query, int $limit = 30): array
    {
        $results = [];

        foreach ($this->assetsGlobalRegistry->search($query, $limit) as $asset) {
            $assetId = (int) ($asset['id'] ?? 0);
            $typeSlug = (string) ($asset['asset_type_slug'] ?? '');

            if ($assetId <= 0 || $typeSlug === '') {
                continue;
            }

            if ($this->isSwitchTypeSlug($typeSlug)) {
                continue;
            }

            $results[] = [
                'id' => $assetId,
                'asset_tag' => (string) ($asset['asset_tag'] ?? ''),
                'name' => (string) ($asset['name'] ?? ''),
                'asset_type_slug' => $typeSlug,
                'assigned_to' => (string) ($asset['assigned_to'] ?? ''),
                'ip_address' => $this->findIpForAsset($assetId),
                'label' => $this->formatAssetLabel($asset),
            ];
        }

        return $results;
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
            $this->networkPortMappingModel->deleteBySource(trim($sourceAssetType), $sourceAssetId);

            if ($payload !== null) {
                $switchAssetId = (int) ($payload['switch_asset_id'] ?? 0);
                $portNumber = trim((string) ($payload['port_number'] ?? ''));

                if ($switchAssetId > 0 && $portNumber !== '') {
                    if ($this->findSwitchAssetById($switchAssetId) === null) {
                        throw new RuntimeException(__('network_port_mapping_invalid_switch'));
                    }

                    $this->networkPortMappingModel->deleteBySwitchAndPort($switchAssetId, $portNumber);
                    $this->networkPortMappingModel->insert([
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
     * @param array<string, mixed> $mapping
     *
     * @return array<string, mixed>
     */
    private function enrichMapping(array $mapping): array
    {
        $sourceId = (int) ($mapping['source_asset_id'] ?? 0);
        $asset = $sourceId > 0 ? $this->assetsGlobalRegistry->findById($sourceId) : null;

        return [
            ...$mapping,
            'asset_name' => (string) ($asset['name'] ?? ''),
            'asset_tag' => (string) ($asset['asset_tag'] ?? ''),
            'assigned_to' => (string) ($asset['assigned_to'] ?? ''),
            'ip_address' => $sourceId > 0 ? $this->findIpForAsset($sourceId) : null,
            'asset_type_slug' => (string) ($asset['asset_type_slug'] ?? $mapping['source_asset_type'] ?? ''),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectSwitchRows(): array
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

            $columns = [
                'id',
                'asset_tag',
                'name',
                'model',
                'brand',
                'serial_number',
                'status',
            ];

            if ($this->columnExistsOnTable($tableName, 'location')) {
                $columns[] = 'location';
            }

            if ($this->columnExistsOnTable($tableName, 'building')) {
                $columns[] = 'building';
            }

            if ($this->columnExistsOnTable($tableName, 'total_ports')) {
                $columns[] = 'total_ports';
            }

            $rows = $connection->select($tableName, $columns, [
                'ORDER' => ['name' => 'ASC', 'asset_tag' => 'ASC'],
            ]);

            if (!is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $totalPorts = (int) ($row['total_ports'] ?? self::DEFAULT_TOTAL_PORTS);

                if ($totalPorts <= 0) {
                    $totalPorts = self::DEFAULT_TOTAL_PORTS;
                }

                $switches[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'asset_tag' => (string) ($row['asset_tag'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'model' => (string) ($row['model'] ?? ''),
                    'brand' => (string) ($row['brand'] ?? ''),
                    'serial_number' => (string) ($row['serial_number'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                    'location' => (string) ($row['location'] ?? ''),
                    'building' => (string) ($row['building'] ?? ''),
                    'total_ports' => $totalPorts,
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
     * @return array<string, mixed>|null
     */
    private function findSwitchAssetById(int $switchAssetId): ?array
    {
        if ($switchAssetId <= 0) {
            return null;
        }

        foreach ($this->collectSwitchRows() as $switch) {
            if ((int) ($switch['id'] ?? 0) === $switchAssetId) {
                return $switch;
            }
        }

        return null;
    }

    private function findIpForAsset(int $assetId): ?string
    {
        if ($assetId <= 0 || !$this->tableExists('ip_addresses')) {
            return null;
        }

        $row = $this->db()->get('ip_addresses', ['ip_address'], [
            'asset_id' => $assetId,
            'status' => ['assigned', 'reserved'],
            'ORDER' => ['updated_at' => 'DESC'],
        ]);

        if (!is_array($row) || !isset($row['ip_address'])) {
            return null;
        }

        $ip = trim((string) $row['ip_address']);

        return $ip !== '' ? $ip : null;
    }

    /**
     * @param array<string, mixed> $asset
     */
    private function formatAssetLabel(array $asset): string
    {
        $tag = trim((string) ($asset['asset_tag'] ?? ''));
        $name = trim((string) ($asset['name'] ?? ''));

        if ($tag !== '' && $name !== '') {
            return sprintf('%s — %s', $tag, $name);
        }

        return $tag !== '' ? $tag : $name;
    }

    private function isSwitchTypeSlug(string $slug): bool
    {
        return in_array($slug, $this->resolveSwitchTypeSlugs(), true);
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

    private function resolveTypeName(string $slug): string
    {
        foreach ($this->assetTypeModel->findAll() as $assetType) {
            if ((string) ($assetType['slug'] ?? '') === $slug) {
                return (string) ($assetType['name'] ?? $slug);
            }
        }

        return $slug;
    }

    private function columnExistsOnTable(string $tableName, string $columnName): bool
    {
        $statement = $this->db()->query(sprintf(
            "SHOW COLUMNS FROM `%s` LIKE '%s'",
            str_replace('`', '``', $tableName),
            str_replace("'", "''", $columnName)
        ));

        return $statement !== false && $statement->rowCount() > 0;
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
