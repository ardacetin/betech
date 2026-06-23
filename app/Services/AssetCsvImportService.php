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
    public function exportHeaders(): array
    {
        return array_column($this->columnSchemaService->buildExportSchema(), 'label');
    }

    /**
     * @param array<string, mixed> $asset
     *
     * @return list<string>
     */
    public function mapAssetToExportRow(array $asset): array
    {
        $row = [];

        foreach ($this->columnSchemaService->buildExportSchema() as $definition) {
            $row[] = (string) ($asset[$definition['column']] ?? '');
        }

        return $row;
    }

    public function templateCsvContent(): string
    {
        return $this->columnSchemaService->buildTemplateCsvContent();
    }

    /**
     * @param list<array<string, mixed>> $assets
     */
    public function exportToCsv(array $assets): string
    {
        $this->columnSchemaService->ensureConfiguredCustomColumns();

        $lines = [$this->buildCsvLine($this->exportHeaders())];

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $lines[] = $this->buildCsvLine($this->mapAssetToExportRow($asset));
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
