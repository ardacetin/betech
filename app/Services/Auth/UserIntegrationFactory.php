<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Setting;
use App\Services\Auth\Drivers\AzureDriver;
use App\Services\Auth\Drivers\GoogleDriver;
use App\Services\Auth\Drivers\LdapDriver;
use App\Services\Auth\Drivers\LocalDriver;
use App\Services\DatabaseService;
use InvalidArgumentException;

class UserIntegrationFactory
{
    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly ?Setting $settingModel = null
    ) {
    }

    public function make(?string $driver = null): UserIntegrationInterface
    {
        if ($driver === null && $this->settingModel !== null) {
            $driver = $this->settingModel->get('active_auth_driver');
        }

        $driverName = strtolower(trim($driver ?? $_ENV['AUTH_DRIVER'] ?? 'local'));

        return match ($driverName) {
            'local' => new LocalDriver($this->databaseService),
            'ldap' => new LdapDriver(),
            'google' => new GoogleDriver(),
            'azure' => new AzureDriver(),
            default => throw new InvalidArgumentException(
                sprintf('Unsupported AUTH_DRIVER value: %s', $driverName)
            ),
        };
    }
}
