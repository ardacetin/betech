<?php

declare(strict_types=1);

namespace App\Services;

class DatabaseInitializationResult
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        private readonly bool $success,
        private readonly ?string $message = null,
        private readonly array $warnings = []
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

    /**
     * @return list<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
