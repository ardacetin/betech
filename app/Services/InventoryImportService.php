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

    /**
     * @var array<string, string>
     */
    private const HEADER_ALIASES = [
        'name' => 'device_name',
        'ad' => 'device_name',
        'cihaz_adi' => 'device_name',
        'device_name' => 'device_name',
        'envanter_adi' => 'device_name',
        'asset_name' => 'device_name',
        'model' => 'device_model',
        'device_model' => 'device_model',
        'serial_number' => 'serial_number',
        'serial' => 'serial_number',
        'seri_numarasi' => 'serial_number',
        'seri_no' => 'serial_number',
        'seri_numara' => 'serial_number',
        'category' => 'category',
        'kategori' => 'category',
        'category_name' => 'category',
        'type' => 'category',
        'tur' => 'category',
        'tip' => 'category',
        'itemtype' => 'category',
        'status' => 'status',
        'durum' => 'status',
        'state' => 'status',
        'location' => 'location',
        'lokasyon' => 'location',
        'location_name' => 'location',
        'oda' => 'location',
        'room' => 'location',
        'building' => 'building',
        'bina' => 'building',
        'campus' => 'building',
        'asset_tag' => 'asset_tag',
        'envanter_etiketi' => 'asset_tag',
        'demirbas_no' => 'asset_tag',
        'demirbas_numarasi' => 'asset_tag',
        'inventory_number' => 'asset_tag',
        'tag' => 'asset_tag',
        'stok_numarasi' => 'asset_tag',
        'stok_no' => 'asset_tag',
        'personnel' => 'personnel',
        'personel' => 'personnel',
        'kullanici' => 'personnel',
        'kullanıcı' => 'personnel',
        'kullanici_adi' => 'personnel',
        'zimmetli_kisi' => 'personnel',
        'zimmetli_kişi' => 'personnel',
        'zimmetli' => 'personnel',
        'assignee' => 'personnel',
        'assigned_to' => 'personnel',
        'user' => 'personnel',
        'user_name' => 'personnel',
        'email' => 'personnel',
        'brand' => 'brand',
        'marka' => 'brand',
        'manufacturer' => 'brand',
        'uretici' => 'brand',
        'üretici' => 'brand',
    ];

    /**
     * GLPI official export columns stored under properties.custom_fields.
     *
     * @var array<string, string>
     */
    private const GLPI_CUSTOM_FIELD_ALIASES = [
        'birim' => 'birim',
        'grup' => 'grup',
        'konum' => 'konum',
        'son_guncelleme' => 'son_guncelleme',
        'uygulama_ekleri_bilgisayar_ek_alanlar_mac_adresi_1' => 'mac_adresi_1',
        'uygulama_ekleri_bilgisayar_ek_alanlar_mac_adresi_2' => 'mac_adresi_2',
        'uygulama_ekleri_bilgisayar_ek_alanlar_eski_kullanici' => 'eski_kullanici',
    ];

    private const CUSTOM_FIELD_VALUE_PREFIX = 'custom_';

    public function __construct(
        private readonly Asset $assetModel,
        private readonly Category $categoryModel,
        private readonly Location $locationModel,
        private readonly Personnel $personnelModel
    ) {
    }

    public static function templateCsvContent(): string
    {
        $headers = [
            'Ad',
            'Birim',
            'Son güncelleme',
            'Grup',
            'Konum',
            'Üretici',
            'Model',
            'Tür',
            'Seri numarası',
            'Stok numarası',
            'Uygulama ekleri - Bilgisayar Ek Alanlar - Mac Adresi 1',
            'Uygulama ekleri - Bilgisayar Ek Alanlar - Mac Adresi 2',
            'Uygulama ekleri - Bilgisayar Ek Alanlar - Eski Kullanıcı',
        ];
        $example = [
            'BT Departman Laptop',
            'BT Birimi',
            '2026-03-15 10:30:00',
            'IT Envanter',
            'Merkez Kampüs / IT Depo',
            'Dell',
            'Latitude 5540',
            'Bilgisayar',
            'SN-GLPI-001',
            'ENV-GLPI-001',
            '00:11:22:33:44:55',
            '00:11:22:33:44:56',
            'ahmet.yilmaz@sirket.com',
        ];

        return self::buildCsvLine($headers) . self::buildCsvLine($example);
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

            $this->processRow(
                $lineNumber,
                $this->normalizeRowValues($columns, $headerMap),
                $state
            );
        }

        if ($headerMap === null) {
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
        $headerMap = null;

        foreach ($worksheet->getRowIterator() as $row) {
            $lineNumber = $row->getRowIndex();
            $columns = [];

            foreach ($row->getCellIterator() as $cell) {
                $columns[] = trim((string) $cell->getValue());
            }

            if ($this->isEmptyRow($columns)) {
                continue;
            }

            if ($headerMap === null) {
                $headerMap = $this->mapHeaders($columns);

                if ($headerMap === []) {
                    throw new RuntimeException(__('import_csv_invalid_headers'));
                }

                continue;
            }

            $this->processRow(
                $lineNumber,
                $this->normalizeRowValues($columns, $headerMap),
                $state
            );
        }

        if ($headerMap === null) {
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
        $name = $deviceName !== '' ? $deviceName : $deviceModel;

        if ($serialNumber === '' && $assetTag === '' && $name === '') {
            $state['skipped']++;

            return;
        }

        try {
            $existingAsset = $this->resolveExistingAsset($serialNumber, $assetTag);
        } catch (RuntimeException $exception) {
            $state['failed']++;
            $state['errors'][] = ['row' => $rowNumber, 'message' => $exception->getMessage()];

            return;
        }

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
        $tagKey = $assetTag !== '' ? mb_strtolower($assetTag, 'UTF-8') : '';

        if ($serialKey !== '' && isset($state['seenSerials'][$serialKey])) {
            $state['failed']++;
            $state['errors'][] = [
                'row' => $rowNumber,
                'message' => sprintf(__('import_error_duplicate_serial_in_file'), $serialNumber),
            ];

            return;
        }

        if ($tagKey !== '' && isset($state['seenTags'][$tagKey])) {
            $state['failed']++;
            $state['errors'][] = [
                'row' => $rowNumber,
                'message' => sprintf(__('import_error_duplicate_tag'), $assetTag),
            ];

            return;
        }

        if ($existingAsset === null && $assetTag === '') {
            $assetTag = $this->assetModel->generateNextAssetTag();
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
        ];

        if ($serialNumber !== '') {
            $coreFields['serial_number'] = $serialNumber;
        }

        if ($personnelId !== null) {
            $coreFields['personnel_id'] = $personnelId;
        }

        if ($assetTag !== '') {
            $coreFields['asset_tag'] = $assetTag;
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

        if ($tagKey !== '') {
            $state['seenTags'][$tagKey] = true;
        }
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

        $customFields = is_array($properties['custom_fields'] ?? null)
            ? $properties['custom_fields']
            : [];

        foreach ($values as $field => $rawValue) {
            if (!is_string($field) || !str_starts_with($field, self::CUSTOM_FIELD_VALUE_PREFIX)) {
                continue;
            }

            $customKey = substr($field, strlen(self::CUSTOM_FIELD_VALUE_PREFIX));
            $customValue = trim($rawValue);

            if ($customKey === '' || $customValue === '') {
                continue;
            }

            $customFields[$customKey] = $customValue;
        }

        if ($customFields !== []) {
            ksort($customFields);
            $properties['custom_fields'] = $customFields;
        }

        return $properties;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveExistingAsset(string $serialNumber, string $assetTag): ?array
    {
        $bySerial = $serialNumber !== '' ? $this->assetModel->findBySerialNumber($serialNumber) : null;
        $byTag = $assetTag !== '' ? $this->assetModel->findByAssetTag($assetTag) : null;

        if ($bySerial !== null && $byTag !== null && (int) $bySerial['id'] !== (int) $byTag['id']) {
            throw new RuntimeException(__('inventory_import_identity_conflict'));
        }

        return $bySerial ?? $byTag;
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

            if ($normalized === '') {
                continue;
            }

            if (isset(self::HEADER_ALIASES[$normalized])) {
                $map[$index] = self::HEADER_ALIASES[$normalized];

                continue;
            }

            if (isset(self::GLPI_CUSTOM_FIELD_ALIASES[$normalized])) {
                $map[$index] = self::CUSTOM_FIELD_VALUE_PREFIX . self::GLPI_CUSTOM_FIELD_ALIASES[$normalized];
            }
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
            'İ' => 'i',
        ]);
        $normalized = preg_replace('/[\s\-–—]+/u', '_', $normalized) ?? $normalized;
        $normalized = preg_replace('/_+/', '_', $normalized) ?? $normalized;

        return trim($normalized, '_');
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
