<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\Personnel;
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

    /** Permanent corporate inventory CSV layout (10 columns, index 0-9). */
    private const MASTER_SCHEMA_COLUMN_COUNT = 10;

    /**
     * Exact downloadable template: header row + sample data row.
     */
    private const MASTER_SCHEMA_TEMPLATE_CSV = <<<'CSV'
Demirbaş No,"Cihaz Adı",Model,Marka,"Seri No",Tür,Durum,Lokasyon,Bina,"Zimmetli Kişi"
ENV-GLPI-001,"BT Departman Laptop","Latitude 5540",Dell,SN-GLPI-001,Bilgisayar,deployed,"IT Depo","Merkez Kampüs",ahmet.yilmaz@sirket.com

CSV;

    /**
     * @var array<int, string>
     */
    private const MASTER_SCHEMA_FIELD_BY_INDEX = [
        0 => 'asset_tag',
        1 => 'device_name',
        2 => 'device_model',
        3 => 'brand',
        4 => 'serial_number',
        5 => 'category',
        6 => 'status',
        7 => 'location',
        8 => 'building',
        9 => 'personnel',
    ];

    public function __construct(
        private readonly Asset $assetModel,
        private readonly Category $categoryModel,
        private readonly Location $locationModel,
        private readonly Personnel $personnelModel,
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
     *     updated_assets: list<int>,
     *     created_categories: list<string>,
     *     created_locations: list<string>
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
     *     updated_assets: list<int>,
     *     created_categories: list<string>,
     *     created_locations: list<string>
     * }
     */
    private function importCsvContents(string $csvContent): array
    {
        $csvContent = $this->stripBom($csvContent);
        $lines = preg_split('/\R/', $csvContent) ?? [];

        if ($lines === []) {
            throw new RuntimeException(__('import_csv_empty'));
        }

        $state = $this->newImportState();
        $headerSkipped = false;

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            $columns = str_getcsv($line, ',', '"', '\\');

            if ($columns === [null] || $columns === false) {
                continue;
            }

            $columns = $this->normalizeMasterSchemaColumns($columns);

            if (!$headerSkipped) {
                if (count($columns) < self::MASTER_SCHEMA_COLUMN_COUNT) {
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
     *     created_categories: list<string>,
     *     created_locations: list<string>
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
     *     updated_assets: list<int>,
     *     created_categories: list<string>,
     *     created_locations: list<string>
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
                $columns[] = $this->parseMasterCellValue((string) $cell->getValue());
            }

            if ($this->isEmptyRow($columns)) {
                continue;
            }

            $columns = $this->normalizeMasterSchemaColumns($columns);

            if (!$headerSkipped) {
                if (count($columns) < self::MASTER_SCHEMA_COLUMN_COUNT) {
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
            $normalized[] = $this->parseMasterCellValue((string) ($columns[$index] ?? ''));
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
            $rawValue = $columns[$index] ?? '';
            $fieldKey = self::MASTER_SCHEMA_FIELD_BY_INDEX[$index];
            $parsedValue = $this->parseMasterCellValue($rawValue);

            if ($parsedValue === '') {
                continue;
            }

            $values[$fieldKey] = $parsedValue;
        }

        return $values;
    }

    private function parseMasterCellValue(string $value): string
    {
        return $this->sanitizeCellValue($value);
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
     *     categoryCache: array<string, int>,
     *     locationCache: array<string, int>,
     *     createdCategories: list<string>,
     *     createdLocations: list<string>,
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
            'categoryCache' => [],
            'locationCache' => [],
            'createdCategories' => [],
            'createdLocations' => [],
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
     *     categoryCache: array<string, int>,
     *     locationCache: array<string, int>,
     *     createdCategories: list<string>,
     *     createdLocations: list<string>,
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

        $deviceName = trim($values['device_name'] ?? '');
        $deviceModel = trim($values['device_model'] ?? '');
        $serialNumber = trim($values['serial_number'] ?? '');
        $assetTag = trim($values['asset_tag'] ?? '');
        $categoryName = trim($values['category'] ?? '');
        $name = $deviceName;

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

        if ($categoryName === '') {
            if ($existingAsset !== null) {
                $categoryId = (int) ($existingAsset['category_id'] ?? 0);
            } else {
                $state['skipped']++;
                $state['warnings'][] = [
                    'row' => $rowNumber,
                    'message' => __('import_error_category_required'),
                ];

                return;
            }
        } else {
            try {
                $categoryId = $this->resolveCategoryId(
                    $categoryName,
                    $state['categoryCache'],
                    $state['createdCategories']
                );
            } catch (\Throwable $exception) {
                $state['failed']++;
                $state['errors'][] = ['row' => $rowNumber, 'message' => $exception->getMessage()];

                return;
            }
        }

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

        try {
            $locationId = $this->resolveLocationId(
                trim($values['location'] ?? ''),
                trim($values['building'] ?? ''),
                $state['locationCache'],
                $state['createdLocations']
            );
        } catch (\Throwable $exception) {
            $state['failed']++;
            $state['errors'][] = ['row' => $rowNumber, 'message' => $exception->getMessage()];

            return;
        }

        $personnelRaw = trim($values['personnel'] ?? '');
        $personnelId = $this->resolvePersonnelId($personnelRaw);

        if ($personnelRaw !== '' && $personnelId === null) {
            $state['warnings'][] = [
                'row' => $rowNumber,
                'message' => sprintf(__('inventory_import_personnel_not_found'), $personnelRaw),
            ];
        }

        if ($personnelId !== null) {
            $status = 'deployed';
        }

        $properties = $this->buildImportProperties($values, $deviceModel, $existingAsset);

        $coreFields = [
            'name' => $name,
            'category_id' => $categoryId,
            'status' => $status,
            'location_id' => $locationId,
            'asset_tag' => $assetTag,
        ];

        if ($serialNumber !== '') {
            $coreFields['serial_number'] = $serialNumber;
        }

        if ($personnelId !== null) {
            $coreFields['personnel_id'] = $personnelId;
        }

        try {
            $result = $this->assetModel->upsertFromImport($existingAsset, $coreFields, $properties);
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

        if ($personnelId !== null) {
            $state['assigned']++;
        }

        if ($serialKey !== '') {
            $state['seenSerials'][$serialKey] = true;
        }

        $state['seenTags'][$tagKey] = true;
    }

    /**
     * @param array<string, string> $values
     * @param array<string, mixed>|null $existingAsset
     *
     * @return array<string, mixed>
     */
    private function buildImportProperties(array $values, string $deviceModel, ?array $existingAsset): array
    {
        $properties = is_array($existingAsset['properties'] ?? null)
            ? $existingAsset['properties']
            : [];

        $brand = trim($values['brand'] ?? '');

        if ($brand !== '') {
            $properties['brand'] = $brand;
        }

        $model = $deviceModel !== '' ? $deviceModel : trim($values['device_model'] ?? '');

        if ($model !== '') {
            $properties['model'] = $model;
        }

        return $properties;
    }

    private function resolvePersonnelId(string $raw): ?int
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (filter_var($raw, FILTER_VALIDATE_EMAIL) !== false) {
            $person = $this->personnelModel->findByEmail(strtolower($raw));

            if ($person !== null && ($person['status'] ?? '') !== Personnel::STATUS_OFFBOARDED) {
                return (int) $person['id'];
            }
        }

        $candidates = $this->personnelModel->searchActive($raw, 10);

        foreach ($candidates as $candidate) {
            if (strcasecmp((string) ($candidate['name'] ?? ''), $raw) === 0) {
                return (int) $candidate['id'];
            }

            if (strcasecmp((string) ($candidate['email'] ?? ''), $raw) === 0) {
                return (int) $candidate['id'];
            }

            if (strcasecmp((string) ($candidate['external_id'] ?? ''), $raw) === 0) {
                return (int) $candidate['id'];
            }
        }

        if (count($candidates) === 1) {
            return (int) $candidates[0]['id'];
        }

        return null;
    }

    /**
     * @param array<string, int> $cache
     * @param list<string> $createdNames
     */
    private function resolveCategoryId(string $categoryName, array &$cache, array &$createdNames): int
    {
        $cacheKey = mb_strtolower($categoryName, 'UTF-8');

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $existing = $this->categoryModel->findByName($categoryName);

        if ($existing !== null) {
            $cache[$cacheKey] = (int) $existing['id'];

            return $cache[$cacheKey];
        }

        $created = $this->categoryModel->create($categoryName, []);
        $cache[$cacheKey] = (int) $created['id'];
        $createdNames[] = $categoryName;

        return $cache[$cacheKey];
    }

    /**
     * @param array<string, int> $cache
     * @param list<string> $createdNames
     */
    private function resolveLocationId(
        string $locationName,
        string $building,
        array &$cache,
        array &$createdNames
    ): ?int {
        if ($locationName === '') {
            return null;
        }

        $cacheKey = mb_strtolower($locationName . '|' . $building, 'UTF-8');

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $existing = $this->locationModel->findByName($locationName, $building !== '' ? $building : null);

        if ($existing !== null) {
            $cache[$cacheKey] = (int) $existing['id'];

            return $cache[$cacheKey];
        }

        $created = $this->locationModel->create(
            $locationName,
            $building !== '' ? $building : null,
            null
        );
        $cache[$cacheKey] = (int) $created['id'];
        $createdNames[] = $building !== ''
            ? $building . ' / ' . $locationName
            : $locationName;

        return $cache[$cacheKey];
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
     *     categoryCache: array<string, int>,
     *     locationCache: array<string, int>,
     *     createdCategories: list<string>,
     *     createdLocations: list<string>,
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
     *     updated_assets: list<int>,
     *     created_categories: list<string>,
     *     created_locations: list<string>
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
            'created_categories' => $state['createdCategories'],
            'created_locations' => $state['createdLocations'],
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
     *     updated_assets: list<int>,
     *     created_categories: list<string>,
     *     created_locations: list<string>
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
            'created_categories' => [],
            'created_locations' => [],
        ];
    }
}
