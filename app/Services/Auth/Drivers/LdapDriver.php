<?php

declare(strict_types=1);

namespace App\Services\Auth\Drivers;

use App\Services\Auth\UserIntegrationInterface;

class LdapDriver implements UserIntegrationInterface
{
    public function searchUsers(string $query): array
    {
        // Placeholder: connect via ldap_connect(), bind, and ldap_search() against directory base DN.
        return [];
    }

    public function getUserById(string $id): ?array
    {
        // Placeholder: resolve LDAP entry by distinguished name or uid attribute.
        return null;
    }
}
