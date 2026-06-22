<?php

declare(strict_types=1);

namespace App\Services;

class DeferredTaskRunner
{
    /** @var list<callable(): void> */
    private static array $tasks = [];

    private static bool $shutdownRegistered = false;

    private static bool $hasRun = false;

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

        foreach ($tasks as $task) {
            try {
                $task();
            } catch (\Throwable) {
                // Individual task failures must not break other deferred work.
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
