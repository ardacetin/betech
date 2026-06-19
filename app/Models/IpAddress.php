<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class IpAddress
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_DHCP = 'dhcp';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_RESERVED,
        self::STATUS_ASSIGNED,
        self::STATUS_DHCP,
    ];

    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByNetworkId(int $networkId, ?string $status = null): array
    {
        $conditions = ['ip_addresses.network_id' => $networkId];

        if ($status !== null && $status !== '' && $status !== 'all') {
            $conditions['ip_addresses.status'] = $status;
        }

        $rows = $this->db()->select('ip_addresses', [
            '[>]assets' => ['asset_id' => 'id'],
        ], [
            'ip_addresses.id',
            'ip_addresses.network_id',
            'ip_addresses.ip_address',
            'ip_addresses.status',
            'ip_addresses.asset_id',
            'ip_addresses.hostname',
            'ip_addresses.mac_address',
            'ip_addresses.notes',
            'ip_addresses.created_at',
            'ip_addresses.updated_at',
            'assets.asset_tag(asset_tag)',
            'assets.name(asset_name)',
        ], array_merge($conditions, [
            'ORDER' => [
                'ip_addresses.ip_address' => 'ASC',
            ],
        ]));

        if (!is_array($rows)) {
            return [];
        }

        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db()->get('ip_addresses', [
            '[>]assets' => ['asset_id' => 'id'],
        ], [
            'ip_addresses.id',
            'ip_addresses.network_id',
            'ip_addresses.ip_address',
            'ip_addresses.status',
            'ip_addresses.asset_id',
            'ip_addresses.hostname',
            'ip_addresses.mac_address',
            'ip_addresses.notes',
            'ip_addresses.created_at',
            'ip_addresses.updated_at',
            'assets.asset_tag(asset_tag)',
            'assets.name(asset_name)',
        ], [
            'ip_addresses.id' => $id,
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByNetworkAndIp(int $networkId, string $ipAddress): ?array
    {
        $row = $this->db()->get('ip_addresses', [
            '[>]assets' => ['asset_id' => 'id'],
        ], [
            'ip_addresses.id',
            'ip_addresses.network_id',
            'ip_addresses.ip_address',
            'ip_addresses.status',
            'ip_addresses.asset_id',
            'ip_addresses.hostname',
            'ip_addresses.mac_address',
            'ip_addresses.notes',
            'ip_addresses.created_at',
            'ip_addresses.updated_at',
            'assets.asset_tag(asset_tag)',
            'assets.name(asset_name)',
        ], [
            'ip_addresses.network_id' => $networkId,
            'ip_addresses.ip_address' => trim($ipAddress),
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $payload): ?array
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            return null;
        }

        $updates = [];

        if (array_key_exists('status', $payload)) {
            $status = strtolower(trim((string) $payload['status']));

            if (!in_array($status, self::STATUSES, true)) {
                throw new \InvalidArgumentException(__('ipam_invalid_status'));
            }

            $updates['status'] = $status;
        }

        if (array_key_exists('asset_id', $payload)) {
            $assetId = $payload['asset_id'];

            if ($assetId === null || $assetId === '') {
                $updates['asset_id'] = null;
            } else {
                $parsed = (int) $assetId;
                $updates['asset_id'] = $parsed > 0 ? $parsed : null;
            }
        }

        if (array_key_exists('hostname', $payload)) {
            $updates['hostname'] = $this->normalizeOptionalText((string) $payload['hostname']);
        }

        if (array_key_exists('mac_address', $payload)) {
            $updates['mac_address'] = $this->normalizeOptionalText((string) $payload['mac_address']);
        }

        if (array_key_exists('notes', $payload)) {
            $updates['notes'] = $this->normalizeOptionalText((string) $payload['notes']);
        }

        if ($updates !== []) {
            $this->db()->update('ip_addresses', $updates, ['id' => $id]);
        }

        return $this->findById($id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsertForNetwork(int $networkId, array $data): void
    {
        $ipAddress = trim((string) ($data['ip_address'] ?? ''));

        if ($ipAddress === '') {
            throw new \InvalidArgumentException(__('ipam_ip_required'));
        }

        $status = strtolower(trim((string) ($data['status'] ?? self::STATUS_AVAILABLE)));

        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(__('ipam_invalid_status'));
        }

        $existing = $this->findByNetworkAndIp($networkId, $ipAddress);
        $assetId = isset($data['asset_id']) && (int) $data['asset_id'] > 0 ? (int) $data['asset_id'] : null;

        $record = [
            'status' => $status,
            'asset_id' => $assetId,
            'hostname' => $this->normalizeOptionalText(isset($data['hostname']) ? (string) $data['hostname'] : null),
            'mac_address' => $this->normalizeOptionalText(isset($data['mac_address']) ? (string) $data['mac_address'] : null),
            'notes' => $this->normalizeOptionalText(isset($data['notes']) ? (string) $data['notes'] : null),
        ];

        if ($existing === null) {
            $this->db()->insert('ip_addresses', [
                'network_id' => $networkId,
                'ip_address' => $ipAddress,
                ...$record,
            ]);

            return;
        }

        $this->db()->update('ip_addresses', $record, ['id' => (int) $existing['id']]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'network_id' => (int) ($row['network_id'] ?? 0),
            'ip_address' => (string) ($row['ip_address'] ?? ''),
            'status' => (string) ($row['status'] ?? self::STATUS_AVAILABLE),
            'asset_id' => $row['asset_id'] !== null ? (int) $row['asset_id'] : null,
            'asset_tag' => (string) ($row['asset_tag'] ?? ''),
            'asset_name' => (string) ($row['asset_name'] ?? ''),
            'hostname' => $row['hostname'] !== null ? (string) $row['hostname'] : null,
            'mac_address' => $row['mac_address'] !== null ? (string) $row['mac_address'] : null,
            'notes' => $row['notes'] !== null ? (string) $row['notes'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
