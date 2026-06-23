<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\Personnel;
use App\Models\Setting;
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
        'son_guncelleme' => 'son_guncelleme',
        'uygulama_ekleri_bilgisayar_ek_alanlar_mac_adresi_1' => 'mac_adresi_1',
        'uygulama_ekleri_bilgisayar_ek_alanlar_mac_adresi_2' => 'mac_adresi_2',
        'uygulama_ekleri_bilgisayar_ek_alanlar_eski_kullanici' => 'eski_kullanici',
    ];

    private const CUSTOM_FIELD_VALUE_PREFIX = 'custom_';

    /**
     * Case-insensitive smart aliases (space-normalized) for GLPI and legacy exports.
     *
     * @var array<string, list<string>>
     */
    private const SMART_FIELD_ALIASES = [
        'serial_number' => ['seri numarası', 'seri numarasi', 'seri no', 'serial_number', 'serial', 'sn'],
        'asset_tag' => ['stok numarası', 'stok numarasi', 'stok no', 'demirbaş no', 'demirbas no', 'asset_tag', 'stok_numarasi', 'demirbas_no', 'inventory_number'],
        'custom_grup' => ['grup', 'group', 'birim grup'],
        'device_name' => ['ad', 'name', 'cihaz adi', 'cihaz adı', 'device name', 'asset name'],
        'brand' => ['üretici', 'uretici', 'marka', 'manufacturer', 'brand'],
        'device_model' => ['model', 'device model'],
        'category' => ['tür', 'tur', 'type', 'category', 'kategori', 'itemtype'],
        'status' => ['durum', 'status', 'state'],
        'location' => ['lokasyon', 'location', 'oda', 'room', 'konum'],
        'building' => ['bina', 'building', 'campus'],
        'personnel' => ['zimmetli kişi', 'zimmetli kisi', 'personel', 'assignee', 'assigned to', 'kullanici', 'kullanıcı'],
        'custom_birim' => ['birim'],
        'custom_son_guncelleme' => ['son güncelleme', 'son guncelleme'],
        'custom_mac_adresi_1' => ['mac adresi 1', 'mac address 1'],
        'custom_mac_adresi_2' => ['mac adresi 2', 'mac address 2'],
        'custom_eski_kullanici' => ['eski kullanıcı', 'eski kullanici', 'former user'],
    ];

    /** @var list<string> */
    public const MAPPING_FIELD_KEYS = [
        '',
        'device_name',
        'serial_number',
        'asset_tag',
        'brand',
        'device_model',
        'category',
        'status',
        'location',
        'building',
        'personnel',
        'custom_birim',
        'custom_grup',
        'custom_konum',
        'custom_son_guncelleme',
        'custom_mac_adresi_1',
        'custom_mac_adresi_2',
        'custom_eski_kullanici',
    ];

    /** @var array<string, array{key: string, label: string, needles: list<string>}>|null */
    private ?array $mappingFieldRegistryCache = null;

    public function __construct(
        private readonly Asset $assetModel,
        private readonly Category $categoryModel,
        private readonly Location $locationModel,
        private readonly Personnel $personnelModel,
        private readonly Setting $settingModel,
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
    public function importFromUploadedFile(string $contents, string $originalFilename, ?array $columnMapping = null): array
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
                'csv' => $this->importCsvContents($contents, $columnMapping),
                'xlsx', 'xls' => $this->importSpreadsheetContents($contents, $extension, $columnMapping),
                default => $this->emptyResultWithError(0, __('inventory_import_invalid_file_type')),
            };
        } catch (RuntimeException $exception) {
            return $this->emptyResultWithError(0, $exception->getMessage());
        }
    }

    /**
     * @return array{
     *     headers: list<string>,
     *     columns: list<array{index: int, header: string, mapped_field: string|null, is_mapped: bool}>,
     *     available_fields: list<string>,
     *     field_options: list<array{value: string, label: string, needles: list<string>}>
     * }
     */
    public function buildImportMappingPreview(string $contents, string $originalFilename): array
    {
        $headers = $this->extractHeaderRow($contents, $originalFilename);

        if ($headers === []) {
            throw new RuntimeException(__('import_csv_missing_headers'));
        }

        $registry = $this->getMappingFieldRegistry();
        $columns = [];

        foreach ($headers as $index => $header) {
            $mappedField = $this->resolveImportField((string) $header);

            $columns[] = [
                'index' => $index,
                'header' => (string) $header,
                'mapped_field' => $mappedField,
                'is_mapped' => $mappedField !== null && $mappedField !== '',
            ];
        }

        $fieldOptions = [];

        foreach ($registry as $entry) {
            if ($entry['key'] === '') {
                continue;
            }

            $fieldOptions[] = [
                'value' => $entry['key'],
                'label' => $entry['label'],
                'needles' => $entry['needles'],
            ];
        }

        return [
            'headers' => $headers,
            'columns' => $columns,
            'available_fields' => array_keys($registry),
            'field_options' => $fieldOptions,
        ];
    }

    /**
     * @return list<string>
     */
    public function extractHeaderRow(string $contents, string $originalFilename): array
    {
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->extractSpreadsheetHeaderRow($contents, $extension);
        }

        return $this->extractCsvHeaderRow($contents);
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
    private function importCsvContents(string $csvContent, ?array $columnMapping = null): array
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
                $headerMap = $columnMapping !== null
                    ? $this->normalizeColumnMapping($columnMapping, count($columns))
                    : $this->mapHeaders($columns);

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
    private function importSpreadsheetContents(string $contents, string $extension, ?array $columnMapping = null): array
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

            return $this->importWorksheetRows($worksheet, $columnMapping);
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
    private function importWorksheetRows(Worksheet $worksheet, ?array $columnMapping = null): array
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
                $headerMap = $columnMapping !== null
                    ? $this->normalizeColumnMapping($columnMapping, count($columns))
                    : $this->mapHeaders($columns);

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
     * seri_numarasi -> serial_number, stok_numarasi -> asset_tag, grup -> custom_fields.grup
     *
     * @param list<string> $headers
     *
     * @return array<int, string>
     */
    private function mapHeaders(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $field = $this->resolveImportField((string) $header);

            if ($field === null || $field === '') {
                continue;
            }

            $map[$index] = $field;
        }

        return $map;
    }

    private function resolveImportField(string $rawHeader): ?string
    {
        $registry = $this->getMappingFieldRegistry();
        $labelMatch = $this->matchImportFieldByVisibleLabel($rawHeader, $registry);

        if ($labelMatch !== null) {
            return $labelMatch;
        }

        $compare = $this->headerComparisonKey($rawHeader);
        $normalized = $this->normalizeHeader($rawHeader);

        if ($compare !== '') {
            $extensionField = $this->matchUygulamaEkleriField($compare);

            if ($extensionField !== null) {
                return $extensionField;
            }

            $priorityField = $this->matchPrioritySubstringRules($compare);

            if ($priorityField !== null) {
                return $priorityField;
            }
        }

        if ($normalized !== '' && isset(self::HEADER_ALIASES[$normalized])) {
            return self::HEADER_ALIASES[$normalized];
        }

        if ($normalized !== '' && isset(self::GLPI_CUSTOM_FIELD_ALIASES[$normalized])) {
            return self::CUSTOM_FIELD_VALUE_PREFIX . self::GLPI_CUSTOM_FIELD_ALIASES[$normalized];
        }

        if ($compare === '') {
            return null;
        }

        $registryField = $this->matchRegistrySubstrings($compare, $this->getMappingFieldRegistry());

        if ($registryField !== null) {
            return $registryField;
        }

        $bestField = null;
        $bestScore = 0;

        foreach (self::SMART_FIELD_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $aliasKey = $this->headerComparisonKey($alias);
                $aliasNormalized = $this->normalizeHeader($alias);

                if ($aliasKey === '' && $aliasNormalized === '') {
                    continue;
                }

                if ($compare === $aliasKey || ($normalized !== '' && $normalized === $aliasNormalized)) {
                    return $field;
                }

                if ($aliasKey !== '' && str_contains($compare, $aliasKey)) {
                    $score = strlen($aliasKey);

                    if ($score > $bestScore) {
                        $bestField = $field;
                        $bestScore = $score;
                    }
                }
            }
        }

        foreach (self::GLPI_CUSTOM_FIELD_ALIASES as $normalizedKey => $customKey) {
            $aliasKey = str_replace('_', ' ', $normalizedKey);

            if ($aliasKey !== '' && str_contains($compare, $aliasKey)) {
                $score = strlen($aliasKey);

                if ($score > $bestScore) {
                    $bestField = self::CUSTOM_FIELD_VALUE_PREFIX . $customKey;
                    $bestScore = $score;
                }
            }
        }

        return $bestField;
    }

    /**
     * Match CSV headers against visible dropdown labels (not internal keys/IDs).
     *
     * @param array<string, array{key: string, label: string, needles: list<string>}> $registry
     */
    private function matchImportFieldByVisibleLabel(string $rawHeader, array $registry): ?string
    {
        $headerKey = $this->compactLabelMatchKey($rawHeader);

        if ($headerKey === '') {
            return null;
        }

        $bestField = null;
        $bestScore = 0;

        foreach ($registry as $entry) {
            $fieldKey = $entry['key'];

            if ($fieldKey === '') {
                continue;
            }

            $labelCandidates = array_merge(
                [$entry['label']],
                $entry['needles'] ?? []
            );

            foreach ($labelCandidates as $labelCandidate) {
                $labelKey = $this->compactLabelMatchKey((string) $labelCandidate);

                if ($labelKey === '' || strlen($labelKey) < 2) {
                    continue;
                }

                if (str_contains($headerKey, $labelKey) && strlen($labelKey) > $bestScore) {
                    $bestField = $fieldKey;
                    $bestScore = strlen($labelKey);

                    continue;
                }

                if (strlen($headerKey) >= 2 && str_contains($labelKey, $headerKey) && strlen($headerKey) > $bestScore) {
                    $bestField = $fieldKey;
                    $bestScore = strlen($headerKey);
                }
            }
        }

        return $bestField;
    }

    private function compactLabelMatchKey(string $text): string
    {
        $key = $this->headerComparisonKey($text);
        $compact = preg_replace('/[^a-z0-9]/u', '', $key) ?? '';

        return $compact;
    }

    /**
     * @return array<string, array{key: string, label: string, needles: list<string>}>
     */
    public function buildMappingFieldRegistry(): array
    {
        $registry = [
            '' => [
                'key' => '',
                'label' => __('import_map_select'),
                'needles' => [],
            ],
        ];

        foreach (self::MAPPING_FIELD_KEYS as $fieldKey) {
            if ($fieldKey === '') {
                continue;
            }

            $needles = [];

            foreach (self::SMART_FIELD_ALIASES[$fieldKey] ?? [] as $alias) {
                $needle = $this->headerComparisonKey($alias);

                if ($needle !== '') {
                    $needles[] = $needle;
                }
            }

            if (str_starts_with($fieldKey, self::CUSTOM_FIELD_VALUE_PREFIX)) {
                $slug = substr($fieldKey, strlen(self::CUSTOM_FIELD_VALUE_PREFIX));
                $slugNeedle = $this->headerComparisonKey(str_replace('_', ' ', $slug));

                if ($slugNeedle !== '') {
                    $needles[] = $slugNeedle;
                }
            }

            $registry[$fieldKey] = [
                'key' => $fieldKey,
                'label' => $this->mappingLabelForKey($fieldKey),
                'needles' => array_values(array_unique($needles)),
            ];
        }

        foreach ($this->collectRegisteredCustomFieldDefinitions() as $definition) {
            $fieldKey = self::CUSTOM_FIELD_VALUE_PREFIX . $definition['name'];

            if (isset($registry[$fieldKey])) {
                $registry[$fieldKey]['needles'] = array_values(array_unique(array_merge(
                    $registry[$fieldKey]['needles'],
                    $this->buildNeedlesForCustomFieldDefinition($definition)
                )));

                continue;
            }

            $registry[$fieldKey] = [
                'key' => $fieldKey,
                'label' => $definition['label'],
                'needles' => $this->buildNeedlesForCustomFieldDefinition($definition),
            ];
        }

        return $registry;
    }

    /**
     * @return list<array{name: string, label: string}>
     */
    private function collectRegisteredCustomFieldDefinitions(): array
    {
        $definitions = [];
        $seen = [];
        $bundle = $this->settingModel->getAdminBundle();
        $globalFields = is_array($bundle['custom_fields'] ?? null) ? $bundle['custom_fields'] : [];

        foreach ($globalFields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));

            if ($name === '' || isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $definitions[] = [
                'name' => $name,
                'label' => trim((string) ($field['label'] ?? $name)),
            ];
        }

        foreach ($this->categoryModel->findAll() as $category) {
            $fields = is_array($category['fields'] ?? null) ? $category['fields'] : [];

            foreach ($fields as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $name = trim((string) ($field['name'] ?? ''));

                if ($name === '' || isset($seen[$name])) {
                    continue;
                }

                $seen[$name] = true;
                $definitions[] = [
                    'name' => $name,
                    'label' => trim((string) ($field['label'] ?? $name)),
                ];
            }
        }

        return $definitions;
    }

    /**
     * @param array{name: string, label: string} $definition
     *
     * @return list<string>
     */
    private function buildNeedlesForCustomFieldDefinition(array $definition): array
    {
        $needles = [];

        foreach ([$definition['name'], $definition['label'], str_replace('_', ' ', $definition['name'])] as $candidate) {
            $needle = $this->headerComparisonKey($candidate);

            if ($needle !== '') {
                $needles[] = $needle;
            }
        }

        return array_values(array_unique($needles));
    }

    /**
     * @return array<string, array{key: string, label: string, needles: list<string>}>
     */
    private function getMappingFieldRegistry(): array
    {
        if ($this->mappingFieldRegistryCache === null) {
            $this->mappingFieldRegistryCache = $this->buildMappingFieldRegistry();
        }

        return $this->mappingFieldRegistryCache;
    }

    private function mappingLabelForKey(string $fieldKey): string
    {
        $translationKey = 'import_map_' . $fieldKey;
        $translated = __($translationKey);

        return $translated !== $translationKey ? $translated : $fieldKey;
    }

    private function matchUygulamaEkleriField(string $compare): ?string
    {
        if (!str_contains($compare, 'uygulama ekleri')) {
            return null;
        }

        $suffixRules = [
            ['needles' => ['mac adresi 2', 'mac address 2'], 'field' => 'custom_mac_adresi_2'],
            ['needles' => ['mac adresi 1', 'mac address 1'], 'field' => 'custom_mac_adresi_1'],
            ['needles' => ['eski kullanici', 'eski kullanıcı', 'former user'], 'field' => 'custom_eski_kullanici'],
        ];

        $bestField = null;
        $bestScore = 0;

        foreach ($suffixRules as $rule) {
            foreach ($rule['needles'] as $needle) {
                $needleKey = $this->headerComparisonKey($needle);

                if ($needleKey === '' || !str_contains($compare, $needleKey)) {
                    continue;
                }

                $score = strlen($needleKey);

                if ($score > $bestScore) {
                    $bestField = $rule['field'];
                    $bestScore = $score;
                }
            }
        }

        return $bestField;
    }

    private function matchPrioritySubstringRules(string $compare): ?string
    {
        $rules = [
            ['needles' => ['birim grup'], 'field' => 'custom_grup'],
            ['needles' => ['son guncelleme', 'son güncelleme'], 'field' => 'custom_son_guncelleme'],
            ['needles' => ['mac adresi 2', 'mac address 2'], 'field' => 'custom_mac_adresi_2'],
            ['needles' => ['mac adresi 1', 'mac address 1'], 'field' => 'custom_mac_adresi_1'],
            ['needles' => ['eski kullanici', 'eski kullanıcı', 'former user'], 'field' => 'custom_eski_kullanici'],
            ['needles' => ['grup', 'group'], 'field' => 'custom_grup'],
            ['needles' => ['birim'], 'field' => 'custom_birim'],
            ['needles' => ['konum'], 'field' => 'location'],
        ];

        $bestField = null;
        $bestScore = 0;

        foreach ($rules as $rule) {
            foreach ($rule['needles'] as $needle) {
                $needleKey = $this->headerComparisonKey($needle);

                if ($needleKey === '') {
                    continue;
                }

                if ($compare !== $needleKey && !str_contains($compare, $needleKey)) {
                    continue;
                }

                $score = strlen($needleKey);

                if ($score > $bestScore) {
                    $bestField = $rule['field'];
                    $bestScore = $score;
                }
            }
        }

        return $bestField;
    }

    /**
     * @param array<string, array{key: string, label: string, needles: list<string>}> $registry
     */
    private function matchRegistrySubstrings(string $compare, array $registry): ?string
    {
        $bestField = null;
        $bestScore = 0;

        foreach ($registry as $entry) {
            $fieldKey = $entry['key'];

            if ($fieldKey === '') {
                continue;
            }

            foreach ($entry['needles'] as $needle) {
                if ($needle === '' || strlen($needle) < 2) {
                    continue;
                }

                if ($compare === $needle || str_contains($compare, $needle)) {
                    $score = strlen($needle);

                    if ($score > $bestScore) {
                        $bestField = $fieldKey;
                        $bestScore = $score;
                    }

                    continue;
                }

                if (strlen($compare) >= 2 && str_contains($needle, $compare)) {
                    $score = strlen($compare);

                    if ($score > $bestScore) {
                        $bestField = $fieldKey;
                        $bestScore = $score;
                    }
                }
            }
        }

        return $bestField;
    }

    /**
     * @param array<int|string, mixed> $columnMapping
     *
     * @return array<int, string>
     */
    private function normalizeColumnMapping(array $columnMapping, int $columnCount): array
    {
        $map = [];

        foreach ($columnMapping as $index => $field) {
            $columnIndex = is_numeric($index) ? (int) $index : null;

            if ($columnIndex === null || $columnIndex < 0 || $columnIndex >= $columnCount) {
                continue;
            }

            $fieldKey = trim((string) $field);

            if ($fieldKey === '') {
                continue;
            }

            if (!array_key_exists($fieldKey, $this->getMappingFieldRegistry())) {
                continue;
            }

            $map[$columnIndex] = $fieldKey;
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function extractCsvHeaderRow(string $csvContent): array
    {
        $csvContent = $this->stripBom($csvContent);
        $lines = preg_split('/\R/', $csvContent) ?? [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $columns = str_getcsv($line, ',', '"', '\\');

            if ($columns === false || $columns === [null]) {
                return [];
            }

            return array_map(
                fn (mixed $column): string => $this->sanitizeHeaderString((string) $column),
                $columns
            );
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function extractSpreadsheetHeaderRow(string $contents, string $extension): array
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'betech_inventory_import_preview_');

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

            foreach ($worksheet->getRowIterator() as $row) {
                $columns = [];

                foreach ($row->getCellIterator() as $cell) {
                    $columns[] = trim((string) $cell->getValue());
                }

                if ($this->isEmptyRow($columns)) {
                    continue;
                }

                return array_map(
                    fn (string $column): string => $this->sanitizeHeaderString($column),
                    $columns
                );
            }
        } finally {
            @unlink($tempPath);
            @unlink($storedPath);
        }

        return [];
    }

    private function headerComparisonKey(string $header): string
    {
        $key = mb_strtolower($this->sanitizeHeaderString($header), 'UTF-8');
        $key = strtr($key, [
            'ı' => 'i',
            'ş' => 's',
            'ğ' => 'g',
            'ü' => 'u',
            'ö' => 'o',
            'ç' => 'c',
            'İ' => 'i',
        ]);
        $key = preg_replace('/[\s\-–—]+/u', ' ', $key) ?? $key;
        $key = preg_replace('/\s+/u', ' ', $key) ?? $key;

        return trim($key);
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
            $values[$field] = $this->sanitizeCellValue((string) ($columns[$index] ?? ''));
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
        $normalized = mb_strtolower($this->sanitizeHeaderString($header), 'UTF-8');
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

    /**
     * Strip GLPI export noise (CR/LF, NBSP, control chars) before header matching.
     */
    private function sanitizeHeaderString(string $header): string
    {
        $header = $this->stripBom($header);
        $cleaned = trim($header);
        $cleaned = preg_replace('/[\x{FEFF}\x{00A0}\x{200B}-\x{200D}\x{2060}]/u', ' ', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/[\x00-\x1F\x7F-\x9F\s]+/u', ' ', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned;

        return trim($cleaned);
    }

    private function sanitizeCellValue(string $value): string
    {
        $cleaned = preg_replace('/[\x{FEFF}\x{00A0}\x{200B}-\x{200D}\x{2060}]/u', ' ', $value) ?? $value;
        $cleaned = preg_replace('/[\x00-\x1F\x7F-\x9F\s]+/u', ' ', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned;

        return trim($cleaned);
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
