<?php

declare(strict_types=1);

namespace App\Services;

class DatabaseInitializationResult
{
    public function __construct(
        private readonly bool $success,
        private readonly ?string $message = null
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }
}
