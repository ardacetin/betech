<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use Medoo\Medoo;

class License
{
    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $rows = $this->db()->select('licenses', [
            'id',
            'name',
            'vendor',
            'license_key',
            'seats',
            'expiration_date',
            'notes',
            'created_at',
        ], [
            'ORDER' => [
                'vendor' => 'ASC',
                'name' => 'ASC',
            ],
        ]);

        return array_map(
            fn (array $row): array => $this->withSeatMetrics($this->normalizeLicenseRow($row)),
            $rows
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db()->get('licenses', [
            'id',
            'name',
            'vendor',
            'license_key',
            'seats',
            'expiration_date',
            'notes',
            'created_at',
        ], [
            'id' => $id,
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->withSeatMetrics($this->normalizeLicenseRow($row));
    }

    /**
     * @return array<string, mixed>
     */
    public function create(
        string $name,
        string $vendor,
        int $seats,
        ?string $licenseKey,
        ?string $expirationDate,
        ?string $notes
    ): array {
        $trimmedName = trim($name);
        $trimmedVendor = trim($vendor);

        if ($trimmedName === '') {
            throw new \InvalidArgumentException(__('license_name_required'));
        }

        if ($trimmedVendor === '') {
            throw new \InvalidArgumentException(__('license_vendor_required'));
        }

        if ($seats < 1) {
            throw new \InvalidArgumentException(__('license_seats_invalid'));
        }

        $this->db()->insert('licenses', [
            'name' => $trimmedName,
            'vendor' => $trimmedVendor,
            'license_key' => $this->normalizeOptionalText($licenseKey),
            'seats' => $seats,
            'expiration_date' => $this->normalizeOptionalDate($expirationDate),
            'notes' => $this->normalizeOptionalText($notes),
        ]);

        $created = $this->findById((int) $this->db()->id());

        if ($created === null) {
            throw new \RuntimeException(__('license_create_error'));
        }

        return $created;
    }

    public function countAssignments(int $licenseId): int
    {
        return $this->db()->count('license_assignments', [
            'license_id' => $licenseId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function assign(int $licenseId, ?int $assetId, ?int $userId): array
    {
        $license = $this->findById($licenseId);

        if ($license === null) {
            throw new \RuntimeException(__('license_not_found'));
        }

        $hasAsset = $assetId !== null && $assetId > 0;
        $hasUser = $userId !== null && $userId > 0;

        if ($hasAsset === $hasUser) {
            throw new \InvalidArgumentException(__('license_assign_target_required'));
        }

        if ((int) ($license['remaining_seats'] ?? 0) <= 0) {
            throw new \RuntimeException(__('license_no_remaining_seats'));
        }

        if ($hasAsset && !$this->db()->has('assets', ['id' => $assetId])) {
            throw new \InvalidArgumentException(__('license_asset_not_found'));
        }

        if ($hasUser && !$this->db()->has('users', ['id' => $userId])) {
            throw new \InvalidArgumentException(__('license_user_not_found'));
        }

        $duplicateConditions = [
            'license_id' => $licenseId,
        ];

        if ($hasAsset) {
            $duplicateConditions['asset_id'] = $assetId;
        } else {
            $duplicateConditions['user_id'] = $userId;
        }

        if ($this->db()->has('license_assignments', $duplicateConditions)) {
            throw new \RuntimeException(__('license_assign_duplicate'));
        }

        $this->db()->insert('license_assignments', [
            'license_id' => $licenseId,
            'asset_id' => $hasAsset ? $assetId : null,
            'user_id' => $hasUser ? $userId : null,
        ]);

        $assignmentId = (int) $this->db()->id();
        $assignment = $this->findAssignmentById($assignmentId);

        if ($assignment === null) {
            throw new \RuntimeException(__('license_assign_error'));
        }

        return $assignment;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function unassign(int $licenseId, int $assignmentId): ?array
    {
        if ($this->findById($licenseId) === null) {
            return null;
        }

        $assignment = $this->findAssignmentById($assignmentId);

        if ($assignment === null || (int) ($assignment['license_id'] ?? 0) !== $licenseId) {
            return null;
        }

        $this->db()->delete('license_assignments', [
            'id' => $assignmentId,
            'license_id' => $licenseId,
        ]);

        return $assignment;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAssignmentsForLicense(int $licenseId): array
    {
        return array_map(
            fn (array $row): array => $this->normalizeAssignmentRow($row),
            $this->db()->select('license_assignments', [
                '[>]assets' => ['asset_id' => 'id'],
                '[>]users' => ['user_id' => 'id'],
            ], [
                'license_assignments.id',
                'license_assignments.license_id',
                'license_assignments.asset_id',
                'license_assignments.user_id',
                'license_assignments.assigned_at',
                'assets.asset_tag',
                'assets.name(asset_name)',
                'users.name(user_name)',
                'users.email(user_email)',
            ], [
                'license_assignments.license_id' => $licenseId,
                'ORDER' => [
                    'license_assignments.assigned_at' => 'DESC',
                ],
            ])
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAssignmentsForAsset(int $assetId): array
    {
        return array_map(
            fn (array $row): array => $this->normalizeAssignmentRow($row),
            $this->db()->select('license_assignments', [
                '[>]licenses' => ['license_id' => 'id'],
            ], [
                'license_assignments.id',
                'license_assignments.license_id',
                'license_assignments.asset_id',
                'license_assignments.user_id',
                'license_assignments.assigned_at',
                'licenses.name(license_name)',
                'licenses.vendor(license_vendor)',
                'licenses.expiration_date(license_expiration_date)',
            ], [
                'license_assignments.asset_id' => $assetId,
                'ORDER' => [
                    'licenses.vendor' => 'ASC',
                    'licenses.name' => 'ASC',
                ],
            ])
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAssignmentById(int $assignmentId): ?array
    {
        $row = $this->db()->get('license_assignments', [
            '[>]assets' => ['asset_id' => 'id'],
            '[>]users' => ['user_id' => 'id'],
        ], [
            'license_assignments.id',
            'license_assignments.license_id',
            'license_assignments.asset_id',
            'license_assignments.user_id',
            'license_assignments.assigned_at',
            'assets.asset_tag',
            'assets.name(asset_name)',
            'users.name(user_name)',
            'users.email(user_email)',
        ], [
            'license_assignments.id' => $assignmentId,
        ]);

        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeAssignmentRow($row);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeLicenseRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['seats'] = (int) $row['seats'];

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function withSeatMetrics(array $row): array
    {
        $assignedSeats = $this->countAssignments((int) $row['id']);
        $totalSeats = (int) $row['seats'];
        $row['assigned_seats'] = $assignedSeats;
        $row['remaining_seats'] = max(0, $totalSeats - $assignedSeats);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeAssignmentRow(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['license_id'] = (int) $row['license_id'];
        $row['asset_id'] = isset($row['asset_id']) && $row['asset_id'] !== null
            ? (int) $row['asset_id']
            : null;
        $row['user_id'] = isset($row['user_id']) && $row['user_id'] !== null
            ? (int) $row['user_id']
            : null;

        return $row;
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeOptionalDate(?string $value): ?string
    {
        $normalized = $this->normalizeOptionalText($value);

        if ($normalized === null) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $normalized);

        if ($date === false || $date->format('Y-m-d') !== $normalized) {
            throw new \InvalidArgumentException(__('license_expiration_invalid'));
        }

        return $normalized;
    }
}
