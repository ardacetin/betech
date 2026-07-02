<?php

declare(strict_types=1);

namespace App\Services;

class AssetCsvImportService
{
    public function __construct(
        private readonly AssetColumnSchemaService $columnSchemaService,
    ) {
    }

    /**
     * @return list<string>
     */
    public function exportHeaders(?int $assetTypeId = null): array
    {
        return array_column($this->columnSchemaService->buildExportSchema($assetTypeId), 'label');
    }

    /**
     * @param array<string, mixed> $asset
     *
     * @return list<string>
     */
    public function mapAssetToExportRow(array $asset, ?int $assetTypeId = null): array
    {
        $row = [];

        foreach ($this->columnSchemaService->buildExportSchema($assetTypeId) as $definition) {
            $row[] = (string) ($asset[$definition['column']] ?? '');
        }

        return $row;
    }

    public function templateCsvContent(?int $assetTypeId = null): string
    {
        return $this->columnSchemaService->buildTemplateCsvContent($assetTypeId);
    }

    /**
     * @param list<array<string, mixed>> $assets
     */
    public function exportToCsv(array $assets, ?int $assetTypeId = null): string
    {
        $this->columnSchemaService->ensureConfiguredCustomColumns([], $assetTypeId);

        $lines = [$this->buildCsvLine($this->exportHeaders($assetTypeId))];

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $lines[] = $this->buildCsvLine($this->mapAssetToExportRow($asset, $assetTypeId));
        }

        return "\xEF\xBB\xBF" . implode('', $lines);
    }

    /**
     * @param list<string> $fields
     */
    private function buildCsvLine(array $fields): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, $fields, ',', '"', '\\');
        rewind($handle);
        $line = stream_get_contents($handle);
        fclose($handle);

        return is_string($line) ? $line : '';
    }
}
