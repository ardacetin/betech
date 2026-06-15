<?php

declare(strict_types=1);

namespace App\Services\Auth;

interface UserIntegrationInterface
{
    /**
     * Search users by name, email, department, or external identifier.
     *
     * @return list<array{id: string, external_id: string, name: string, email: string, department: string|null}>
     */
    public function searchUsers(string $query): array;

    /**
     * Resolve a single user record by integration identifier.
     *
     * @return array{id: string, external_id: string, name: string, email: string, department: string|null}|null
     */
    public function getUserById(string $id): ?array;
}
