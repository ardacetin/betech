<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class AppLogger
{
    public function __construct(
        private readonly string $logFilePath,
        private readonly ClientIpResolver $clientIpResolver
    ) {
    }

    public function logException(ServerRequestInterface $request, Throwable $exception): void
    {
        $entry = [
            'timestamp' => date('c'),
            'client_ip' => $this->clientIpResolver->resolveFromRequest($request),
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'query' => $request->getUri()->getQuery(),
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'request_payload' => $this->extractRequestPayload($request),
        ];

        $this->write(json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}');
    }

    public function log(string $message, array $context = []): void
    {
        $entry = [
            'timestamp' => date('c'),
            'message' => $message,
            'context' => $context,
        ];

        $this->write(json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}');
    }

    /**
     * @return array<string, mixed>|string|null
     */
    private function extractRequestPayload(ServerRequestInterface $request): array|string|null
    {
        $parsedBody = $request->getParsedBody();

        if (is_array($parsedBody)) {
            return $this->redactSensitiveValues($parsedBody);
        }

        if (is_string($parsedBody) && $parsedBody !== '') {
            return $parsedBody;
        }

        $rawBody = (string) $request->getBody();

        if ($rawBody !== '') {
            return $rawBody;
        }

        return $request->getQueryParams() !== [] ? $request->getQueryParams() : null;
    }

    /**
     * @param array<string|int, mixed> $payload
     *
     * @return array<string|int, mixed>
     */
    private function redactSensitiveValues(array $payload): array
    {
        $redacted = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && preg_match('/password|secret|token|authorization/i', $key) === 1) {
                $redacted[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                /** @var array<string|int, mixed> $value */
                $redacted[$key] = $this->redactSensitiveValues($value);
                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

    private function write(string $line): void
    {
        $directory = dirname($this->logFilePath);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            error_log('[Betech] Failed to create log directory: ' . $directory);

            return;
        }

        file_put_contents($this->logFilePath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
