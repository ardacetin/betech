<?php

declare(strict_types=1);

namespace App\Services\Auth\Drivers;

use App\Services\Auth\UserIntegrationInterface;

class AzureDriver implements UserIntegrationInterface
{
    public function searchUsers(string $query): array
    {
        // Placeholder: Microsoft Graph GET /users?$search=...
        return [];
    }

    public function getUserById(string $id): ?array
    {
        // Placeholder: Microsoft Graph GET /users/{id}
        return null;
    }
}
