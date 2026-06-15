<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\ServerRequestInterface;
use Medoo\Medoo;

class LoginAttemptService
{
    public const MAX_ATTEMPTS = 5;
    public const WINDOW_MINUTES = 15;

    public function __construct(
        private readonly DatabaseService $databaseService
    ) {
    }

    public function isRateLimited(string $ipAddress): bool
    {
        return $this->countRecentFailures($ipAddress) >= self::MAX_ATTEMPTS;
    }

    public function recordFailure(string $ipAddress): void
    {
        $normalizedIp = $this->normalizeIpAddress($ipAddress);

        if ($normalizedIp === '') {
            return;
        }

        $this->db()->insert('login_attempts', [
            'ip_address' => $normalizedIp,
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);

        $this->purgeExpiredAttempts($normalizedIp);
    }

    public function clearFailures(string $ipAddress): void
    {
        $normalizedIp = $this->normalizeIpAddress($ipAddress);

        if ($normalizedIp === '') {
            return;
        }

        $this->db()->delete('login_attempts', [
            'ip_address' => $normalizedIp,
        ]);
    }

    public function countRecentFailures(string $ipAddress): int
    {
        $normalizedIp = $this->normalizeIpAddress($ipAddress);

        if ($normalizedIp === '') {
            return 0;
        }

        return $this->db()->count('login_attempts', [
            'ip_address' => $normalizedIp,
            'attempted_at[>=]' => date('Y-m-d H:i:s', time() - (self::WINDOW_MINUTES * 60)),
        ]);
    }

    private function purgeExpiredAttempts(string $ipAddress): void
    {
        $this->db()->delete('login_attempts', [
            'ip_address' => $ipAddress,
            'attempted_at[<]' => date('Y-m-d H:i:s', time() - (self::WINDOW_MINUTES * 60)),
        ]);
    }

    public static function resolveClientIpFromRequest(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        $candidates = [
            $serverParams['HTTP_X_FORWARDED_FOR'] ?? null,
            $serverParams['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $first = trim(explode(',', $candidate)[0]);

            if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                return $first;
            }
        }

        return '';
    }

    private function normalizeIpAddress(string $ipAddress): string
    {
        $ipAddress = trim($ipAddress);

        if ($ipAddress === '') {
            return '';
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return '';
        }

        return $ipAddress;
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
