<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\Personnel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class InventoryImportService
{
    private const MAX_FILE_BYTES = 5 * 1024 * 1024;

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
    ];

    /**
     * @var array<string, string>
     */
    private const HEADER_ALIASES = [
        'name' => 'name',
        'ad' => 'name',
        'model' => 'name',
        'envanter_adi' => 'name',
        'asset_name' => 'name',
        'serial_number' => 'serial_number',
        'serial' => 'serial_number',
        'seri_numarasi' => 'serial_number',
        'seri_no' => 'serial_number',
        'seri_numara' => 'serial_number',
        'category' => 'category',
        'kategori' => 'category',
        'category_name' => 'category',
        'status' => 'status',
        'durum' => 'status',
        'location' => 'location',
        'lokasyon' => 'location',
        'location_name' => 'location',
        'building' => 'building',
        'bina' => 'building',
        'campus' => 'building',
        'asset_tag' => 'asset_tag',
        'envanter_etiketi' => 'asset_tag',
        'tag' => 'asset_tag',
        'personnel' => 'personnel',
        'personel' => 'personnel',
        'kullanici' => 'personnel',
        'kullanıcı' => 'personnel',
        'zimmetli_kisi' => 'personnel',
        'zimmetli_kişi' => 'personnel',
        'zimmetli' => 'personnel',
        'assignee' => 'personnel',
        'user' => 'personnel',
        'email' => 'personnel',
    ];

    public function __construct(
        private readonly Asset $assetModel,
        private readonly Category $categoryModel,
        private readonly Location $locationModel,
        private readonly Personnel $personnelModel
    ) {
    }

    public static function templateCsvContent(): string
    {
        $headers = ['Seri No', 'Model', 'Kategori', 'Zimmetli Kişi', 'Durum', 'Lokasyon', 'Envanter Etiketi'];
        $example = [
            'SN-GLPI-001',
            'Dell Latitude 5540',
            'Laptop',
            'ahmet.yilmaz@sirket.com',
            'deployed',
            'IT Depo',
            '',
        ];

        return self::buildCsvLine($headers) . self::buildCsvLine($example);
    }

    /**
     * @return array{
     *     imported: int,
     *     updated: int,
     *     assigned: int,
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
            $rows = match ($extension) {
                'csv' => $this->parseCsvRows($contents),
                'xlsx', 'xls' => $this->parseSpreadsheetRows($contents, $extension),
                default => throw new RuntimeException(__('inventory_import_invalid_file_type')),
            };
        } catch (RuntimeException $exception) {
            return $this->emptyResultWithError(0, $exception->getMessage());
        }

        if ($rows === []) {
            return $this->emptyResultWithError(0, __('import_csv_no_data_rows'));
        }

        return $this->processRows($rows);
    }

    /**
     * @return list<array{row: int, values: array<string, string>}>
     */
    private function parseCsvRows(string $csvContent): array
    {
        $csvContent = $this->stripBom($csvContent);
        $lines = preg_split('/\R/', $csvContent) ?? [];

        if ($lines === []) {
            throw new RuntimeException(__('import_csv_empty'));
        }

        $rows = [];
        $headerMap = null;

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

            if ($headerMap === null) {
                $headerMap = $this->mapHeaders($columns);

                if ($headerMap === []) {
                    throw new RuntimeException(__('import_csv_invalid_headers'));
                }

                continue;
            }

            $rows[] = [
                'row' => $lineNumber,
                'values' => $this->normalizeRowValues($columns, $headerMap),
            ];
        }

        if ($headerMap === null) {
            throw new RuntimeException(__('import_csv_missing_headers'));
        }

        return $rows;
    }

    /**
     * @return list<array{row: int, values: array<string, string>}>
     */
    private function parseSpreadsheetRows(string $contents, string $extension): array
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
            $spreadsheet = IOFactory::createReader($readerType)->load($storedPath);
            $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

            if ($sheetRows === []) {
                throw new RuntimeException(__('import_csv_empty'));
            }

            $headerMap = null;
            $rows = [];

            foreach ($sheetRows as $index => $columns) {
                $lineNumber = $index + 1;

                if (!is_array($columns)) {
                    continue;
                }

                $normalizedColumns = array_map(
                    static fn (mixed $value): string => trim((string) $value),
                    $columns
                );

                if ($this->isEmptyRow($normalizedColumns)) {
                    continue;
                }

                if ($headerMap === null) {
                    $headerMap = $this->mapHeaders($normalizedColumns);

                    if ($headerMap === []) {
                        throw new RuntimeException(__('import_csv_invalid_headers'));
                    }

                    continue;
                }

                $rows[] = [
                    'row' => $lineNumber,
                    'values' => $this->normalizeRowValues($normalizedColumns, $headerMap),
                ];
            }

            if ($headerMap === null) {
                throw new RuntimeException(__('import_csv_missing_headers'));
            }

            return $rows;
        } finally {
            @unlink($tempPath);
            @unlink($storedPath);
        }
    }

    /**
     * @param list<array{row: int, values: array<string, string>}> $rows
     *
     * @return array{
     *     imported: int,
     *     updated: int,
     *     assigned: int,
     *     failed: int,
     *     errors: list<array{row: int, message: string}>,
     *     warnings: list<array{row: int, message: string}>,
     *     created_assets: list<int>,
     *     updated_assets: list<int>,
     *     created_categories: list<string>,
     *     created_locations: list<string>
     * }
     */
    private function processRows(array $rows): array
    {
        $imported = 0;
        $updated = 0;
        $assigned = 0;
        $failed = 0;
        $errors = [];
        $warnings = [];
        $createdAssets = [];
        $updatedAssets = [];
        $createdCategories = [];
        $createdLocations = [];
        $seenSerials = [];

        /** @var array<string, int> $categoryCache */
        $categoryCache = [];

        /** @var array<string, int> $locationCache */
        $locationCache = [];

        foreach ($rows as $entry) {
            $rowNumber = $entry['row'];
            $values = $entry['values'];

            $name = trim($values['name'] ?? '');

            if ($name === '') {
                $failed++;
                $errors[] = ['row' => $rowNumber, 'message' => __('import_error_name_required')];
                continue;
            }

            $categoryName = trim($values['category'] ?? '');

            if ($categoryName === '') {
                $failed++;
                $errors[] = ['row' => $rowNumber, 'message' => __('import_error_category_required')];
                continue;
            }

            $serialNumber = trim($values['serial_number'] ?? '');
            $serialKey = $serialNumber !== '' ? mb_strtolower($serialNumber, 'UTF-8') : '';

            if ($serialKey !== '' && isset($seenSerials[$serialKey])) {
                $failed++;
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => sprintf(__('import_error_duplicate_serial_in_file'), $serialNumber),
                ];
                continue;
            }

            $existingAsset = $serialKey !== '' ? $this->assetModel->findBySerialNumber($serialNumber) : null;
            $assetTag = trim($values['asset_tag'] ?? '');

            if ($existingAsset === null && $assetTag !== '') {
                if ($this->assetModel->assetTagExists($assetTag)) {
                    $failed++;
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => sprintf(__('import_error_duplicate_tag'), $assetTag),
                    ];
                    continue;
                }
            } elseif ($existingAsset !== null && $assetTag !== '') {
                if ($this->assetModel->assetTagExists($assetTag, (int) $existingAsset['id'])) {
                    $failed++;
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => sprintf(__('import_error_duplicate_tag'), $assetTag),
                    ];
                    continue;
                }
            }

            if ($existingAsset === null && $assetTag === '') {
                $assetTag = $this->assetModel->generateNextAssetTag();
            }

            $status = $this->normalizeStatus($values['status'] ?? '');

            if ($status === null) {
                $failed++;
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => sprintf(
                        __('import_error_invalid_status'),
                        trim($values['status'] ?? '')
                    ),
                ];
                continue;
            }

            try {
                $categoryId = $this->resolveCategoryId($categoryName, $categoryCache, $createdCategories);
                $locationId = $this->resolveLocationId(
                    trim($values['location'] ?? ''),
                    trim($values['building'] ?? ''),
                    $locationCache,
                    $createdLocations
                );
            } catch (\Throwable $exception) {
                $failed++;
                $errors[] = ['row' => $rowNumber, 'message' => $exception->getMessage()];
                continue;
            }

            $personnelRaw = trim($values['personnel'] ?? '');
            $personnelId = $this->resolvePersonnelId($personnelRaw);

            if ($personnelRaw !== '' && $personnelId === null) {
                $warnings[] = [
                    'row' => $rowNumber,
                    'message' => sprintf(__('inventory_import_personnel_not_found'), $personnelRaw),
                ];
            }

            if ($personnelId !== null) {
                $status = 'deployed';
            }

            $coreFields = [
                'name' => $name,
                'category_id' => $categoryId,
                'status' => $status,
                'location_id' => $locationId,
            ];

            if ($serialNumber !== '') {
                $coreFields['serial_number'] = $serialNumber;
            }

            if ($personnelId !== null) {
                $coreFields['personnel_id'] = $personnelId;
            }

            try {
                if ($existingAsset !== null) {
                    if ($assetTag !== '') {
                        $coreFields['asset_tag'] = $assetTag;
                    }

                    $asset = $this->assetModel->update((int) $existingAsset['id'], $coreFields);

                    if ($asset === null) {
                        throw new RuntimeException(__('inventory_import_update_failed'));
                    }

                    $updatedAssets[] = (int) $asset['id'];
                    $updated++;
                } else {
                    $coreFields['asset_tag'] = $assetTag;
                    $asset = $this->assetModel->create($coreFields, []);
                    $createdAssets[] = (int) $asset['id'];
                    $imported++;
                }
            } catch (\Throwable $exception) {
                $failed++;
                $errors[] = ['row' => $rowNumber, 'message' => $exception->getMessage()];
                continue;
            }

            if ($personnelId !== null) {
                $assigned++;
            }

            if ($serialKey !== '') {
                $seenSerials[$serialKey] = true;
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'assigned' => $assigned,
            'failed' => $failed,
            'errors' => $errors,
            'warnings' => $warnings,
            'created_assets' => $createdAssets,
            'updated_assets' => $updatedAssets,
            'created_categories' => $createdCategories,
            'created_locations' => $createdLocations,
        ];
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
     * @param list<string> $headers
     *
     * @return array<int, string>
     */
    private function mapHeaders(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);

            if ($normalized === '' || !isset(self::HEADER_ALIASES[$normalized])) {
                continue;
            }

            $map[$index] = self::HEADER_ALIASES[$normalized];
        }

        return $map;
    }

    /**
     * @param list<string|null> $columns
     * @param array<int, string> $headerMap
     *
     * @return array<string, string>
     */
    private function normalizeRowValues(array $columns, array $headerMap): array
    {
        $values = [];

        foreach ($headerMap as $index => $field) {
            $values[$field] = trim((string) ($columns[$index] ?? ''));
        }

        return $values;
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

    private function normalizeHeader(string $header): string
    {
        $normalized = mb_strtolower(trim($header), 'UTF-8');
        $normalized = strtr($normalized, [
            'ı' => 'i',
            'ş' => 's',
            'ğ' => 'g',
            'ü' => 'u',
            'ö' => 'o',
            'ç' => 'c',
        ]);
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return $normalized;
    }

    private function normalizeStatus(string $status): ?string
    {
        $trimmed = trim($status);

        if ($trimmed === '') {
            return 'ready';
        }

        $key = mb_strtolower($trimmed, 'UTF-8');

        if (isset(self::STATUS_ALIASES[$key])) {
            return self::STATUS_ALIASES[$key];
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

    /**
     * @return array{
     *     imported: int,
     *     updated: int,
     *     assigned: int,
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
