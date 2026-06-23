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
        return $this->userModel->isEndUserRole($this->sessionAuthService->role());
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
     * Resolve a legacy users.id for FK columns that still reference the users table.
     * Session auth stores personnel.id; returns null when no matching users row exists.
     */
    public function resolveLegacyUserId(): ?int
    {
        $sessionUserId = $this->sessionAuthService->userId();

        if ($sessionUserId === null || $sessionUserId <= 0) {
            return null;
        }

        if ($this->userModel->findById($sessionUserId) !== null) {
            return $sessionUserId;
        }

        $person = $this->personnelModel->findById($sessionUserId);

        if ($person === null) {
            return null;
        }

        $user = $this->userModel->findByEmail((string) ($person['email'] ?? ''));

        if ($user === null) {
            return null;
        }

        return (int) $user['id'];
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

        $assignedTo = trim((string) ($asset['assigned_to'] ?? ''));

        if ($assignedTo === '') {
            return false;
        }

        $person = $this->personnelModel->findById($personnelId);

        if ($person === null) {
            return false;
        }

        $email = trim((string) ($person['email'] ?? ''));
        $name = trim((string) ($person['name'] ?? ''));

        return ($email !== '' && strcasecmp($assignedTo, $email) === 0)
            || ($name !== '' && strcasecmp($assignedTo, $name) === 0);
    }
}
