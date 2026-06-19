<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\IpAddress;
use App\Models\IpNetwork;

class IpamCsvImportService
{
    /**
     * @var array<string, string>
     */
    private const NETWORK_HEADER_ALIASES = [
        'name' => 'name',
        'network_name' => 'name',
        'ag_adi' => 'name',
        'network_address' => 'network_address',
        'network' => 'network_address',
        'ag_adresi' => 'network_address',
        'cidr' => 'cidr',
        'subnet_mask' => 'cidr',
        'gateway' => 'gateway',
        'ag_gecidi' => 'gateway',
        'vlan_id' => 'vlan_id',
        'vlan' => 'vlan_id',
        'description' => 'description',
        'aciklama' => 'description',
        'auto_generate' => 'auto_generate',
        'otomatik_olustur' => 'auto_generate',
    ];

    /**
     * @var array<string, string>
     */
    private const ADDRESS_HEADER_ALIASES = [
        'network_name' => 'network_name',
        'ag_adi' => 'network_name',
        'network_cidr' => 'network_cidr',
        'ag_cidr' => 'network_cidr',
        'ip_address' => 'ip_address',
        'ip' => 'ip_address',
        'ip_adresi' => 'ip_address',
        'status' => 'status',
        'durum' => 'status',
        'asset_tag' => 'asset_tag',
        'envanter_etiketi' => 'asset_tag',
        'hostname' => 'hostname',
        'host_adi' => 'hostname',
        'mac_address' => 'mac_address',
        'mac' => 'mac_address',
        'mac_adresi' => 'mac_address',
        'notes' => 'notes',
        'notlar' => 'notes',
    ];

    /**
     * @var array<string, string>
     */
    private const STATUS_ALIASES = [
        'available' => IpAddress::STATUS_AVAILABLE,
        'musait' => IpAddress::STATUS_AVAILABLE,
        'boş' => IpAddress::STATUS_AVAILABLE,
        'bos' => IpAddress::STATUS_AVAILABLE,
        'reserved' => IpAddress::STATUS_RESERVED,
        'rezerve' => IpAddress::STATUS_RESERVED,
        'assigned' => IpAddress::STATUS_ASSIGNED,
        'atanmis' => IpAddress::STATUS_ASSIGNED,
        'atanmış' => IpAddress::STATUS_ASSIGNED,
        'dhcp' => IpAddress::STATUS_DHCP,
    ];

    public function __construct(
        private readonly IpNetwork $ipNetworkModel,
        private readonly IpAddress $ipAddressModel,
        private readonly Asset $assetModel,
        private readonly IpAddressGenerator $ipAddressGenerator
    ) {
    }

    public static function networkTemplateCsvContent(): string
    {
        $headers = ['name', 'network_address', 'cidr', 'gateway', 'vlan_id', 'description', 'auto_generate'];
        $example = ['Server VLAN', '192.168.1.0', '24', '192.168.1.1', '10', 'Production servers', 'yes'];

        return self::buildCsvLine($headers) . self::buildCsvLine($example);
    }

    public static function addressTemplateCsvContent(): string
    {
        $headers = ['network_name', 'network_cidr', 'ip_address', 'status', 'asset_tag', 'hostname', 'mac_address', 'notes'];
        $example = ['Server VLAN', '192.168.1.0/24', '192.168.1.10', 'assigned', 'ENV-001', 'web-01', 'AA:BB:CC:DD:EE:FF', 'Primary web server'];

        return self::buildCsvLine($headers) . self::buildCsvLine($example);
    }

    /**
     * @param list<array<string, mixed>> $addresses
     */
    public function exportNetworkAddressesToCsv(array $addresses): string
    {
        $headers = [
            __('ipam_col_ip'),
            __('ipam_col_status'),
            __('ipam_col_asset_tag'),
            __('ipam_col_hostname'),
            __('ipam_col_mac'),
            __('ipam_notes'),
        ];

        $lines = [self::buildCsvLine($headers)];

        foreach ($addresses as $address) {
            $lines[] = self::buildCsvLine([
                (string) ($address['ip_address'] ?? ''),
                $this->statusLabelForExport((string) ($address['status'] ?? IpAddress::STATUS_AVAILABLE)),
                (string) ($address['asset_tag'] ?? ''),
                (string) ($address['hostname'] ?? ''),
                (string) ($address['mac_address'] ?? ''),
                (string) ($address['notes'] ?? ''),
            ]);
        }

        return implode('', $lines);
    }

    private function statusLabelForExport(string $status): string
    {
        return match (strtolower(trim($status))) {
            IpAddress::STATUS_AVAILABLE => __('ipam_status_available'),
            IpAddress::STATUS_RESERVED => __('ipam_status_reserved'),
            IpAddress::STATUS_ASSIGNED => __('ipam_status_assigned'),
            IpAddress::STATUS_DHCP => __('ipam_status_dhcp'),
            default => $status,
        };
    }

    /**
     * @return array{
     *     imported: int,
     *     failed: int,
     *     errors: list<array{row: int, message: string}>,
     *     created_networks: list<string>
     * }
     */
    public function importNetworksFromString(string $csvContent): array
    {
        $rows = $this->parseCsv($csvContent, self::NETWORK_HEADER_ALIASES);

        if (isset($rows['error'])) {
            return $this->emptyNetworkResultWithError($rows['row'], $rows['error']);
        }

        $imported = 0;
        $failed = 0;
        $errors = [];
        $createdNetworks = [];

        foreach ($rows as $entry) {
            $rowNumber = $entry['row'];
            $values = $entry['values'];

            try {
                $name = trim($values['name'] ?? '');

                if ($name === '') {
                    throw new \InvalidArgumentException(__('ipam_network_name_required'));
                }

                $networkAddress = trim($values['network_address'] ?? '');
                $cidr = (int) trim($values['cidr'] ?? '0');
                $autoGenerate = $this->normalizeBoolean($values['auto_generate'] ?? 'yes');

                $network = $this->ipNetworkModel->create(
                    $name,
                    $networkAddress,
                    $cidr,
                    $values['gateway'] ?? null,
                    isset($values['vlan_id']) && trim($values['vlan_id']) !== '' ? (int) $values['vlan_id'] : null,
                    $values['description'] ?? null,
                    $autoGenerate
                );

                $imported++;
                $createdNetworks[] = (string) ($network['cidr_notation'] ?? $name);
            } catch (\Throwable $exception) {
                $failed++;
                $errors[] = ['row' => $rowNumber, 'message' => $exception->getMessage()];
            }
        }

        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors,
            'created_networks' => $createdNetworks,
        ];
    }

    /**
     * @return array{
     *     imported: int,
     *     failed: int,
     *     errors: list<array{row: int, message: string}>,
     *     updated_addresses: list<string>
     * }
     */
    public function importAddressesFromString(string $csvContent): array
    {
        $rows = $this->parseCsv($csvContent, self::ADDRESS_HEADER_ALIASES);

        if (isset($rows['error'])) {
            return $this->emptyAddressResultWithError($rows['row'], $rows['error']);
        }

        $imported = 0;
        $failed = 0;
        $errors = [];
        $updatedAddresses = [];

        foreach ($rows as $entry) {
            $rowNumber = $entry['row'];
            $values = $entry['values'];

            try {
                $network = $this->resolveNetworkFromRow($values);

                if ($network === null) {
                    throw new \InvalidArgumentException(__('ipam_import_network_not_found'));
                }

                $ipAddress = trim($values['ip_address'] ?? '');

                if ($ipAddress === '') {
                    throw new \InvalidArgumentException(__('ipam_ip_required'));
                }

                if (!$this->ipAddressGenerator->isIpInNetwork(
                    $ipAddress,
                    (string) $network['network_address'],
                    (int) $network['cidr']
                )) {
                    throw new \InvalidArgumentException(__('ipam_ip_outside_network'));
                }

                $status = $this->normalizeStatus($values['status'] ?? IpAddress::STATUS_AVAILABLE);

                if ($status === null) {
                    throw new \InvalidArgumentException(__('ipam_invalid_status'));
                }

                $assetId = null;
                $assetTag = trim($values['asset_tag'] ?? '');

                if ($assetTag !== '') {
                    $asset = $this->assetModel->findByAssetTag($assetTag);

                    if ($asset === null) {
                        throw new \InvalidArgumentException(sprintf(__('ipam_import_asset_not_found'), $assetTag));
                    }

                    $assetId = (int) $asset['id'];
                }

                $this->ipAddressModel->upsertForNetwork((int) $network['id'], [
                    'ip_address' => $ipAddress,
                    'status' => $status,
                    'asset_id' => $assetId,
                    'hostname' => $values['hostname'] ?? null,
                    'mac_address' => $values['mac_address'] ?? null,
                    'notes' => $values['notes'] ?? null,
                ]);

                $imported++;
                $updatedAddresses[] = $ipAddress;
            } catch (\Throwable $exception) {
                $failed++;
                $errors[] = ['row' => $rowNumber, 'message' => $exception->getMessage()];
            }
        }

        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors,
            'updated_addresses' => $updatedAddresses,
        ];
    }

    /**
     * @param array<string, string> $headerAliases
     *
     * @return list<array{row: int, values: array<string, string>}>|array{error: string, row: int}
     */
    private function parseCsv(string $csvContent, array $headerAliases): array
    {
        $csvContent = $this->stripBom($csvContent);
        $lines = preg_split('/\R/', $csvContent) ?? [];

        if ($lines === []) {
            return ['error' => __('import_csv_empty'), 'row' => 0];
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
                $headerMap = $this->mapHeaders($columns, $headerAliases);

                if ($headerMap === []) {
                    return ['error' => __('import_csv_invalid_headers'), 'row' => $lineNumber];
                }

                continue;
            }

            $rows[] = [
                'row' => $lineNumber,
                'values' => $this->normalizeRowValues($columns, $headerMap),
            ];
        }

        if ($headerMap === null) {
            return ['error' => __('import_csv_missing_headers'), 'row' => 0];
        }

        if ($rows === []) {
            return ['error' => __('import_csv_no_data_rows'), 'row' => 0];
        }

        return $rows;
    }

    /**
     * @param array<string, string> $values
     *
     * @return array<string, mixed>|null
     */
    private function resolveNetworkFromRow(array $values): ?array
    {
        $networkName = trim($values['network_name'] ?? '');

        if ($networkName !== '') {
            return $this->ipNetworkModel->findByName($networkName);
        }

        $networkCidr = trim($values['network_cidr'] ?? '');

        if ($networkCidr !== '' && str_contains($networkCidr, '/')) {
            [$address, $cidrPart] = explode('/', $networkCidr, 2);

            return $this->ipNetworkModel->findByAddressAndCidr(trim($address), (int) $cidrPart);
        }

        return null;
    }

    /**
     * @param list<string> $headers
     * @param array<string, string> $aliases
     *
     * @return array<int, string>
     */
    private function mapHeaders(array $headers, array $aliases): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeaderKey((string) $header);
            $canonical = $aliases[$normalized] ?? null;

            if ($canonical !== null) {
                $map[$index] = $canonical;
            }
        }

        return $map;
    }

    /**
     * @param list<string> $columns
     * @param array<int, string> $headerMap
     *
     * @return array<string, string>
     */
    private function normalizeRowValues(array $columns, array $headerMap): array
    {
        $values = [];

        foreach ($headerMap as $index => $key) {
            $values[$key] = trim((string) ($columns[$index] ?? ''));
        }

        return $values;
    }

    private function normalizeHeaderKey(string $header): string
    {
        $header = mb_strtolower(trim($header), 'UTF-8');
        $header = str_replace([' ', '-'], '_', $header);

        return $header;
    }

    private function normalizeStatus(string $status): ?string
    {
        $key = mb_strtolower(trim($status), 'UTF-8');

        if ($key === '') {
            return IpAddress::STATUS_AVAILABLE;
        }

        return self::STATUS_ALIASES[$key] ?? (in_array($key, IpAddress::STATUSES, true) ? $key : null);
    }

    private function normalizeBoolean(string $value): bool
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        return !in_array($value, ['0', 'no', 'false', 'hayir', 'hayır', 'off'], true);
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
        $escaped = array_map(static function (string $value): string {
            if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
                return '"' . str_replace('"', '""', $value) . '"';
            }

            return $value;
        }, $fields);

        return implode(',', $escaped) . "\n";
    }

    /**
     * @return array{imported: int, failed: int, errors: list<array{row: int, message: string}>, created_networks: list<string>}
     */
    private function emptyNetworkResultWithError(int $row, string $message): array
    {
        return [
            'imported' => 0,
            'failed' => 1,
            'errors' => [['row' => $row, 'message' => $message]],
            'created_networks' => [],
        ];
    }

    /**
     * @return array{imported: int, failed: int, errors: list<array{row: int, message: string}>, updated_addresses: list<string>}
     */
    private function emptyAddressResultWithError(int $row, string $message): array
    {
        return [
            'imported' => 0,
            'failed' => 1,
            'errors' => [['row' => $row, 'message' => $message]],
            'updated_addresses' => [],
        ];
    }
}
