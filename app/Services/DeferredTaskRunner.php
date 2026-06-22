<?php

declare(strict_types=1);

namespace App\Services;

class DeferredTaskRunner
{
    /** @var list<callable(): void> */
    private static array $tasks = [];

    private static bool $shutdownRegistered = false;

    private static bool $hasRun = false;

    private static ?AppLogger $logger = null;

    public static function setLogger(AppLogger $logger): void
    {
        self::$logger = $logger;
    }

    public static function defer(callable $task): void
    {
        self::$tasks[] = $task;

        if (!self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function([self::class, 'run']);
        }
    }

    public static function run(): void
    {
        if (self::$hasRun || self::$tasks === []) {
            return;
        }

        self::$hasRun = true;
        self::flushResponseToClient();

        $tasks = self::$tasks;
        self::$tasks = [];

        foreach ($tasks as $index => $task) {
            try {
                $task();
            } catch (\Throwable $exception) {
                if (self::$logger !== null) {
                    self::$logger->error('deferred_task.failed', [
                        'task_index' => $index,
                        'error' => $exception->getMessage(),
                        'exception_class' => $exception::class,
                        'trace' => $exception->getTraceAsString(),
                    ]);
                }
            }
        }
    }

    /**
     * Release the HTTP response and session lock before slow background work (SMTP, etc.).
     */
    private static function flushResponseToClient(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        ignore_user_abort(true);

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }

        @flush();

        if (function_exists('litespeed_finish_request')) {
            @litespeed_finish_request();
        } elseif (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
    }
}
