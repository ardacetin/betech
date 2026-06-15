<?php

declare(strict_types=1);

namespace App\Services;

use Medoo\Medoo;

class DatabaseService
{
    private ?Medoo $connection = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config
    ) {
    }

    public function getConnection(): Medoo
    {
        if ($this->connection === null) {
            $this->connection = new Medoo($this->config);
        }

        return $this->connection;
    }
}
