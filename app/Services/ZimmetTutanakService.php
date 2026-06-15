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
     *     date?: string
     * } $data
     */
    public function renderTemplate(string $template, array $data): string
    {
        $replacements = [
            '{personnel_name}' => trim((string) ($data['personnel_name'] ?? '')),
            '{asset_name}' => trim((string) ($data['asset_name'] ?? '')),
            '{serial_number}' => trim((string) ($data['serial_number'] ?? '')),
            '{date}' => trim((string) ($data['date'] ?? date('d.m.Y'))),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
