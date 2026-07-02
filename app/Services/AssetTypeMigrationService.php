<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssetType;
use App\Models\AssetsGlobalRegistry;

class AssetTypeMigrationService
{
    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly AssetType $assetTypeModel,
        private readonly AssetTypeTableService $assetTypeTableService,
        private readonly ?AssetsGlobalRegistry $assetsGlobalRegistry = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public function ensurePerTypeTablesAndMigrateLegacyData(): array
    {
        $warnings = [];
        $connection = $this->databaseService->getConnection();

        if (!$this->tableExists('assets') || !$this->tableExists('asset_types')) {
            return $warnings;
        }

        foreach ($this->assetTypeModel->findAll() as $assetType) {
            $typeId = (int) ($assetType['id'] ?? 0);
            $slug = (string) ($assetType['slug'] ?? '');

            if ($typeId <= 0 || $slug === '') {
                continue;
            }

            $tableName = $this->assetTypeTableService->createTableForSlug($slug);

            if (!$this->tableExists('asset_registry')) {
                continue;
            }

            $this->migrateLegacyRowsForType($typeId, $tableName, $warnings);
        }

        foreach ($this->backfillGlobalRegistry() as $warning) {
            $warnings[] = $warning;
        }

        return $warnings;
    }

    /**
     * @return list<string>
     */
    public function backfillGlobalRegistry(): array
    {
        $warnings = [];

        if ($this->assetsGlobalRegistry === null || !$this->assetsGlobalRegistry->tableExists()) {
            return $warnings;
        }

        $connection = $this->databaseService->getConnection();
        $synced = 0;

        foreach ($this->assetTypeModel->findAll() as $assetType) {
            $typeId = (int) ($assetType['id'] ?? 0);
            $slug = trim((string) ($assetType['slug'] ?? ''));

            if ($typeId <= 0 || $slug === '') {
                continue;
            }

            $tableName = $this->assetTypeTableService->tableNameForSlug($slug);

            if (!$this->tableExists($tableName)) {
                continue;
            }

            $rows = $connection->select($tableName, '*');

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $assetId = (int) ($row['id'] ?? 0);

                if ($assetId <= 0) {
                    continue;
                }

                $this->assetsGlobalRegistry->sync($assetId, $slug, $row);
                ++$synced;
            }
        }

        if ($synced > 0) {
            $warnings[] = sprintf('Synchronized %d asset row(s) into assets_global_registry.', $synced);
        }

        return $warnings;
    }

    /**
     * @param list<string> $warnings
     */
    private function migrateLegacyRowsForType(int $typeId, string $tableName, array &$warnings): void
    {
        $connection = $this->databaseService->getConnection();

        if (!$this->columnExists('assets', 'asset_type_id')) {
            return;
        }

        $statement = $connection->query(
            'SELECT * FROM `assets` WHERE `asset_type_id` = ' . (int) $typeId . ' ORDER BY `id` ASC'
        );

        if ($statement === false) {
            return;
        }

        $rows = $statement->fetchAll();

        if ($rows === []) {
            return;
        }

        $typeColumns = $this->assetTypeTableService->listTableColumns($tableName);
        $migrated = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $assetId = (int) ($row['id'] ?? 0);

            if ($assetId <= 0) {
                continue;
            }

            if ($connection->has($tableName, ['id' => $assetId])) {
                $this->ensureRegistryRow($assetId, $typeId);

                continue;
            }

            $insert = [];

            foreach ($typeColumns as $column) {
                if ($column === 'id' || !array_key_exists($column, $row)) {
                    continue;
                }

                $insert[$column] = $row[$column];
            }

            $insert['id'] = $assetId;
            $connection->insert($tableName, $insert);
            $this->ensureRegistryRow($assetId, $typeId);
            ++$migrated;
        }

        if ($migrated > 0) {
            $warnings[] = sprintf(
                'Migrated %d legacy asset row(s) into `%s`.',
                $migrated,
                $tableName
            );
        }
    }

    private function ensureRegistryRow(int $assetId, int $typeId): void
    {
        $connection = $this->databaseService->getConnection();

        if ($connection->has('asset_registry', ['id' => $assetId])) {
            $connection->update('asset_registry', [
                'asset_type_id' => $typeId,
            ], [
                'id' => $assetId,
            ]);

            return;
        }

        $connection->insert('asset_registry', [
            'id' => $assetId,
            'asset_type_id' => $typeId,
        ]);
    }

    private function tableExists(string $tableName): bool
    {
        $connection = $this->databaseService->getConnection();
        $statement = $connection->query(
            'SELECT COUNT(*) AS total
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ' . $connection->quote($tableName)
        );

        if ($statement === false) {
            return false;
        }

        $row = $statement->fetch();

        return (int) ($row['total'] ?? 0) > 0;
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $connection = $this->databaseService->getConnection();
        $statement = $connection->query(
            'SELECT COUNT(*) AS total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ' . $connection->quote($tableName) . '
              AND COLUMN_NAME = ' . $connection->quote($columnName)
        );

        if ($statement === false) {
            return false;
        }

        $row = $statement->fetch();

        return (int) ($row['total'] ?? 0) > 0;
    }
}
