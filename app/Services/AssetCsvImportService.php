<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;

class AssetCsvImportService
{
    /**
     * @return list<string>
     */
    public static function exportHeaders(): array
    {
        return [
            'Demirbaş No',
            'Cihaz Adı',
            'Model',
            'Marka',
            'Seri No',
            'Tür',
            'Durum',
            'Lokasyon',
            'Bina',
            'Zimmetli Kişi',
            'Mac Adresi 1',
            'Mac Adresi 2',
        ];
    }

    /**
     * @param array<string, mixed> $asset
     *
     * @return list<string>
     */
    public static function mapAssetToExportRow(array $asset): array
    {
        return [
            (string) ($asset['asset_tag'] ?? ''),
            (string) ($asset['name'] ?? ''),
            (string) ($asset['model'] ?? ''),
            (string) ($asset['brand'] ?? ''),
            (string) ($asset['serial_number'] ?? ''),
            (string) ($asset['type'] ?? ''),
            (string) ($asset['status'] ?? ''),
            (string) ($asset['location'] ?? ''),
            (string) ($asset['building'] ?? ''),
            (string) ($asset['assigned_to'] ?? ''),
            (string) ($asset['mac_address_1'] ?? ''),
            (string) ($asset['mac_address_2'] ?? ''),
        ];
    }

    public static function templateCsvContent(): string
    {
        return InventoryImportService::templateCsvContent();
    }

    /**
     * @param list<array<string, mixed>> $assets
     */
    public function exportToCsv(array $assets): string
    {
        $lines = [self::buildCsvLine(self::exportHeaders())];

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $lines[] = self::buildCsvLine(self::mapAssetToExportRow($asset));
        }

        return "\xEF\xBB\xBF" . implode('', $lines);
    }

    /**
     * @param list<string> $fields
     */
    private static function buildCsvLine(array $fields): string
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
