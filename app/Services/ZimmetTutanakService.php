<?php

declare(strict_types=1);

namespace App\Services;

class ZimmetTutanakService
{
    /**
     * @param array{
     *     personnel_name?: string,
     *     asset_name?: string,
     *     serial_number?: string,
     *     asset_tag?: string,
     *     category_name?: string,
     *     date?: string
     * } $data
     */
    public function renderTemplate(string $template, array $data): string
    {
        $replacements = [
            '{personnel_name}' => $this->escapeValue($data['personnel_name'] ?? ''),
            '{asset_name}' => $this->escapeValue($data['asset_name'] ?? ''),
            '{serial_number}' => $this->escapeValue($data['serial_number'] ?? ''),
            '{asset_tag}' => $this->escapeValue($data['asset_tag'] ?? ''),
            '{category_name}' => $this->escapeValue($data['category_name'] ?? ''),
            '{date}' => $this->escapeValue($data['date'] ?? date('d.m.Y')),
        ];

        $result = str_replace(array_keys($replacements), array_values($replacements), $template);

        if (!$this->isHtmlTemplate($template)) {
            return nl2br($result, false);
        }

        return $result;
    }

    private function escapeValue(mixed $value): string
    {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function isHtmlTemplate(string $template): bool
    {
        return (bool) preg_match('/<[^>]+>/', $template);
    }
}
