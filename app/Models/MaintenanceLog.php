<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class MaintenanceLog
{
    public const STATUS_UNDER_REPAIR = 'under_repair';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_UNREPAIRABLE = 'unrepairable';

    public const ASSET_STATUS_UNDER_REPAIR = 'under_repair';
    public const ASSET_STATUS_RETURNED = 'storage';

    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly Asset $assetModel,
        private readonly AssetHistory $assetHistoryModel
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        return $this->mapRows($this->selectRows());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findActive(): array
    {
        return $this->mapRows($this->selectRows([
            'maintenance_logs.status' => self::STATUS_UNDER_REPAIR,
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $rows = $this->selectRows(['maintenance_logs.id' => $id], 1);

        return $rows[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByAssetId(int $assetId): array
    {
        return $this->mapRows($this->selectRows([
            'maintenance_logs.asset_id' => $assetId,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function create(
        int $assetId,
        string $providerName,
        string $issueDescription,
        string $sentDate,
        string $status = self::STATUS_UNDER_REPAIR,
        ?float $repairCost = null,
        ?string $returnDate = null
    ): array {
        if ($this->assetModel->findById($assetId) === null) {
            throw new \InvalidArgumentException(__('maintenance_asset_not_found'));
        }

        $normalizedStatus = $this->normalizeStatus($status);
        $payload = $this->buildPayload($providerName, $issueDescription, $sentDate, $normalizedStatus, $repairCost, $returnDate);
        $payload['asset_id'] = $assetId;

        $created = null;

        $this->db()->action(function (Medoo $db) use ($payload, $assetId, $normalizedStatus, &$created): void {
            $db->insert('maintenance_logs', $payload);
            $createdId = (int) $db->id();
            $this->applyAssetStatusForLog($assetId, $normalizedStatus, $payload['return_date'] ?? null);
            $created = $this->findById($createdId);
        });

        if ($created === null) {
            throw new \RuntimeException(__('maintenance_create_error'));
        }

        $this->logMaintenanceEvent(
            $assetId,
            $this->historyActionForStatus($normalizedStatus),
            sprintf('Provider: %s', $payload['provider_name'])
        );

        return $created;
    }

    /**
     * @param array{
     *     provider_name?: string,
     *     issue_description?: string,
     *     repair_cost?: float|int|string|null,
     *     sent_date?: string,
     *     return_date?: string|null,
     *     status?: string
     * } $fields
     *
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $fields): ?array
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            return null;
        }

        $update = [];

        if (array_key_exists('provider_name', $fields)) {
            $providerName = trim((string) $fields['provider_name']);

            if ($providerName === '') {
                throw new \InvalidArgumentException(__('maintenance_provider_required'));
            }

            $update['provider_name'] = $providerName;
        }

        if (array_key_exists('issue_description', $fields)) {
            $issueDescription = trim((string) $fields['issue_description']);

            if ($issueDescription === '') {
                throw new \InvalidArgumentException(__('maintenance_issue_required'));
            }

            $update['issue_description'] = $issueDescription;
        }

        if (array_key_exists('repair_cost', $fields)) {
            $update['repair_cost'] = $this->normalizeRepairCost($fields['repair_cost']);
        }

        if (array_key_exists('sent_date', $fields)) {
            $update['sent_date'] = $this->normalizeDate((string) $fields['sent_date'], __('maintenance_sent_date_invalid'));
        }

        if (array_key_exists('return_date', $fields)) {
            $update['return_date'] = $this->normalizeOptionalDate($fields['return_date']);
        }

        if (array_key_exists('status', $fields)) {
            $update['status'] = $this->normalizeStatus((string) $fields['status']);
        }

        if ($update === []) {
            return $existing;
        }

        $assetId = (int) $existing['asset_id'];
        $nextStatus = (string) ($update['status'] ?? $existing['status']);
        $nextReturnDate = array_key_exists('return_date', $update)
            ? $update['return_date']
            : ($existing['return_date'] ?? null);

        $updated = null;

        $this->db()->action(function (Medoo $db) use ($id, $update, $assetId, $nextStatus, $nextReturnDate, &$updated): void {
            $db->update('maintenance_logs', $update, ['id' => $id]);
            $this->applyAssetStatusForLog($assetId, $nextStatus, $nextReturnDate);
            $updated = $this->findById($id);
        });

        if ($updated !== null && array_key_exists('status', $update)) {
            $this->logMaintenanceEvent(
                $assetId,
                $this->historyActionForStatus($nextStatus),
                sprintf('Maintenance log #%d updated.', $id)
            );
        }

        return $updated;
    }

    public function delete(int $id): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $this->db()->delete('maintenance_logs', ['id' => $id]);

        return !$this->db()->has('maintenance_logs', ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $conditions
     *
     * @return list<array<string, mixed>>
     */
    private function selectRows(array $conditions = [], ?int $limit = null): array
    {
        $options = [
            'ORDER' => [
                'maintenance_logs.sent_date' => 'DESC',
                'maintenance_logs.id' => 'DESC',
            ],
        ];

        if ($conditions !== []) {
            $options = [...$conditions, ...$options];
        }

        if ($limit !== null) {
            $options['LIMIT'] = $limit;
        }

        return $this->db()->select('maintenance_logs', [
            '[>]assets' => ['asset_id' => 'id'],
        ], [
            'maintenance_logs.id',
            'maintenance_logs.asset_id',
            'maintenance_logs.provider_name',
            'maintenance_logs.issue_description',
            'maintenance_logs.repair_cost',
            'maintenance_logs.sent_date',
            'maintenance_logs.return_date',
            'maintenance_logs.status',
            'maintenance_logs.created_at',
            'assets.asset_tag',
            'assets.name(asset_name)',
            'assets.status(asset_status)',
        ], $options);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function mapRows(array $rows): array
    {
        return array_map(
            fn (array $row): array => $this->normalizeRow($row),
            $rows
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['asset_id'] = (int) $row['asset_id'];
        $row['status'] = (string) $row['status'];
        $row['repair_cost'] = $row['repair_cost'] !== null ? (float) $row['repair_cost'] : null;
        $row['is_active'] = $row['status'] === self::STATUS_UNDER_REPAIR;

        return $row;
    }

    private function buildPayload(
        string $providerName,
        string $issueDescription,
        string $sentDate,
        string $status,
        ?float $repairCost,
        ?string $returnDate
    ): array {
        $trimmedProvider = trim($providerName);
        $trimmedIssue = trim($issueDescription);

        if ($trimmedProvider === '') {
            throw new \InvalidArgumentException(__('maintenance_provider_required'));
        }

        if ($trimmedIssue === '') {
            throw new \InvalidArgumentException(__('maintenance_issue_required'));
        }

        return [
            'provider_name' => $trimmedProvider,
            'issue_description' => $trimmedIssue,
            'repair_cost' => $repairCost,
            'sent_date' => $this->normalizeDate($sentDate, __('maintenance_sent_date_invalid')),
            'return_date' => $this->normalizeOptionalDate($returnDate),
            'status' => $status,
        ];
    }

    private function applyAssetStatusForLog(int $assetId, string $status, ?string $returnDate): void
    {
        if ($status === self::STATUS_UNDER_REPAIR) {
            $this->assetModel->update($assetId, ['status' => self::ASSET_STATUS_UNDER_REPAIR]);

            return;
        }

        if ($status === self::STATUS_UNREPAIRABLE) {
            $this->assetModel->update($assetId, ['status' => 'broken']);

            return;
        }

        if ($status === self::STATUS_RESOLVED && $returnDate !== null && $returnDate !== '') {
            $this->assetModel->update($assetId, ['status' => self::ASSET_STATUS_RETURNED]);
        }
    }

    private function historyActionForStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_RESOLVED => 'maintenance_resolved',
            self::STATUS_UNREPAIRABLE => 'maintenance_unrepairable',
            default => 'maintenance_sent',
        };
    }

    private function logMaintenanceEvent(int $assetId, string $action, string $notes): void
    {
        $this->assetHistoryModel->log($assetId, $action, null, null, $notes);
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            self::STATUS_RESOLVED => self::STATUS_RESOLVED,
            self::STATUS_UNREPAIRABLE => self::STATUS_UNREPAIRABLE,
            default => self::STATUS_UNDER_REPAIR,
        };
    }

    /**
     * @param mixed $value
     */
    private function normalizeRepairCost(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(__('maintenance_repair_cost_invalid'));
        }

        $cost = (float) $value;

        if ($cost < 0) {
            throw new \InvalidArgumentException(__('maintenance_repair_cost_invalid'));
        }

        return round($cost, 2);
    }

    private function normalizeDate(string $value, string $errorMessage): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new \InvalidArgumentException($errorMessage);
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $trimmed);

        if ($date === false || $date->format('Y-m-d') !== $trimmed) {
            throw new \InvalidArgumentException($errorMessage);
        }

        return $trimmed;
    }

    /**
     * @param mixed $value
     */
    private function normalizeOptionalDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalizeDate((string) $value, __('maintenance_return_date_invalid'));
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
