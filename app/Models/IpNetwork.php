<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use App\Services\IpAddressGenerator;
use Medoo\Medoo;

class IpNetwork
{
    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly IpAddressGenerator $ipAddressGenerator
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $rows = $this->db()->select('ip_networks', [
            'id',
            'name',
            'network_address',
            'cidr',
            'gateway',
            'vlan_id',
            'description',
            'created_at',
            'updated_at',
        ], [
            'ORDER' => ['name' => 'ASC', 'network_address' => 'ASC'],
        ]);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(fn (array $row): array => $this->withUtilization($this->normalizeRow($row)), $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findHighUtilization(int $thresholdPercent = 90): array
    {
        if ($thresholdPercent < 1) {
            return [];
        }

        return array_values(array_filter(
            $this->findAll(),
            static fn (array $network): bool => (int) ($network['utilization_percent'] ?? 0) >= $thresholdPercent
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db()->get('ip_networks', [
            'id',
            'name',
            'network_address',
            'cidr',
            'gateway',
            'vlan_id',
            'description',
            'created_at',
            'updated_at',
        ], [
            'id' => $id,
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->withUtilization($this->normalizeRow($row));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByAddressAndCidr(string $networkAddress, int $cidr): ?array
    {
        $row = $this->db()->get('ip_networks', [
            'id',
            'name',
            'network_address',
            'cidr',
            'gateway',
            'vlan_id',
            'description',
            'created_at',
            'updated_at',
        ], [
            'network_address' => $networkAddress,
            'cidr' => $cidr,
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->withUtilization($this->normalizeRow($row));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByName(string $name): ?array
    {
        $row = $this->db()->get('ip_networks', [
            'id',
            'name',
            'network_address',
            'cidr',
            'gateway',
            'vlan_id',
            'description',
            'created_at',
            'updated_at',
        ], [
            'name' => trim($name),
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->withUtilization($this->normalizeRow($row));
    }

    /**
     * @return array<string, mixed>
     */
    public function create(
        string $name,
        string $networkAddress,
        int $cidr,
        ?string $gateway,
        ?int $vlanId,
        ?string $description,
        bool $autoGenerate = true
    ): array {
        $trimmedName = trim($name);
        $networkAddress = trim($networkAddress);

        if ($trimmedName === '') {
            throw new \InvalidArgumentException(__('ipam_network_name_required'));
        }

        if ($cidr < 0 || $cidr > 32) {
            throw new \InvalidArgumentException(__('ipam_invalid_cidr'));
        }

        if (!filter_var($networkAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new \InvalidArgumentException(__('ipam_invalid_network_address'));
        }

        $networkLong = ip2long($networkAddress);

        if ($networkLong === false) {
            throw new \InvalidArgumentException(__('ipam_invalid_network_address'));
        }

        $networkAddress = long2ip($this->ipAddressGenerator->normalizeNetworkLong($networkLong, $cidr));

        if ($this->findByAddressAndCidr($networkAddress, $cidr) !== null) {
            throw new \InvalidArgumentException(__('ipam_network_duplicate'));
        }

        $this->db()->insert('ip_networks', [
            'name' => $trimmedName,
            'network_address' => $networkAddress,
            'cidr' => $cidr,
            'gateway' => $this->normalizeOptionalText($gateway),
            'vlan_id' => $vlanId !== null && $vlanId > 0 ? $vlanId : null,
            'description' => $this->normalizeOptionalText($description),
        ]);

        $networkId = (int) $this->db()->id();
        $created = $this->findById($networkId);

        if ($created === null) {
            throw new \RuntimeException(__('ipam_network_create_error'));
        }

        if ($autoGenerate) {
            $this->generateAddresses($networkId);
            $created = $this->findById($networkId) ?? $created;
        }

        return $created;
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

        if (array_key_exists('name', $payload)) {
            $name = trim((string) $payload['name']);

            if ($name === '') {
                throw new \InvalidArgumentException(__('ipam_network_name_required'));
            }

            $updates['name'] = $name;
        }

        if (array_key_exists('gateway', $payload)) {
            $updates['gateway'] = $this->normalizeOptionalText((string) $payload['gateway']);
        }

        if (array_key_exists('vlan_id', $payload)) {
            $vlanId = (int) ($payload['vlan_id'] ?? 0);
            $updates['vlan_id'] = $vlanId > 0 ? $vlanId : null;
        }

        if (array_key_exists('description', $payload)) {
            $updates['description'] = $this->normalizeOptionalText((string) $payload['description']);
        }

        if ($updates !== []) {
            $this->db()->update('ip_networks', $updates, ['id' => $id]);
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $this->db()->delete('ip_networks', ['id' => $id]);

        return true;
    }

    /**
     * @return int Number of addresses generated
     */
    public function generateAddresses(int $networkId): int
    {
        $network = $this->findById($networkId);

        if ($network === null) {
            throw new \InvalidArgumentException(__('ipam_network_not_found'));
        }

        $addresses = $this->ipAddressGenerator->generateUsableAddresses(
            (string) $network['network_address'],
            (int) $network['cidr']
        );

        $inserted = 0;

        foreach ($addresses as $ipAddress) {
            $existing = $this->db()->get('ip_addresses', 'id', [
                'network_id' => $networkId,
                'ip_address' => $ipAddress,
            ]);

            if ($existing !== null) {
                continue;
            }

            $this->db()->insert('ip_addresses', [
                'network_id' => $networkId,
                'ip_address' => $ipAddress,
                'status' => IpAddress::STATUS_AVAILABLE,
            ]);
            $inserted++;
        }

        return $inserted;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function withUtilization(array $row): array
    {
        $networkId = (int) ($row['id'] ?? 0);
        $total = (int) $this->db()->count('ip_addresses', ['network_id' => $networkId]);
        $available = (int) $this->db()->count('ip_addresses', [
            'network_id' => $networkId,
            'status' => IpAddress::STATUS_AVAILABLE,
        ]);
        $used = max(0, $total - $available);

        try {
            $capacity = count($this->ipAddressGenerator->generateUsableAddresses(
                (string) $row['network_address'],
                (int) $row['cidr']
            ));
        } catch (\Throwable) {
            $capacity = $total;
        }

        if ($total === 0) {
            $capacity = max($capacity, 0);
        } else {
            $capacity = max($capacity, $total);
        }

        $row['total_ips'] = $total;
        $row['capacity_ips'] = $capacity;
        $row['used_ips'] = $used;
        $row['available_ips'] = $available;
        $row['utilization_percent'] = $capacity > 0 ? (int) round(($used / $capacity) * 100) : 0;
        $row['cidr_notation'] = $this->ipAddressGenerator->cidrNotation(
            (string) $row['network_address'],
            (int) $row['cidr']
        );

        return $row;
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
            'name' => (string) ($row['name'] ?? ''),
            'network_address' => (string) ($row['network_address'] ?? ''),
            'cidr' => (int) ($row['cidr'] ?? 0),
            'gateway' => $row['gateway'] !== null ? (string) $row['gateway'] : null,
            'vlan_id' => $row['vlan_id'] !== null ? (int) $row['vlan_id'] : null,
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
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
