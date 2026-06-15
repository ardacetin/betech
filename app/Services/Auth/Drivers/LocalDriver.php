<?php

declare(strict_types=1);

namespace App\Services\Auth\Drivers;

use App\Services\Auth\UserIntegrationInterface;
use App\Services\DatabaseService;
use Medoo\Medoo;

class LocalDriver implements UserIntegrationInterface
{
    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    public function searchUsers(string $query): array
    {
        $conditions = [
            'ORDER' => ['name' => 'ASC'],
            'LIMIT' => 20,
            'status' => 'active',
        ];

        $trimmedQuery = trim($query);

        if ($trimmedQuery !== '') {
            $conditions['OR'] = [
                'name[~]' => $trimmedQuery,
                'email[~]' => $trimmedQuery,
                'department[~]' => $trimmedQuery,
                'external_id[~]' => $trimmedQuery,
            ];
        }

        $rows = $this->db()->select('users', [
            'id',
            'external_id',
            'name',
            'email',
            'department',
        ], $conditions);

        return array_map(
            fn (array $row): array => $this->formatUser($row),
            $rows
        );
    }

    public function getUserById(string $id): ?array
    {
        $row = null;

        if (ctype_digit($id)) {
            $row = $this->db()->get('users', [
                'id',
                'external_id',
                'name',
                'email',
                'department',
                'status',
            ], [
                'id' => (int) $id,
            ]);
        }

        if ($row === null) {
            $row = $this->db()->get('users', [
                'id',
                'external_id',
                'name',
                'email',
                'department',
                'status',
            ], [
                'external_id' => $id,
            ]);
        }

        return $row === null ? null : $this->formatUser($row);
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: string, external_id: string, name: string, email: string, department: string|null}
     */
    private function formatUser(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'external_id' => (string) $row['external_id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'department' => isset($row['department']) && $row['department'] !== null
                ? (string) $row['department']
                : null,
        ];
    }
}
