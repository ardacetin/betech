<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class InventoryImportService
{
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    private const KNOWN_STATUSES = ['ready', 'deployed', 'storage', 'broken'];

    private const STATUS_ALIASES = [
        'hazır' => 'ready',
        'hazir' => 'ready',
        'ready' => 'ready',
        'dağıtılmış' => 'deployed',
        'dagitilmis' => 'deployed',
        'deployed' => 'deployed',
        'depo' => 'storage',
        'storage' => 'storage',
        'arızalı' => 'broken',
        'arizali' => 'broken',
        'broken' => 'broken',
        'in stock' => 'storage',
        'instock' => 'storage',
        'stokta' => 'storage',
        'in use' => 'deployed',
        'inuse' => 'deployed',
        'in production' => 'deployed',
        'production' => 'deployed',
        'kullanımda' => 'deployed',
        'kullanimda' => 'deployed',
        'ordered' => 'ready',
        'out of order' => 'broken',
        'outoforder' => 'broken',
    ];

    /** Permanent corporate inventory CSV layout (12 columns, index 0-11). */
    private const MASTER_SCHEMA_COLUMN_COUNT = 12;

    private const MASTER_SCHEMA_TEMPLATE_CSV = <<<'CSV'
Demirbaş No,"Cihaz Adı",Model,Marka,"Seri No",Tür,Durum,Lokasyon,Bina,"Zimmetli Kişi","Mac Adresi 1","Mac Adresi 2"
ENV-GLPI-001,"BT Departman Laptop","Latitude 5540",Dell,SN-GLPI-001,Bilgisayar,deployed,"IT Depo","Merkez Kampüs",ahmet.yilmaz@sirket.com,AA:BB:CC:DD:EE:01,AA:BB:CC:DD:EE:02

CSV;

    /**
     * @var array<int, string>
     */
    private const MASTER_SCHEMA_FIELD_BY_INDEX = [
        0 => 'asset_tag',
        1 => 'name',
        2 => 'model',
        3 => 'brand',
        4 => 'serial_number',
        5 => 'type',
        6 => 'status',
        7 => 'location',
        8 => 'building',
        9 => 'assigned_to',
        10 => 'mac_address_1',
        11 => 'mac_address_2',
    ];

    public function __construct(
        private readonly Asset $assetModel,
    ) {
    }

    public static function templateCsvContent(): string
    {
        return self::MASTER_SCHEMA_TEMPLATE_CSV;
    }

    /**
     * @return array{
     *     imported: int,
     *     updated: int,
     *     assigned: int,
     *     skipped: int,
     *     failed: int,
     *     errors: list<array{row: int, message: string}>,
     *     warnings: list<array{row: int, message: string}>,
     *     created_assets: list<int>,
     *     updated_assets: list<int>
     * }
     */
    public function importFromUploadedFile(string $contents, string $originalFilename): array
    {
        if (trim($contents) === '') {
            return $this->emptyResultWithError(0, __('import_csv_empty'));
        }

        if (strlen($contents) > self::MAX_FILE_BYTES) {
            return $this->emptyResultWithError(0, __('import_file_too_large'));
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        try {
            return match ($extension) {
                'csv' => $this->importCsvContents($contents),
                'xlsx', 'xls' => $this->importSpreadsheetContents($contents, $extension),
                default => $this->emptyResultWithError(0, __('inventory_import_invalid_file_type')),
            };
        } catch (RuntimeException $exception) {
            return $this->emptyResultWithError(0, $exception->getMessage());
        }
    }

    /**
     * @return array{
     *     imported: int,
     *     updated: int,
     *     assigned: int,
     *     skipped: int,
     *     failed: int,
     *     errors: list<array{row: int, message: string}>,
     *     warnings: list<array{row: int, message: string}>,
     *     created_assets: list<int>,
     *     updated_assets: list<int>
     * }
     */
    private function importCsvContents(string $csvContent): array
    {
        $csvContent = $this->stripBom($csvContent);

        if (trim($csvContent) === '') {
            throw new RuntimeException(__('import_csv_empty'));
        }

        $state = $this->newImportState();
        $headerSkipped = false;
        $lineNumber = 0;

        foreach ($this->iteratePhysicalCsvLines($csvContent) as $line) {
            ++$lineNumber;

            if (trim($line) === '') {
                continue;
            }

            $columns = $this->parseMasterSchemaCsvLine($line);

            if (!$headerSkipped) {
                if (substr_count($line, ',') < self::MASTER_SCHEMA_COLUMN_COUNT - 1) {
                    throw new RuntimeException(__('import_csv_invalid_headers'));
                }

                $headerSkipped = true;

                continue;
            }

            $this->processRow(
                $lineNumber,
                $this->mapMasterSchemaRowByIndex($columns),
                $state
            );
        }

        if (!$headerSkipped) {
            throw new RuntimeException(__('import_csv_missing_headers'));
        }

        return $this->finalizeImportState($state);
    }

    /**
     * @return \Generator<int, string>
     */
    private function iteratePhysicalCsvLines(string $csvContent): \Generator
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException(__('inventory_import_temp_file_failed'));
        }

        try {
            if (fwrite($stream, $csvContent) === false) {
                throw new RuntimeException(__('inventory_import_temp_file_failed'));
            }

            rewind($stream);

            while (($line = fgets($stream)) !== false) {
                yield rtrim($line, "\r\n");
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return list<string>
     */
    private function parseMasterSchemaCsvLine(string $line): array
    {
        $rawColumns = explode(',', $line);
        $columns = [];

        for ($index = 0; $index < self::MASTER_SCHEMA_COLUMN_COUNT; ++$index) {
            $columns[] = $this->unwrapCsvField((string) ($rawColumns[$index] ?? ''));
        }

        return $columns;
    }

    private function unwrapCsvField(string $field): string
    {
        $field = trim($field);

        if ($field === '') {
            return '';
        }

        if (strlen($field) >= 2 && $field[0] === '"' && substr($field, -1) === '"') {
            $field = substr($field, 1, -1);
            $field = str_replace('""', '"', $field);
        }

        return $this->sanitizeCellValue($field);
    }

    /**
     * @return array{
     *     imported: int,
     *     updated: int,
     *     assigned: int,
     *     skipped: int,
     *     failed: int,
     *     errors: list<array{row: int, message: string}>,
     *     warnings: list<array{row: int, message: string}>,
     *     created_assets: list<int>,
     *     updated_assets: list<int>
     * }
     */
    private function importSpreadsheetContents(string $contents, string $extension): array
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'betech_inventory_import_');

        if ($tempPath === false) {
            throw new RuntimeException(__('inventory_import_temp_file_failed'));
        }

        $storedPath = $tempPath . '.' . $extension;

        try {
            if (file_put_contents($storedPath, $contents) === false) {
                throw new RuntimeException(__('inventory_import_temp_file_failed'));
            }

            $readerType = $extension === 'xls' ? 'Xls' : 'Xlsx';
            $reader = IOFactory::createReader($readerType);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($storedPath);
            $worksheet = $spreadsheet->getActiveSheet();

            return $this->importWorksheetRows($worksheet);
        } finally {
            @unlink($tempPath);
            @unlink($storedPath);
        }
    }

    /**
     * @return array{
     *     imported: int,
     *     updated: int,
     *     assigned: int,
     *     skipped: int,
     *     failed: int,
     *     errors: list<array{row: int, message: string}>,
     *     warnings: list<array{row: int, message: string}>,
     *     created_assets: list<int>,
     *     updated_assets: list<int>
     * }
     */
    private function importWorksheetRows(Worksheet $worksheet): array
    {
        $state = $this->newImportState();
        $headerSkipped = false;

        foreach ($worksheet->getRowIterator() as $row) {
            $lineNumber = $row->getRowIndex();
            $columns = [];

            foreach ($row->getCellIterator() as $cell) {
                $columns[] = $this->sanitizeCellValue((string) $cell->getValue());
            }

            if ($this->isEmptyRow($columns)) {
                continue;
            }

            $columns = $this->normalizeMasterSchemaColumns($columns);

            if (!$headerSkipped) {
                if (count(array_filter($columns, static fn (string $value): bool => $value !== '')) < self::MASTER_SCHEMA_COLUMN_COUNT) {
                    throw new RuntimeException(__('import_csv_invalid_headers'));
                }

                $headerSkipped = true;

                continue;
            }

            $this->processRow(
                $lineNumber,
                $this->mapMasterSchemaRowByIndex($columns),
                $state
            );
        }

        if (!$headerSkipped) {
            throw new RuntimeException(__('import_csv_missing_headers'));
        }

        return $this->finalizeImportState($state);
    }

    /**
     * @param list<string|null> $columns
     *
     * @return list<string>
     */
    private function normalizeMasterSchemaColumns(array $columns): array
    {
        $normalized = [];

        for ($index = 0; $index < self::MASTER_SCHEMA_COLUMN_COUNT; ++$index) {
            $normalized[] = $this->sanitizeCellValue((string) ($columns[$index] ?? ''));
        }

        return $normalized;
    }

    /**
     * @param list<string> $columns
     *
     * @return array<string, string>
     */
    private function mapMasterSchemaRowByIndex(array $columns): array
    {
        $values = [];

        for ($index = 0; $index < self::MASTER_SCHEMA_COLUMN_COUNT; ++$index) {
            $parsedValue = $columns[$index] ?? '';
            $fieldKey = self::MASTER_SCHEMA_FIELD_BY_INDEX[$index];

            if ($parsedValue === '') {
                continue;
            }

            $values[$fieldKey] = $parsedValue;
        }

        return $values;
    }

    /**
     * @return array{
     *     imported: int,
     *     updated: int,
     *     assigned: int,
     *     skipped: int,
     *     failed: int,
     *     errors: list<array{row: int, message: string}>,
     *     warnings: list<array{row: int, message: string}>,
     *     created_assets: list<int>,
     *     updated_assets: list<int>,
     *     seenSerials: array<string, true>,
     *     seenTags: array<string, true>
     * }
     */
    private function newImportState(): array
    {
        return [
            'imported' => 0,
            'updated' => 0,
            'assigned' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
            'warnings' => [],
            'created_assets' => [],
            'updated_assets' => [],
            'seenSerials' => [],
            'seenTags' => [],
        ];
    }

    /**
     * @param array<string, string> $values
     * @param array{
     *     imported: int,
     *     updated: int,
     *     assigned: int,
     *     skipped: int,
     *     failed: int,
     *     errors: list<array{row: int, message: string}>,
     *     warnings: list<array{row: int, message: string}>,
     *     created_assets: list<int>,
     *     updated_assets: list<int>,
     *     seenSerials: array<string, true>,
     *     seenTags: array<string, true>
     * } $state
     */
    private function processRow(int $rowNumber, array $values, array &$state): void
    {
        if ($this->isImportRowEmpty($values)) {
            $state['skipped']++;

            return;
        }

        $assetTag = trim($values['asset_tag'] ?? '');
        $name = trim($values['name'] ?? '');

        if ($assetTag === '') {
            $state['failed']++;
            $state['errors'][] = [
                'row' => $rowNumber,
                'message' => __('import_error_asset_tag_required'),
            ];

            return;
        }

        $existingAsset = $this->assetModel->findByAssetTag($assetTag);

        if ($name === '' && $existingAsset !== null) {
            $name = trim((string) ($existingAsset['name'] ?? ''));
        }

        if ($name === '') {
            $state['skipped']++;
            $state['warnings'][] = [
                'row' => $rowNumber,
                'message' => __('import_error_name_required'),
            ];

            return;
        }

        $serialNumber = trim($values['serial_number'] ?? '');
        $serialKey = $serialNumber !== '' ? mb_strtolower($serialNumber, 'UTF-8') : '';
        $tagKey = mb_strtolower($assetTag, 'UTF-8');

        if ($serialKey !== '' && isset($state['seenSerials'][$serialKey])) {
            $state['failed']++;
            $state['errors'][] = [
                'row' => $rowNumber,
                'message' => sprintf(__('import_error_duplicate_serial_in_file'), $serialNumber),
            ];

            return;
        }

        if (isset($state['seenTags'][$tagKey])) {
            $state['failed']++;
            $state['errors'][] = [
                'row' => $rowNumber,
                'message' => sprintf(__('import_error_duplicate_tag_in_file'), $assetTag),
            ];

            return;
        }

        $status = $this->normalizeStatus($values['status'] ?? '');

        if ($status === null) {
            $state['failed']++;
            $state['errors'][] = [
                'row' => $rowNumber,
                'message' => sprintf(
                    __('import_error_invalid_status'),
                    trim($values['status'] ?? '')
                ),
            ];

            return;
        }

        $assignedTo = trim($values['assigned_to'] ?? '');

        if ($assignedTo !== '') {
            $status = 'deployed';
        }

        $fields = [
            'asset_tag' => $assetTag,
            'name' => $name,
            'model' => trim($values['model'] ?? ''),
            'brand' => trim($values['brand'] ?? ''),
            'serial_number' => $serialNumber,
            'type' => trim($values['type'] ?? ''),
            'status' => $status,
            'location' => trim($values['location'] ?? ''),
            'building' => trim($values['building'] ?? ''),
            'assigned_to' => $assignedTo,
            'mac_address_1' => trim($values['mac_address_1'] ?? ''),
            'mac_address_2' => trim($values['mac_address_2'] ?? ''),
        ];

        try {
            $result = $this->assetModel->upsertFromImport($existingAsset, $fields);
            $asset = $result['asset'];

            if ($result['created']) {
                $state['created_assets'][] = (int) $asset['id'];
                $state['imported']++;
            } else {
                $state['updated_assets'][] = (int) $asset['id'];
                $state['updated']++;
            }
        } catch (\Throwable $exception) {
            $state['failed']++;
            $state['errors'][] = ['row' => $rowNumber, 'message' => $exception->getMessage()];

            return;
        }

        if ($assignedTo !== '') {
            $state['assigned']++;
        }

        if ($serialKey !== '') {
            $state['seenSerials'][$serialKey] = true;
        }

        $state['seenTags'][$tagKey] = true;
    }

    /**
     * @param array<string, string> $values
     */
    private function isImportRowEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $columns
     */
    private function isEmptyRow(array $columns): bool
    {
        foreach ($columns as $column) {
            if (trim($column) !== '') {
                return false;
            }
        }

        return true;
    }

    private function sanitizeCellValue(string $value): string
    {
        $value = $this->stripBom($value);
        $cleaned = preg_replace('/[\x{FEFF}\x{00A0}\x{200B}-\x{200D}\x{2060}]/u', ' ', $value) ?? $value;
        $cleaned = preg_replace('/[\x00-\x1F\x7F-\x9F\s]+/u', ' ', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned;

        return trim($cleaned, " \t\n\r\0\x0B\"'");
    }

    private function normalizeStatus(string $status): ?string
    {
        $trimmed = trim($status);

        if ($trimmed === '') {
            return 'ready';
        }

        $key = mb_strtolower($trimmed, 'UTF-8');
        $key = str_replace('_', ' ', $key);

        if (isset(self::STATUS_ALIASES[$key])) {
            return self::STATUS_ALIASES[$key];
        }

        $compactKey = str_replace(' ', '', $key);

        if (isset(self::STATUS_ALIASES[$compactKey])) {
            return self::STATUS_ALIASES[$compactKey];
        }

        if (in_array($key, self::KNOWN_STATUSES, true)) {
            return $key;
        }

        return null;
    }

    private function stripBom(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        return $content;
    }

    /**
     * @param array{
     *     imported: int,
     *     updated: int,
     *     assigned: int,
     *     skipped: int,
     *     failed: int,
     *     errors: list<array{row: int, message: string}>,
     *     warnings: list<array{row: int, message: string}>,
     *     created_assets: list<int>,
     *     updated_assets: list<int>,
     *     seenSerials: array<string, true>,
     *     seenTags: array<string, true>
     * } $state
     *
     * @return array{
     *     imported: int,
     *     updated: int,
     *     assigned: int,
     *     skipped: int,
     *     failed: int,
     *     errors: list<array{row: int, message: string}>,
     *     warnings: list<array{row: int, message: string}>,
     *     created_assets: list<int>,
     *     updated_assets: list<int>
     * }
     */
    private function finalizeImportState(array $state): array
    {
        return [
            'imported' => $state['imported'],
            'updated' => $state['updated'],
            'assigned' => $state['assigned'],
            'skipped' => $state['skipped'],
            'failed' => $state['failed'],
            'errors' => $state['errors'],
            'warnings' => $state['warnings'],
            'created_assets' => $state['created_assets'],
            'updated_assets' => $state['updated_assets'],
        ];
    }

    /**
     * @return array{
     *     imported: int,
     *     updated: int,
     *     assigned: int,
     *     skipped: int,
     *     failed: int,
     *     errors: list<array{row: int, message: string}>,
     *     warnings: list<array{row: int, message: string}>,
     *     created_assets: list<int>,
     *     updated_assets: list<int>
     * }
     */
    private function emptyResultWithError(int $row, string $message): array
    {
        return [
            'imported' => 0,
            'updated' => 0,
            'assigned' => 0,
            'skipped' => 0,
            'failed' => $row > 0 ? 1 : 0,
            'errors' => $row > 0
                ? [['row' => $row, 'message' => $message]]
                : [['row' => 0, 'message' => $message]],
            'warnings' => [],
            'created_assets' => [],
            'updated_assets' => [],
        ];
    }
}
