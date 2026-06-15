<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class Translator
{
    public const DEFAULT_LOCALE = 'tr';

    /** @var list<string> */
    public const SUPPORTED_LOCALES = ['tr', 'en'];

    private static ?self $instance = null;

    /** @var array<string, string> */
    private array $lines = [];

    private string $locale = self::DEFAULT_LOCALE;

    public function __construct(
        private readonly string $langPath
    ) {
        $this->setLocale(self::DEFAULT_LOCALE);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new RuntimeException('Translator has not been initialized.');
        }

        return self::$instance;
    }

    public static function initialize(self $translator): void
    {
        self::$instance = $translator;
    }

    public function setLocale(string $locale): void
    {
        if (!in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = self::DEFAULT_LOCALE;
        }

        $this->locale = $locale;
        $this->loadLocale($locale);
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function translate(string $key, array $replace = []): string
    {
        $translation = $this->lines[$key] ?? $key;

        foreach ($replace as $search => $value) {
            $translation = str_replace(':' . $search, (string) $value, $translation);
        }

        return $translation;
    }

    public function currentPathWithLocale(string $locale): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $query = $_GET ?? [];
        $query['lang'] = $locale;

        return $path . '?' . http_build_query($query);
    }

    private function loadLocale(string $locale): void
    {
        $langFile = $this->langPath . '/' . $locale . '.php';

        if (!is_readable($langFile)) {
            throw new RuntimeException(sprintf('Language file not found: %s', $langFile));
        }

        $lines = require $langFile;

        if (!is_array($lines)) {
            throw new RuntimeException(sprintf('Language file must return an array: %s', $langFile));
        }

        $this->lines = $lines;
    }
}
