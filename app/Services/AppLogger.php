<?php

declare(strict_types=1);

namespace App\Services;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class AppLogger
{
    private const RETENTION_DAYS = 365;

    private readonly LoggerInterface $logger;

    public function __construct(
        string $logDirectory,
        string $logFilename,
        private readonly ClientIpResolver $clientIpResolver
    ) {
        if (!is_dir($logDirectory) && !mkdir($logDirectory, 0755, true) && !is_dir($logDirectory)) {
            throw new \RuntimeException('Unable to create log directory: ' . $logDirectory);
        }

        $handler = new RotatingFileHandler(
            rtrim($logDirectory, '/') . '/' . ltrim($logFilename, '/'),
            self::RETENTION_DAYS,
            Level::Debug,
            true,
            null,
            true
        );

        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s',
            true,
            true
        );
        $handler->setFormatter($formatter);

        $this->logger = new Logger('betech', [$handler]);
    }

    public function registerGlobalHandlers(): void
    {
        set_exception_handler(function (Throwable $exception): void {
            $this->logUncaughtThrowable($exception);
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();

            if ($error === null) {
                return;
            }

            if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            $this->logFatalError($error);
        });
    }

    public function logAuthSuccess(
        string $identifier,
        string $clientIp,
        string $method,
        ?int $userId = null,
        ?string $email = null
    ): void {
        $this->logger->info('Authentication successful', [
            'identifier' => $identifier,
            'email' => $email !== null && $email !== '' ? $email : $identifier,
            'user_id' => $userId,
            'client_ip' => $clientIp,
            'method' => $method,
        ]);
    }

    public function logAuthFailure(
        string $identifier,
        string $clientIp,
        string $reason,
        string $method = ''
    ): void {
        $context = [
            'identifier' => $identifier !== '' ? $identifier : '[unknown]',
            'client_ip' => $clientIp,
            'reason' => $reason,
        ];

        if ($method !== '') {
            $context['method'] = $method;
        }

        $this->logger->warning('Authentication failed', $context);
    }

    public function logException(ServerRequestInterface $request, Throwable $exception): void
    {
        $this->logger->error(
            sprintf(
                'Unhandled exception: %s in %s:%d',
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ),
            [
                'client_ip' => $this->clientIpResolver->resolveFromRequest($request),
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
                'query' => $request->getUri()->getQuery(),
                'exception_class' => $exception::class,
                'trace' => $exception->getTraceAsString(),
                'request_payload' => $this->extractRequestPayload($request),
            ]
        );
    }

    public function logUncaughtThrowable(Throwable $exception): void
    {
        $this->logger->critical(
            sprintf(
                'Uncaught exception: %s in %s:%d',
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ),
            [
                'exception_class' => $exception::class,
                'trace' => $exception->getTraceAsString(),
            ]
        );
    }

    /**
     * @param array{type: int, message: string, file: string, line: int} $error
     */
    public function logFatalError(array $error): void
    {
        $this->logger->critical(
            sprintf(
                'Fatal error: %s in %s:%d',
                $error['message'],
                $error['file'],
                $error['line']
            ),
            [
                'error_type' => $error['type'],
            ]
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
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
}
