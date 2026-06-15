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
