<?php

declare(strict_types=1);

namespace App\Services;

class DeferredTaskRunner
{
    /** @var list<callable(): void> */
    private static array $tasks = [];

    private static bool $shutdownRegistered = false;

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
        if (self::$tasks === []) {
            return;
        }

        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        foreach (self::$tasks as $task) {
            try {
                $task();
            } catch (\Throwable) {
                // Individual task failures must not break other deferred work.
            }
        }

        self::$tasks = [];
    }
}
