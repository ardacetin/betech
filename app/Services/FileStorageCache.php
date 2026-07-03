<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class FileStorageCache
{
    public const DEFAULT_TTL_SECONDS = 600;

    public function __construct(
        private readonly string $storageDirectory
    ) {
        $this->ensureStorageDirectory();
    }

    public function get(string $key): mixed
    {
        $path = $this->pathForKey($key);

        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);

        if ($raw === false || trim($raw) === '') {
            $this->delete($key);

            return null;
        }

        try {
            /** @var array{expires_at: int, value: mixed} $payload */
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->delete($key);

            return null;
        }

        if (!isset($payload['expires_at'], $payload['value']) || time() >= (int) $payload['expires_at']) {
            $this->delete($key);

            return null;
        }

        return $payload['value'];
    }

    public function set(string $key, mixed $value, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): void
    {
        $this->ensureStorageDirectory();

        $payload = json_encode([
            'expires_at' => time() + max(1, $ttlSeconds),
            'value' => $value,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $path = $this->pathForKey($key);
        $temporaryPath = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (file_put_contents($temporaryPath, $payload, LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Unable to write cache file: %s', $temporaryPath));
        }

        if (!rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new RuntimeException(sprintf('Unable to finalize cache file: %s', $path));
        }
    }

    public function delete(string $key): void
    {
        $path = $this->pathForKey($key);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function pathForKey(string $key): string
    {
        return $this->storageDirectory . '/' . hash('sha256', $key) . '.cache';
    }

    private function ensureStorageDirectory(): void
    {
        if (is_dir($this->storageDirectory)) {
            return;
        }

        if (!mkdir($this->storageDirectory, 0775, true) && !is_dir($this->storageDirectory)) {
            throw new RuntimeException(sprintf('Unable to create cache directory: %s', $this->storageDirectory));
        }
    }
}
