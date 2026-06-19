<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Psr\Http\Message\ServerRequestInterface;

class AuditLogger
{
    public function __construct(
        private readonly AuditLog $auditLogModel,
        private readonly AuditChangeFormatter $changeFormatter,
        private readonly ClientIpResolver $clientIpResolver
    ) {
    }

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function log(
        ?int $userId,
        string $actionType,
        string $entityType,
        ?int $entityId,
        ?array $oldValues,
        ?array $newValues,
        ?string $ipAddress = null
    ): void {
        try {
            $this->auditLogModel->create(
                $userId,
                $actionType,
                $entityType,
                $entityId,
                $this->changeFormatter->maskValues($oldValues),
                $this->changeFormatter->maskValues($newValues),
                $ipAddress
            );
        } catch (\Throwable) {
            // Audit failures must never break primary operations.
        }
    }

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function logFromRequest(
        ServerRequestInterface $request,
        ?int $userId,
        string $actionType,
        string $entityType,
        ?int $entityId,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $this->log(
            $userId,
            $actionType,
            $entityType,
            $entityId,
            $oldValues,
            $newValues,
            $this->clientIpResolver->resolveFromRequest($request)
        );
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param list<string> $changedSections
     *
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    public function buildSettingsDiff(array $before, array $after, array $changedSections): array
    {
        $old = [];
        $new = ['section' => implode(', ', $changedSections)];

        foreach ($changedSections as $section) {
            if (isset($before[$section])) {
                $old[$section] = $before[$section];
            }

            if (isset($after[$section])) {
                $new[$section] = $after[$section];
            }
        }

        return ['old' => $old, 'new' => $new];
    }
}
