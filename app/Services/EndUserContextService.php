<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Personnel;
use App\Models\User;
use App\Services\Auth\SessionAuthService;

class EndUserContextService
{
    public function __construct(
        private readonly SessionAuthService $sessionAuthService,
        private readonly User $userModel,
        private readonly Personnel $personnelModel
    ) {
    }

    public function isEndUser(): bool
    {
        return $this->sessionAuthService->role() === User::ROLE_END_USER;
    }

    public function resolvePersonnelId(): ?int
    {
        $sessionUserId = $this->sessionAuthService->userId();

        if ($sessionUserId === null || $sessionUserId <= 0) {
            return null;
        }

        $person = $this->personnelModel->findById($sessionUserId);

        if ($person !== null) {
            return (int) $person['id'];
        }

        $user = $this->userModel->findById($sessionUserId);

        if ($user === null) {
            return null;
        }

        $personByEmail = $this->personnelModel->findByEmail((string) ($user['email'] ?? ''));

        if ($personByEmail === null) {
            return null;
        }

        return (int) $personByEmail['id'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolvePersonnel(): ?array
    {
        $personnelId = $this->resolvePersonnelId();

        if ($personnelId === null) {
            return null;
        }

        return $this->personnelModel->findById($personnelId);
    }

    /**
     * @param array<string, mixed> $asset
     */
    public function ownsAsset(array $asset): bool
    {
        $personnelId = $this->resolvePersonnelId();

        if ($personnelId === null) {
            return false;
        }

        $assignedPersonnelId = $asset['personnel_id'] ?? null;

        return $assignedPersonnelId !== null && (int) $assignedPersonnelId === $personnelId;
    }
}
