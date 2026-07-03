<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssetType;
use App\Models\AuditLog;
use App\Models\User;

class AssetMutationLogger
{
    /** @var list<string> */
    private const SNAPSHOT_FIELDS = [
        'asset_tag',
        'name',
        'status',
        'serial_number',
        'assigned_to',
        'location',
        'building',
        'model',
        'brand',
        'type',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly EndUserContextService $endUserContextService,
        private readonly User $userModel,
        private readonly AssetType $assetTypeModel,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function logCreated(int $assetTypeId, int $assetId, array $row): void
    {
        $this->write(
            AuditLog::ACTION_CREATED,
            $assetTypeId,
            $assetId,
            null,
            $this->snapshot($row)
        );
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function logUpdated(int $assetTypeId, int $assetId, array $before, array $after): void
    {
        $this->write(
            AuditLog::ACTION_UPDATED,
            $assetTypeId,
            $assetId,
            $this->snapshot($before),
            $this->snapshot($after)
        );
    }

    /**
     * @param array<string, mixed> $before
     */
    public function logDeleted(int $assetTypeId, int $assetId, array $before): void
    {
        $this->write(
            AuditLog::ACTION_DELETED,
            $assetTypeId,
            $assetId,
            $this->snapshot($before),
            null
        );
    }

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    private function write(
        string $actionType,
        int $assetTypeId,
        int $assetId,
        ?array $oldValues,
        ?array $newValues
    ): void {
        if ($assetId <= 0) {
            return;
        }

        $slug = $this->resolveSlug($assetTypeId);
        $actorEmail = $this->resolveActorEmail();

        $this->auditLogger->log(
            $this->endUserContextService->resolveLegacyUserId(),
            $actionType,
            AuditLog::ENTITY_ASSET,
            $assetId,
            $this->attachContext($oldValues, $slug, $actorEmail),
            $this->attachContext($newValues, $slug, $actorEmail),
            null,
            $slug !== '' ? $slug : null
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function snapshot(array $row): array
    {
        $snapshot = [];

        foreach (self::SNAPSHOT_FIELDS as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            $value = $row[$field];

            if ($value === null) {
                $snapshot[$field] = null;

                continue;
            }

            if (is_scalar($value)) {
                $snapshot[$field] = trim((string) $value);

                continue;
            }

            $snapshot[$field] = $value;
        }

        return $snapshot;
    }

    /**
     * @param array<string, mixed>|null $values
     *
     * @return array<string, mixed>|null
     */
    private function attachContext(?array $values, string $slug, ?string $actorEmail): ?array
    {
        if ($values === null) {
            return null;
        }

        if ($slug !== '') {
            $values['asset_type'] = $slug;
        }

        if ($actorEmail !== null && $actorEmail !== '') {
            $values['actor_email'] = $actorEmail;
        }

        return $values;
    }

    private function resolveSlug(int $assetTypeId): string
    {
        if ($assetTypeId <= 0) {
            return '';
        }

        $assetType = $this->assetTypeModel->findById($assetTypeId);

        return trim((string) ($assetType['slug'] ?? ''));
    }

    private function resolveActorEmail(): ?string
    {
        $legacyUserId = $this->endUserContextService->resolveLegacyUserId();

        if ($legacyUserId === null) {
            return null;
        }

        $user = $this->userModel->findById($legacyUserId);

        if ($user === null) {
            return null;
        }

        $email = trim((string) ($user['email'] ?? ''));

        return $email !== '' ? $email : null;
    }
}
