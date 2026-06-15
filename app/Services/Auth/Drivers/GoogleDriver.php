<?php

declare(strict_types=1);

namespace App\Services\Auth\Drivers;

use App\Services\Auth\UserIntegrationInterface;

class GoogleDriver implements UserIntegrationInterface
{
    public function searchUsers(string $query): array
    {
        // Placeholder: Google Workspace Directory API users.list / users.search query.
        return [];
    }

    public function getUserById(string $id): ?array
    {
        // Placeholder: Directory API users.get by primary key or external id.
        return null;
    }
}
