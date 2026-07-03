<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class AssetsGlobalRegistry
{
    public function __construct(
        private readonly DatabaseService $databaseService,
    ) {
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }

    /**
     * @param array<string, mixed> $row
     */
    public function sync(int $assetId, string $assetTypeSlug, array $row): void
    {
        if ($assetId <= 0 || trim($assetTypeSlug) === '' || !$this->tableExists()) {
            return;
        }

        $mac = trim((string) ($row['mac_address_1'] ?? ''));

        if ($mac === '') {
            $mac = trim((string) ($row['mac_address_2'] ?? ''));
        }

        $payload = [
            'asset_tag' => trim((string) ($row['asset_tag'] ?? '')),
            'serial_number' => $this->nullableString($row['serial_number'] ?? null),
            'mac_address' => $mac !== '' ? $mac : null,
            'asset_type' => trim($assetTypeSlug),
            'assigned_to' => $this->nullableString($row['assigned_to'] ?? null),
            'name' => trim((string) ($row['name'] ?? '')),
            'status' => trim((string) ($row['status'] ?? 'ready')) ?: 'ready',
        ];

        if ($this->db()->has('assets_global_registry', ['id' => $assetId])) {
            $this->db()->update('assets_global_registry', $payload, ['id' => $assetId]);

            return;
        }

        $payload['id'] = $assetId;
        $this->db()->insert('assets_global_registry', $payload);
    }

    public function unregister(int $assetId): void
    {
        if ($assetId <= 0 || !$this->tableExists()) {
            return;
        }

        $this->db()->delete('assets_global_registry', ['id' => $assetId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByAssignedReferences(string $email, string $name): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $needles = array_values(array_unique(array_filter([$email, $name], static fn (string $value): bool => $value !== '')));

        if ($needles === []) {
            return [];
        }

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $this->db()->select('assets_global_registry', '*', [
                'assigned_to' => $needles,
                'ORDER' => ['id' => 'DESC'],
            ])
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $assetId): ?array
    {
        if ($assetId <= 0 || !$this->tableExists()) {
            return null;
        }

        $row = $this->db()->get('assets_global_registry', '*', ['id' => $assetId]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, int $limit = 50): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $trimmed = trim($query);

        if ($trimmed === '') {
            return [];
        }

        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $this->db()->select('assets_global_registry', '*', [
                'OR' => [
                    'asset_tag[~]' => $trimmed,
                    'serial_number[~]' => $trimmed,
                    'mac_address[~]' => $trimmed,
                    'name[~]' => $trimmed,
                    'assigned_to[~]' => $trimmed,
                ],
                'ORDER' => ['id' => 'DESC'],
                'LIMIT' => max(1, min(200, $limit)),
            ])
        );
    }

    public function resolveTypeSlug(int $assetId): ?string
    {
        if ($assetId <= 0 || !$this->tableExists()) {
            return null;
        }

        $slug = $this->db()->get('assets_global_registry', 'asset_type', ['id' => $assetId]);

        if (!is_string($slug) || trim($slug) === '') {
            return null;
        }

        return trim($slug);
    }

    public function tableExists(): bool
    {
        $statement = $this->db()->query(
            "SHOW TABLES LIKE 'assets_global_registry'"
        );

        if ($statement === false) {
            return false;
        }

        return $statement->fetch() !== false;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        if (isset($row['id'])) {
            $row['id'] = (int) $row['id'];
        }

        $row['asset_type_slug'] = trim((string) ($row['asset_type'] ?? ''));

        return $row;
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
