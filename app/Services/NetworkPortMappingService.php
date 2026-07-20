<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssetType;
use App\Models\AssetsGlobalRegistry;
use App\Models\NetworkPortMapping;
use RuntimeException;

class NetworkPortMappingService
{
    private const SWITCH_TYPE_SLUG = 'switchler';

    /**
     * Canonical + legacy switch type slugs used by inventory / migrations.
     *
     * @var list<string>
     */
    private const SWITCH_TYPE_SLUGS = [
        'switchler',
        'ag_anahtarlari',
        'switches',
        'ag-anahtarlari',
        'ag-anahtari-switch',
    ];

    /**
     * Orphan tables that may still hold switch rows after a slug rename
     * created an empty assets_switchler without migrating data.
     *
     * @var array<string, string> table => fallback slug
     */
    private const LEGACY_SWITCH_TABLES = [
        'assets_ag_anahtarlari' => 'ag_anahtarlari',
        'assets_ag_anahtari_switch' => 'ag-anahtari-switch',
        'assets_switches' => 'switches',
    ];

    private const DEFAULT_TOTAL_PORTS = 48;

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
        try {
            $directory = [];

            foreach ($this->collectSwitchRows() as $switch) {
                $switchId = (int) ($switch['id'] ?? 0);
                $totalPorts = (int) ($switch['total_ports'] ?? self::DEFAULT_TOTAL_PORTS);

                if ($totalPorts <= 0) {
                    $totalPorts = self::DEFAULT_TOTAL_PORTS;
                }

                try {
                    $usedPorts = $this->networkPortMappingModel->countBySwitchId($switchId);
                } catch (\Throwable) {
                    $usedPorts = 0;
                }

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
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSwitchPortMatrix(int $switchAssetId): ?array
    {
        try {
            $switch = $this->findSwitchAssetById($switchAssetId);

            if ($switch === null) {
                return null;
            }

            $totalPorts = (int) ($switch['total_ports'] ?? self::DEFAULT_TOTAL_PORTS);

            if ($totalPorts <= 0) {
                $totalPorts = self::DEFAULT_TOTAL_PORTS;
            }

            $mappingByPort = [];

            try {
                foreach ($this->networkPortMappingModel->findAllBySwitchId($switchAssetId) as $mapping) {
                    $portNumber = trim((string) ($mapping['port_number'] ?? ''));

                    if ($portNumber === '') {
                        continue;
                    }

                    try {
                        $mappingByPort[$portNumber] = $this->enrichMapping($mapping);
                    } catch (\Throwable) {
                        $mappingByPort[$portNumber] = $mapping;
                    }
                }
            } catch (\Throwable) {
                $mappingByPort = [];
            }

            $ports = [];

            for ($index = 1; $index <= $totalPorts; ++$index) {
                $portKey = (string) $index;
                $mapping = $mappingByPort[$portKey] ?? null;

                $ports[] = [
                    'port_number' => $portKey,
                    'occupied' => $mapping !== null && $this->mappingHasContent($mapping),
                    'mapping' => $mapping,
                ];
            }

            $portsPerRow = (int) ceil($totalPorts / 2);

            $usedPorts = 0;

            try {
                $usedPorts = $this->networkPortMappingModel->countBySwitchId($switchAssetId);
            } catch (\Throwable) {
                $usedPorts = count($mappingByPort);
            }

            return [
                'switch' => [
                    'id' => (int) ($switch['id'] ?? 0),
                    'asset_tag' => (string) ($switch['asset_tag'] ?? ''),
                    'name' => (string) ($switch['name'] ?? ''),
                    'location' => (string) ($switch['location'] ?? ''),
                    'building' => (string) ($switch['building'] ?? ''),
                    'total_ports' => $totalPorts,
                    'used_ports' => $usedPorts,
                    'asset_type_slug' => (string) ($switch['asset_type_slug'] ?? ''),
                ],
                'ports_per_row' => $portsPerRow,
                'ports' => $ports,
            ];
        } catch (\Throwable) {
            return null;
        }
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

    public function savePortDescription(int $switchAssetId, string $portNumber, string $description): void
    {
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

        $trimmedDescription = trim($description);

        if ($trimmedDescription === '') {
            $this->networkPortMappingModel->deleteBySwitchAndPort($switchAssetId, (string) $portIndex);

            return;
        }

        if (mb_strlen($trimmedDescription) > 2000) {
            throw new RuntimeException(__('switch_port_description_too_long'));
        }

        $this->networkPortMappingModel->upsertPortDescription(
            $switchAssetId,
            (string) $portIndex,
            $trimmedDescription
        );
    }

    public function assignPort(
        int $switchAssetId,
        string $portNumber,
        string $sourceAssetType,
        int $sourceAssetId
    ): void {
        // Legacy inventory-linked assignment path kept for older callers.
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
                'description' => $this->formatAssetLabel($asset),
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
        $description = trim((string) ($mapping['description'] ?? ''));

        if ($description === '' && $asset !== null) {
            $description = $this->formatAssetLabel($asset);
        }

        return [
            ...$mapping,
            'description' => $description,
            'asset_name' => $description !== '' ? $description : (string) ($asset['name'] ?? ''),
            'asset_tag' => (string) ($asset['asset_tag'] ?? ''),
            'assigned_to' => (string) ($asset['assigned_to'] ?? ''),
            'ip_address' => $sourceId > 0 ? $this->findIpForAsset($sourceId) : null,
            'asset_type_slug' => (string) ($asset['asset_type_slug'] ?? $mapping['source_asset_type'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $mapping
     */
    private function mappingHasContent(array $mapping): bool
    {
        if (trim((string) ($mapping['description'] ?? '')) !== '') {
            return true;
        }

        return (int) ($mapping['source_asset_id'] ?? 0) > 0
            || trim((string) ($mapping['asset_name'] ?? '')) !== '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectSwitchRows(): array
    {
        try {
            $switches = [];
            $seenTables = [];
            $seenKeys = [];

            foreach ($this->resolveSwitchTypeSources() as $source) {
                $tableName = $source['table'];
                $slug = $source['slug'];

                if (isset($seenTables[$tableName])) {
                    continue;
                }

                $seenTables[$tableName] = true;

                foreach ($this->readSwitchRowsFromTable($tableName, $slug) as $switch) {
                    $dedupeKey = $this->switchDedupeKey($switch);

                    if (isset($seenKeys[$dedupeKey])) {
                        continue;
                    }

                    $seenKeys[$dedupeKey] = true;
                    $switches[] = $switch;
                }
            }

            if ($switches !== []) {
                usort($switches, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));
            }

            return array_map(static function (array $switch): array {
                unset($switch['_source_table']);

                return $switch;
            }, $switches);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{slug: string, table: string}>
     */
    private function resolveSwitchTypeSources(): array
    {
        $sources = [];
        $seenTables = [];

        foreach ($this->resolveSwitchTypeSlugs() as $slug) {
            $typeContext = $this->assetTypeTableService->resolveWhitelistedType($slug);
            $tableName = is_array($typeContext)
                ? (string) ($typeContext['table'] ?? '')
                : $this->assetTypeTableService->tableNameForSlug($slug);

            if ($tableName === '' || isset($seenTables[$tableName]) || !$this->tableExists($tableName)) {
                continue;
            }

            $resolvedSlug = is_array($typeContext) && trim((string) ($typeContext['slug'] ?? '')) !== ''
                ? (string) $typeContext['slug']
                : $slug;

            $seenTables[$tableName] = true;
            $sources[] = [
                'slug' => $resolvedSlug,
                'table' => $tableName,
            ];
        }

        // Safety net: slug may already be "switchler" while rows remain in a legacy table.
        foreach (self::LEGACY_SWITCH_TABLES as $legacyTable => $fallbackSlug) {
            if (isset($seenTables[$legacyTable]) || !$this->tableExists($legacyTable)) {
                continue;
            }

            $seenTables[$legacyTable] = true;
            $sources[] = [
                'slug' => $this->canonicalSwitchSlug($fallbackSlug),
                'table' => $legacyTable,
            ];
        }

        return $sources;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readSwitchRowsFromTable(string $tableName, string $slug): array
    {
        try {
            $tableColumns = $this->describeTableColumns($tableName);

            if ($tableColumns === [] || !in_array('id', $tableColumns, true)) {
                return [];
            }

            $selectColumns = $this->buildSelectColumns($tableColumns);
            $orderClause = $this->buildOrderClause($tableColumns);
            $connection = $this->databaseService->getConnection();

            $query = ['ORDER' => $orderClause];

            if ($orderClause === []) {
                unset($query['ORDER']);
            }

            $rows = $connection->select($tableName, $selectColumns, $query);

            if (!is_array($rows)) {
                return [];
            }

            $switches = [];

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $totalPorts = self::DEFAULT_TOTAL_PORTS;

                if (in_array('total_ports', $tableColumns, true)) {
                    $totalPorts = (int) ($row['total_ports'] ?? self::DEFAULT_TOTAL_PORTS);
                }

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
                    '_source_table' => $tableName,
                ];
            }

            return $switches;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $switch
     */
    private function switchDedupeKey(array $switch): string
    {
        $assetTag = trim((string) ($switch['asset_tag'] ?? ''));

        if ($assetTag !== '') {
            return 'tag:' . strtolower($assetTag);
        }

        $serial = trim((string) ($switch['serial_number'] ?? ''));

        if ($serial !== '') {
            return 'serial:' . strtolower($serial);
        }

        return sprintf(
            'row:%s:%d',
            (string) ($switch['_source_table'] ?? $switch['asset_type_slug'] ?? 'unknown'),
            (int) ($switch['id'] ?? 0)
        );
    }

    private function canonicalSwitchSlug(string $fallbackSlug): string
    {
        foreach ($this->assetTypeModel->findAll() as $assetType) {
            $slug = strtolower(trim((string) ($assetType['slug'] ?? '')));

            if ($slug === self::SWITCH_TYPE_SLUG) {
                return self::SWITCH_TYPE_SLUG;
            }
        }

        return $fallbackSlug;
    }

    /**
     * @return list<string>
     */
    private function describeTableColumns(string $tableName): array
    {
        try {
            $statement = $this->db()->query(sprintf(
                'DESCRIBE `%s`',
                str_replace('`', '``', $tableName)
            ));

            if ($statement === false) {
                return [];
            }

            $columns = [];

            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                if (!is_array($row)) {
                    continue;
                }

                $field = trim((string) ($row['Field'] ?? ''));

                if ($field !== '') {
                    $columns[] = $field;
                }
            }

            return $columns;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param list<string> $tableColumns
     *
     * @return list<string>
     */
    private function buildSelectColumns(array $tableColumns): array
    {
        $wanted = [
            'id',
            'asset_tag',
            'name',
            'model',
            'brand',
            'serial_number',
            'status',
            'location',
            'building',
            'total_ports',
        ];

        $selected = array_values(array_intersect($wanted, $tableColumns));

        if ($selected === []) {
            return ['id'];
        }

        return $selected;
    }

    /**
     * @param list<string> $tableColumns
     *
     * @return array<string, string>
     */
    private function buildOrderClause(array $tableColumns): array
    {
        $order = [];

        if (in_array('name', $tableColumns, true)) {
            $order['name'] = 'ASC';
        }

        if (in_array('asset_tag', $tableColumns, true)) {
            $order['asset_tag'] = 'ASC';
        }

        if ($order === [] && in_array('id', $tableColumns, true)) {
            $order['id'] = 'ASC';
        }

        return $order;
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
        return in_array(strtolower(trim($slug)), $this->resolveSwitchTypeSlugs(), true);
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
        return in_array($columnName, $this->describeTableColumns($tableName), true);
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
