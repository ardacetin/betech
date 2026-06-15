<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\DatabaseService;
use JsonException;
use Medoo\Medoo;

class Setting
{
    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->db()->get('settings', 'value', [
            'key' => $key,
        ]);

        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    /**
     * @return mixed
     */
    public function getJson(string $key, mixed $default = []): mixed
    {
        $rawValue = $this->get($key);

        if ($rawValue === null) {
            return $default;
        }

        try {
            $decoded = json_decode($rawValue, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $default;
        }

        return $decoded;
    }

    public function set(string $key, string $value): void
    {
        $exists = $this->db()->has('settings', ['key' => $key]);

        if ($exists) {
            $this->db()->update('settings', [
                'value' => $value,
            ], [
                'key' => $key,
            ]);

            return;
        }

        $this->db()->insert('settings', [
            'key' => $key,
            'value' => $value,
        ]);
    }

    /**
     * @param mixed $value
     */
    public function setJson(string $key, mixed $value): void
    {
        $this->set($key, json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array{
     *     active_auth_driver: string,
     *     zimmet_template: string,
     *     custom_fields: list<array<string, mixed>>
     * }
     */
    public function getAdminBundle(): array
    {
        $customFields = $this->getJson('custom_fields', []);

        return [
            'active_auth_driver' => $this->get('active_auth_driver', 'local') ?? 'local',
            'zimmet_template' => $this->get('zimmet_template', '') ?? '',
            'custom_fields' => is_array($customFields) ? $customFields : [],
        ];
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
