<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;

class AssetCsvImportService
{
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
        'envanter_adi' => 'name',
        'asset_name' => 'name',
        'serial_number' => 'serial_number',
        'serial' => 'serial_number',
        'seri_numarasi' => 'serial_number',
        'seri_no' => 'serial_number',
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
    ];

    public function __construct(
        private readonly Asset $assetModel,
        private readonly Category $categoryModel,
        private readonly Location $locationModel
    ) {
    }

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
        ];
    }

    /**
     * @param array<string, mixed> $asset
     *
     * @return list<string>
     */
    public static function mapAssetToExportRow(array $asset): array
    {
        $properties = is_array($asset['properties'] ?? null) ? $asset['properties'] : [];

        $assignee = trim((string) ($asset['personnel_email'] ?? ''));

        if ($assignee === '') {
            $assignee = trim((string) ($asset['personnel_name'] ?? ''));
        }

        return [
            (string) ($asset['asset_tag'] ?? ''),
            (string) ($asset['name'] ?? ''),
            (string) ($properties['model'] ?? ''),
            (string) ($properties['brand'] ?? ''),
            (string) ($asset['serial_number'] ?? ''),
            (string) ($asset['category_name'] ?? ''),
            (string) ($asset['status'] ?? ''),
            (string) ($asset['location_name'] ?? ''),
            (string) ($asset['location_building'] ?? ''),
            $assignee,
        ];
    }

    public static function templateCsvContent(): string
    {
        $headers = self::exportHeaders();
        $example = [
            'ENV-GLPI-001',
            'BT Departman Laptop',
            'Latitude 5540',
            'Dell',
            'SN-GLPI-001',
            'Bilgisayar',
            'deployed',
            'IT Depo',
            'Merkez Kampüs',
            'ahmet.yilmaz@sirket.com',
        ];

        return self::buildCsvLine($headers) . self::buildCsvLine($example);
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
     * @return array{
     *     imported: int,
     *     failed: int,
     *     errors: list<array{row: int, message: string}>,
     *     created_assets: list<int>,
     *     created_categories: list<string>,
     *     created_locations: list<string>
     * }
     */
    public function importFromString(string $csvContent): array
    {
        $csvContent = $this->stripBom($csvContent);
        $lines = preg_split('/\R/', $csvContent) ?? [];

        if ($lines === []) {
            return $this->emptyResultWithError(0, __('import_csv_empty'));
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
                    return $this->emptyResultWithError($lineNumber, __('import_csv_invalid_headers'));
                }

                continue;
            }

            $rows[] = [
                'row' => $lineNumber,
                'values' => $this->normalizeRowValues($columns, $headerMap),
            ];
        }

        if ($headerMap === null) {
            return $this->emptyResultWithError(0, __('import_csv_missing_headers'));
        }

        if ($rows === []) {
            return $this->emptyResultWithError(0, __('import_csv_no_data_rows'));
        }

        return $this->processRows($rows);
    }

    /**
     * @param list<array{row: int, values: array<string, string>}> $rows
     *
     * @return array{
     *     imported: int,
     *     failed: int,
     *     errors: list<array{row: int, message: string}>,
     *     created_assets: list<int>,
     *     created_categories: list<string>,
     *     created_locations: list<string>
     * }
     */
    private function processRows(array $rows): array
    {
        $imported = 0;
        $failed = 0;
        $errors = [];
        $createdAssets = [];
        $createdCategories = [];
        $createdLocations = [];
        $seenSerials = [];
        $seenTags = [];

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

            if ($serialKey !== '') {
                if (isset($seenSerials[$serialKey])) {
                    $failed++;
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => sprintf(__('import_error_duplicate_serial_in_file'), $serialNumber),
                    ];
                    continue;
                }

                if ($this->assetModel->serialNumberExists($serialNumber)) {
                    $failed++;
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => sprintf(__('import_error_duplicate_serial'), $serialNumber),
                    ];
                    continue;
                }
            }

            $assetTag = trim($values['asset_tag'] ?? '');

            if ($assetTag !== '') {
                $tagKey = mb_strtolower($assetTag, 'UTF-8');

                if (isset($seenTags[$tagKey])) {
                    $failed++;
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => sprintf(__('import_error_duplicate_tag_in_file'), $assetTag),
                    ];
                    continue;
                }

                if ($this->assetModel->assetTagExists($assetTag)) {
                    $failed++;
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => sprintf(__('import_error_duplicate_tag'), $assetTag),
                    ];
                    continue;
                }
            } else {
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

            $coreFields = [
                'asset_tag' => $assetTag,
                'name' => $name,
                'category_id' => $categoryId,
                'status' => $status,
                'location_id' => $locationId,
            ];

            if ($serialNumber !== '') {
                $coreFields['serial_number'] = $serialNumber;
            }

            try {
                $asset = $this->assetModel->create($coreFields, []);
            } catch (\Throwable $exception) {
                $failed++;
                $errors[] = ['row' => $rowNumber, 'message' => $exception->getMessage()];
                continue;
            }

            if ($serialKey !== '') {
                $seenSerials[$serialKey] = true;
            }

            $seenTags[mb_strtolower($assetTag, 'UTF-8')] = true;
            $createdAssets[] = (int) $asset['id'];
            $imported++;
        }

        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors,
            'created_assets' => $createdAssets,
            'created_categories' => $createdCategories,
            'created_locations' => $createdLocations,
        ];
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

    private function normalizeHeader(string $header): string
    {
        $normalized = mb_strtolower(trim($header), 'UTF-8');
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
     *     failed: int,
     *     errors: list<array{row: int, message: string}>,
     *     created_assets: list<int>,
     *     created_categories: list<string>,
     *     created_locations: list<string>
     * }
     */
    private function emptyResultWithError(int $row, string $message): array
    {
        return [
            'imported' => 0,
            'failed' => $row > 0 ? 1 : 0,
            'errors' => $row > 0
                ? [['row' => $row, 'message' => $message]]
                : [['row' => 0, 'message' => $message]],
            'created_assets' => [],
            'created_categories' => [],
            'created_locations' => [],
        ];
    }
}
