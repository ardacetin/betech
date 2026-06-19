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

/**
 * Build a stable custom field code from a human-readable label.
 */
function custom_field_code_from_label(string $label): string
{
    $map = [
        'ç' => 'c',
        'Ç' => 'c',
        'ğ' => 'g',
        'Ğ' => 'g',
        'ı' => 'i',
        'I' => 'i',
        'İ' => 'i',
        'ö' => 'o',
        'Ö' => 'o',
        'ş' => 's',
        'Ş' => 's',
        'ü' => 'u',
        'Ü' => 'u',
    ];

    $normalized = strtr(trim($label), $map);
    $normalized = mb_strtolower($normalized, 'UTF-8');
    $name = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
    $name = trim($name, '_');

    if ($name === '') {
        return 'field';
    }

    if (preg_match('/^[0-9]/', $name)) {
        $name = 'field_' . $name;
    }

    return $name;
}
