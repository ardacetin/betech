<?php

declare(strict_types=1);

namespace App\Services;

class AuditChangeFormatter
{
    private const HIDDEN_VALUE = '[hidden]';

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function formatSummary(
        string $actionType,
        string $entityType,
        ?int $entityId,
        ?array $oldValues,
        ?array $newValues
    ): string {
        $oldValues = $this->maskValues($oldValues ?? []);
        $newValues = $this->maskValues($newValues ?? []);

        return match ($actionType) {
            'login' => $this->formatLogin($newValues),
            'assigned' => $this->formatAssetAssigned($oldValues, $newValues),
            'returned' => $this->formatAssetReturned($oldValues, $newValues),
            'transferred' => $this->formatAssetTransferred($oldValues, $newValues),
            'created' => $this->formatCreated($entityType, $entityId, $newValues),
            'deleted' => $this->formatDeleted($entityType, $entityId, $oldValues),
            default => $this->formatUpdated($entityType, $entityId, $oldValues, $newValues),
        };
    }

    /**
     * @param array<string, mixed>|null $values
     *
     * @return array<string, mixed>
     */
    public function maskValues(?array $values): array
    {
        if ($values === null) {
            return [];
        }

        $masked = [];

        foreach ($values as $key => $value) {
            $masked[(string) $key] = $this->maskValue((string) $key, $value);
        }

        return $masked;
    }

    /**
     * @param array<string, mixed> $newValues
     */
    private function formatLogin(array $newValues): string
    {
        $email = (string) ($newValues['email'] ?? '');

        return __('audit_summary_login', ['email' => $email !== '' ? $email : '—']);
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    private function formatAssetAssigned(array $oldValues, array $newValues): string
    {
        $assetTag = (string) ($newValues['asset_tag'] ?? $oldValues['asset_tag'] ?? '—');
        $personnelName = (string) ($newValues['personnel_name'] ?? $newValues['personnel_id'] ?? '—');

        return __('audit_summary_asset_assigned', [
            'asset_tag' => $assetTag,
            'personnel' => $personnelName,
        ]);
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    private function formatAssetReturned(array $oldValues, array $newValues): string
    {
        $assetTag = (string) ($newValues['asset_tag'] ?? $oldValues['asset_tag'] ?? '—');

        return __('audit_summary_asset_returned', ['asset_tag' => $assetTag]);
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    private function formatAssetTransferred(array $oldValues, array $newValues): string
    {
        $assetTag = (string) ($newValues['asset_tag'] ?? $oldValues['asset_tag'] ?? '—');
        $from = (string) ($oldValues['personnel_name'] ?? $oldValues['personnel_id'] ?? '—');
        $to = (string) ($newValues['personnel_name'] ?? $newValues['personnel_id'] ?? '—');

        return __('audit_summary_asset_transferred', [
            'asset_tag' => $assetTag,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * @param array<string, mixed> $newValues
     */
    private function formatCreated(string $entityType, ?int $entityId, array $newValues): string
    {
        $label = $this->entityLabel($entityType, $entityId, $newValues);

        return __('audit_summary_created', [
            'entity' => $this->entityTypeLabel($entityType),
            'label' => $label,
        ]);
    }

    /**
     * @param array<string, mixed> $oldValues
     */
    private function formatDeleted(string $entityType, ?int $entityId, array $oldValues): string
    {
        $label = $this->entityLabel($entityType, $entityId, $oldValues);

        return __('audit_summary_deleted', [
            'entity' => $this->entityTypeLabel($entityType),
            'label' => $label,
        ]);
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    private function formatUpdated(string $entityType, ?int $entityId, array $oldValues, array $newValues): string
    {
        $label = $this->entityLabel($entityType, $entityId, $newValues !== [] ? $newValues : $oldValues);
        $changes = [];

        foreach ($newValues as $field => $newValue) {
            $oldValue = $oldValues[$field] ?? null;

            if ($this->valuesEqual($oldValue, $newValue)) {
                continue;
            }

            $changes[] = __('audit_summary_field_changed', [
                'field' => $this->fieldLabel((string) $field),
                'old' => $this->displayValue($field, $oldValue),
                'new' => $this->displayValue($field, $newValue),
            ]);
        }

        if ($changes === []) {
            return __('audit_summary_updated_generic', [
                'entity' => $this->entityTypeLabel($entityType),
                'label' => $label,
            ]);
        }

        return __('audit_summary_updated', [
            'entity' => $this->entityTypeLabel($entityType),
            'label' => $label,
            'changes' => implode('; ', $changes),
        ]);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function entityLabel(string $entityType, ?int $entityId, array $values): string
    {
        return match ($entityType) {
            'asset' => (string) ($values['asset_tag'] ?? $values['name'] ?? ('#' . ($entityId ?? ''))),
            'ticket' => (string) ($values['ticket_number'] ?? $values['subject'] ?? ('#' . ($entityId ?? ''))),
            'category' => (string) ($values['name'] ?? ('#' . ($entityId ?? ''))),
            'setting' => (string) ($values['section'] ?? __('audit_entity_setting')),
            'user' => (string) ($values['email'] ?? $values['name'] ?? ('#' . ($entityId ?? ''))),
            'ip_address' => (string) ($values['ip_address'] ?? ('#' . ($entityId ?? ''))),
            default => (string) ($entityId ?? '—'),
        };
    }

    private function entityTypeLabel(string $entityType): string
    {
        return match ($entityType) {
            'asset' => __('audit_entity_asset'),
            'ticket' => __('audit_entity_ticket'),
            'category' => __('audit_entity_category'),
            'setting' => __('audit_entity_setting'),
            'user' => __('audit_entity_user'),
            'ip_address' => __('audit_entity_ip_address'),
            default => $entityType,
        };
    }

    private function fieldLabel(string $field): string
    {
        $key = 'audit_field_' . $field;

        return __($key) !== $key ? __($key) : str_replace('_', ' ', $field);
    }

    private function displayValue(string|int $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($field === 'status') {
            $statusKey = 'status_' . (string) $value;
            $label = __($statusKey);

            return $label !== $statusKey ? $label : (string) $value;
        }

        if ($field === 'priority') {
            $priorityKey = 'ticket_priority_' . (string) $value;
            $label = __($priorityKey);

            return $label !== $priorityKey ? $label : (string) $value;
        }

        if (is_bool($value)) {
            return $value ? __('yes') : __('no');
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '—';
        }

        return (string) $value;
    }

    private function maskValue(string $key, mixed $value): mixed
    {
        if ($this->isSensitiveKey($key)) {
            return $value === null || $value === '' ? null : self::HIDDEN_VALUE;
        }

        if (is_array($value)) {
            $masked = [];

            foreach ($value as $childKey => $childValue) {
                $masked[$childKey] = $this->maskValue((string) $childKey, $childValue);
            }

            return $masked;
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (['pass', 'password', 'secret', 'token', 'private_key', 'service_account', 'oauth'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function valuesEqual(mixed $left, mixed $right): bool
    {
        if (is_array($left) || is_array($right)) {
            return json_encode($left) === json_encode($right);
        }

        return (string) $left === (string) $right;
    }
}
