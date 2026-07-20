<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use JsonException;
use Medoo\Medoo;

class AutomationRule
{
    public const TYPE_LICENSE_EXPIRING = 'license_expiring';
    public const TYPE_WARRANTY_EXPIRING = 'warranty_expiring';
    public const TYPE_CONSUMABLE_LOW_STOCK = 'consumable_low_stock';
    public const TYPE_TICKET_PRIORITY = 'ticket_priority';

    public const RECIPIENT_ADMINS = 'admins';
    public const RECIPIENT_SUPPORT = 'support';
    public const RECIPIENT_CUSTOM = 'custom';

    /** @var list<string> */
    public const RULE_TYPES = [
        self::TYPE_LICENSE_EXPIRING,
        self::TYPE_WARRANTY_EXPIRING,
        self::TYPE_CONSUMABLE_LOW_STOCK,
        self::TYPE_TICKET_PRIORITY,
    ];

    /** @var list<string> */
    public const SCHEDULED_TYPES = [
        self::TYPE_LICENSE_EXPIRING,
        self::TYPE_WARRANTY_EXPIRING,
        self::TYPE_CONSUMABLE_LOW_STOCK,
    ];

    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    public function tableReady(): bool
    {
        try {
            $this->db()->query('SELECT 1 FROM automation_rules LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        if (!$this->tableReady()) {
            return [];
        }

        $rows = $this->db()->select('automation_rules', '*', [
            'ORDER' => ['id' => 'ASC'],
        ]);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(fn (array $row): array => $this->normalize($row), $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findEnabledByTypes(array $types): array
    {
        if (!$this->tableReady() || $types === []) {
            return [];
        }

        $rows = $this->db()->select('automation_rules', '*', [
            'is_enabled' => 1,
            'rule_type' => array_values($types),
            'ORDER' => ['id' => 'ASC'],
        ]);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(fn (array $row): array => $this->normalize($row), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0 || !$this->tableReady()) {
            return null;
        }

        $row = $this->db()->get('automation_rules', '*', ['id' => $id]);

        return is_array($row) ? $this->normalize($row) : null;
    }

    /**
     * @param array{
     *     name: string,
     *     rule_type: string,
     *     is_enabled?: bool|int,
     *     config?: array<string, mixed>,
     *     recipient_mode?: string,
     *     custom_recipients?: string|null
     * } $payload
     *
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        if (!$this->tableReady()) {
            throw new \RuntimeException(__('automation_unavailable'));
        }

        $normalized = $this->normalizeWritablePayload($payload, true);

        $this->db()->insert('automation_rules', [
            'name' => $normalized['name'],
            'rule_type' => $normalized['rule_type'],
            'is_enabled' => $normalized['is_enabled'] ? 1 : 0,
            'config' => json_encode($normalized['config'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'recipient_mode' => $normalized['recipient_mode'],
            'custom_recipients' => $normalized['custom_recipients'],
        ]);

        $created = $this->findById((int) $this->db()->id());

        if ($created === null) {
            throw new \RuntimeException(__('automation_rule_create_error'));
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

        $normalized = $this->normalizeWritablePayload(array_merge($existing, $payload), false);

        $this->db()->update('automation_rules', [
            'name' => $normalized['name'],
            'rule_type' => $normalized['rule_type'],
            'is_enabled' => $normalized['is_enabled'] ? 1 : 0,
            'config' => json_encode($normalized['config'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'recipient_mode' => $normalized['recipient_mode'],
            'custom_recipients' => $normalized['custom_recipients'],
        ], ['id' => $id]);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        if ($this->findById($id) === null) {
            return false;
        }

        $this->db()->delete('automation_rules', ['id' => $id]);

        return $this->findById($id) === null;
    }

    public function markRun(int $ruleId): void
    {
        if (!$this->tableReady()) {
            return;
        }

        $this->db()->update('automation_rules', [
            'last_run_at' => date('Y-m-d H:i:s'),
        ], ['id' => $ruleId]);
    }

    public function hasFired(int $ruleId, string $dedupeKey): bool
    {
        if (!$this->tableReady() || !$this->firingsTableReady()) {
            return false;
        }

        return $this->db()->has('automation_rule_firings', [
            'rule_id' => $ruleId,
            'dedupe_key' => $dedupeKey,
        ]);
    }

    public function recordFiring(
        int $ruleId,
        string $dedupeKey,
        string $entityType,
        int $entityId,
        ?string $subject = null
    ): void {
        if (!$this->firingsTableReady()) {
            return;
        }

        if ($this->hasFired($ruleId, $dedupeKey)) {
            return;
        }

        try {
            $this->db()->insert('automation_rule_firings', [
                'rule_id' => $ruleId,
                'dedupe_key' => $dedupeKey,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'subject' => $subject,
            ]);
        } catch (\Throwable) {
            // Unique race: another worker already recorded the same firing.
        }
    }

    public function seedDefaultsIfEmpty(): void
    {
        if (!$this->tableReady()) {
            return;
        }

        if ((int) $this->db()->count('automation_rules') > 0) {
            return;
        }

        $defaults = [
            [
                'name' => 'Lisans bitimine 30 gün kala',
                'rule_type' => self::TYPE_LICENSE_EXPIRING,
                'config' => ['days' => 30],
            ],
            [
                'name' => 'Garanti bitimine 60 gün kala',
                'rule_type' => self::TYPE_WARRANTY_EXPIRING,
                'config' => ['days' => 60],
            ],
            [
                'name' => 'Sarf stoğu minimumun altında',
                'rule_type' => self::TYPE_CONSUMABLE_LOW_STOCK,
                'config' => [],
            ],
            [
                'name' => 'Kritik talep açıldığında',
                'rule_type' => self::TYPE_TICKET_PRIORITY,
                'config' => ['priority' => Ticket::PRIORITY_CRITICAL],
            ],
        ];

        foreach ($defaults as $default) {
            $this->create([
                'name' => $default['name'],
                'rule_type' => $default['rule_type'],
                'is_enabled' => true,
                'config' => $default['config'],
                'recipient_mode' => $default['rule_type'] === self::TYPE_TICKET_PRIORITY
                    ? self::RECIPIENT_SUPPORT
                    : self::RECIPIENT_ADMINS,
                'custom_recipients' => null,
            ]);
        }
    }

    private function firingsTableReady(): bool
    {
        try {
            $this->db()->query('SELECT 1 FROM automation_rule_firings LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{
     *     name: string,
     *     rule_type: string,
     *     is_enabled: bool,
     *     config: array<string, mixed>,
     *     recipient_mode: string,
     *     custom_recipients: string|null
     * }
     */
    private function normalizeWritablePayload(array $payload, bool $isCreate): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $ruleType = strtolower(trim((string) ($payload['rule_type'] ?? '')));

        if ($name === '') {
            throw new \InvalidArgumentException(__('automation_rule_name_required'));
        }

        if (!in_array($ruleType, self::RULE_TYPES, true)) {
            throw new \InvalidArgumentException(__('automation_rule_type_invalid'));
        }

        $recipientMode = strtolower(trim((string) ($payload['recipient_mode'] ?? self::RECIPIENT_ADMINS)));

        if (!in_array($recipientMode, [self::RECIPIENT_ADMINS, self::RECIPIENT_SUPPORT, self::RECIPIENT_CUSTOM], true)) {
            $recipientMode = self::RECIPIENT_ADMINS;
        }

        $config = is_array($payload['config'] ?? null) ? $payload['config'] : [];
        $config = $this->normalizeConfigForType($ruleType, $config);

        $customRecipients = null;

        if ($recipientMode === self::RECIPIENT_CUSTOM) {
            $customRecipients = $this->normalizeRecipientList((string) ($payload['custom_recipients'] ?? ''));

            if ($customRecipients === null) {
                throw new \InvalidArgumentException(__('automation_rule_recipients_required'));
            }
        }

        return [
            'name' => $name,
            'rule_type' => $ruleType,
            'is_enabled' => !array_key_exists('is_enabled', $payload) || (bool) $payload['is_enabled'],
            'config' => $config,
            'recipient_mode' => $recipientMode,
            'custom_recipients' => $customRecipients,
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function normalizeConfigForType(string $ruleType, array $config): array
    {
        return match ($ruleType) {
            self::TYPE_LICENSE_EXPIRING, self::TYPE_WARRANTY_EXPIRING => [
                'days' => max(1, min(365, (int) ($config['days'] ?? ($ruleType === self::TYPE_WARRANTY_EXPIRING ? 60 : 30)))),
            ],
            self::TYPE_TICKET_PRIORITY => [
                'priority' => in_array(
                    strtolower(trim((string) ($config['priority'] ?? Ticket::PRIORITY_CRITICAL))),
                    [Ticket::PRIORITY_LOW, Ticket::PRIORITY_MEDIUM, Ticket::PRIORITY_HIGH, Ticket::PRIORITY_CRITICAL],
                    true
                )
                    ? strtolower(trim((string) ($config['priority'] ?? Ticket::PRIORITY_CRITICAL)))
                    : Ticket::PRIORITY_CRITICAL,
            ],
            default => [],
        };
    }

    private function normalizeRecipientList(string $raw): ?string
    {
        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        $emails = [];

        foreach ($parts as $part) {
            $email = strtolower(trim($part));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $emails[$email] = true;
            }
        }

        if ($emails === []) {
            return null;
        }

        return implode(', ', array_keys($emails));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        $config = [];

        try {
            $decoded = json_decode((string) ($row['config'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
            $config = is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            $config = [];
        }

        $ruleType = (string) ($row['rule_type'] ?? '');
        $config = $this->normalizeConfigForType($ruleType, $config);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'rule_type' => $ruleType,
            'is_enabled' => (bool) ((int) ($row['is_enabled'] ?? 0)),
            'config' => $config,
            'recipient_mode' => (string) ($row['recipient_mode'] ?? self::RECIPIENT_ADMINS),
            'custom_recipients' => trim((string) ($row['custom_recipients'] ?? '')) ?: null,
            'last_run_at' => $row['last_run_at'] ?? null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
