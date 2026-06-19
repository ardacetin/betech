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

    /**
     * @param array{
     *     name?: string,
     *     vendor?: string,
     *     seats?: int,
     *     license_key?: string|null,
     *     expiration_date?: string|null,
     *     notes?: string|null
     * } $payload
     *
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $payload): ?array
    {
        if ($this->findById($id) === null) {
            return null;
        }

        $update = [];

        if (array_key_exists('name', $payload)) {
            $trimmedName = trim((string) $payload['name']);

            if ($trimmedName === '') {
                throw new \InvalidArgumentException(__('license_name_required'));
            }

            $update['name'] = $trimmedName;
        }

        if (array_key_exists('vendor', $payload)) {
            $trimmedVendor = trim((string) $payload['vendor']);

            if ($trimmedVendor === '') {
                throw new \InvalidArgumentException(__('license_vendor_required'));
            }

            $update['vendor'] = $trimmedVendor;
        }

        if (array_key_exists('seats', $payload)) {
            $seats = (int) $payload['seats'];

            if ($seats < 1) {
                throw new \InvalidArgumentException(__('license_seats_invalid'));
            }

            $assignedSeats = $this->countAssignments($id);

            if ($seats < $assignedSeats) {
                throw new \InvalidArgumentException(__('license_allocated_exceeds_total'));
            }

            $update['seats'] = $seats;
        }

        if (array_key_exists('license_key', $payload)) {
            $update['license_key'] = $this->normalizeOptionalText(
                $payload['license_key'] !== null ? (string) $payload['license_key'] : null
            );
        }

        if (array_key_exists('expiration_date', $payload)) {
            $update['expiration_date'] = $this->normalizeOptionalDate(
                $payload['expiration_date'] !== null ? (string) $payload['expiration_date'] : null
            );
        }

        if (array_key_exists('notes', $payload)) {
            $update['notes'] = $this->normalizeOptionalText(
                $payload['notes'] !== null ? (string) $payload['notes'] : null
            );
        }

        if ($update !== []) {
            $this->db()->update('licenses', $update, ['id' => $id]);
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $this->db()->delete('licenses', ['id' => $id]);

        return !$this->db()->has('licenses', ['id' => $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findExpiringWithinDays(int $days = 30): array
    {
        if ($days < 1) {
            return [];
        }

        $statement = $this->db()->query(
            'SELECT id, name, vendor, seats, expiration_date
            FROM licenses
            WHERE expiration_date IS NOT NULL
              AND expiration_date >= CURDATE()
              AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
            ORDER BY expiration_date ASC, vendor ASC, name ASC',
            [
                ':days' => $days,
            ]
        );

        if ($statement === false) {
            return [];
        }

        $licenses = [];

        foreach ($statement->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }

            $license = $this->withSeatMetrics($this->normalizeLicenseRow($row));
            $licenses[] = $license;
        }

        return $licenses;
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
    public function assign(int $licenseId, ?int $assetId, ?int $personnelId): array
    {
        $license = $this->findById($licenseId);

        if ($license === null) {
            throw new \RuntimeException(__('license_not_found'));
        }

        $hasAsset = $assetId !== null && $assetId > 0;
        $hasPersonnel = $personnelId !== null && $personnelId > 0;

        if ($hasAsset === $hasPersonnel) {
            throw new \InvalidArgumentException(__('license_assign_target_required'));
        }

        if ((int) ($license['remaining_seats'] ?? 0) <= 0) {
            throw new \RuntimeException(__('license_no_remaining_seats'));
        }

        if ($hasAsset && !$this->db()->has('assets', ['id' => $assetId])) {
            throw new \InvalidArgumentException(__('license_asset_not_found'));
        }

        if ($hasPersonnel && !$this->db()->has('personnel', ['id' => $personnelId])) {
            throw new \InvalidArgumentException(__('license_user_not_found'));
        }

        $duplicateConditions = [
            'license_id' => $licenseId,
        ];

        if ($hasAsset) {
            $duplicateConditions['asset_id'] = $assetId;
        } else {
            $duplicateConditions['personnel_id'] = $personnelId;
        }

        if ($this->db()->has('license_assignments', $duplicateConditions)) {
            throw new \RuntimeException(__('license_assign_duplicate'));
        }

        $this->db()->insert('license_assignments', [
            'license_id' => $licenseId,
            'asset_id' => $hasAsset ? $assetId : null,
            'personnel_id' => $hasPersonnel ? $personnelId : null,
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
                '[>]personnel' => ['personnel_id' => 'id'],
            ], [
                'license_assignments.id',
                'license_assignments.license_id',
                'license_assignments.asset_id',
                'license_assignments.personnel_id',
                'license_assignments.assigned_at',
                'assets.asset_tag',
                'assets.name(asset_name)',
                'personnel.name(personnel_name)',
                'personnel.email(personnel_email)',
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
                'license_assignments.personnel_id',
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
            '[>]personnel' => ['personnel_id' => 'id'],
        ], [
            'license_assignments.id',
            'license_assignments.license_id',
            'license_assignments.asset_id',
            'license_assignments.personnel_id',
            'license_assignments.assigned_at',
            'assets.asset_tag',
            'assets.name(asset_name)',
            'personnel.name(personnel_name)',
            'personnel.email(personnel_email)',
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
        $row['allocated_seats'] = $assignedSeats;
        $row['remaining_seats'] = max(0, $totalSeats - $assignedSeats);
        $row['total_seats'] = $totalSeats;
        $row['software_name'] = (string) ($row['name'] ?? '');
        $row['is_expiring_soon'] = $this->isExpiringSoon(isset($row['expiration_date']) ? (string) $row['expiration_date'] : null);

        return $row;
    }

    private function isExpiringSoon(?string $expirationDate): bool
    {
        if ($expirationDate === null || trim($expirationDate) === '') {
            return false;
        }

        $expiration = \DateTimeImmutable::createFromFormat('Y-m-d', $expirationDate);

        if ($expiration === false) {
            return false;
        }

        $today = new \DateTimeImmutable('today');
        $threshold = $today->modify('+30 days');

        return $expiration >= $today && $expiration <= $threshold;
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
        $row['personnel_id'] = isset($row['personnel_id']) && $row['personnel_id'] !== null
            ? (int) $row['personnel_id']
            : null;

        if (array_key_exists('personnel_name', $row)) {
            $row['user_name'] = $row['personnel_name'];
        }

        if ($row['personnel_id'] !== null) {
            $row['user_id'] = $row['personnel_id'];
        }

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
