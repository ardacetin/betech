<?php

declare(strict_types=1);

use App\Services\Translator;

/**
 * Fetch a translated UI string for the active locale.
 *
 * @param array<string, string|int|float> $replace
 */
function __(string $key, array $replace = []): string
{
    return Translator::instance()->translate($key, $replace);
}

/**
 * Build a URL for the current path with a locale query parameter.
 */
function lang_url(string $locale): string
{
    return Translator::instance()->currentPathWithLocale($locale);
}

/**
 * Detect HTTPS for session cookies and security headers (Cloudflare / reverse proxy aware).
 */
function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443') {
        return true;
    }

    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));

    if ($forwardedProto === 'https') {
        return true;
    }

    $cfVisitor = trim((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''));

    if ($cfVisitor !== '') {
        $decoded = json_decode($cfVisitor, true);

        if (is_array($decoded) && strtolower((string) ($decoded['scheme'] ?? '')) === 'https') {
            return true;
        }
    }

    return false;
}
