<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\License;

class LicenseFilterSchemaService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function buildDefinitions(): array
    {
        return [
            [
                'name' => 'name',
                'label_key' => 'col_license_name',
                'type' => 'text',
                'input' => 'text',
                'match' => 'partial',
                'column' => 'licenses.name',
            ],
            [
                'name' => 'vendor',
                'label_key' => 'col_license_vendor',
                'type' => 'select',
                'input' => 'select',
                'match' => 'exact',
                'column' => 'licenses.vendor',
                'options_source' => 'distinct_vendor',
                'options' => [],
            ],
            [
                'name' => 'license_key',
                'label_key' => 'label_license_key',
                'type' => 'text',
                'input' => 'text',
                'match' => 'partial',
                'column' => 'licenses.license_key',
            ],
            [
                'name' => 'notes',
                'label_key' => 'label_license_notes',
                'type' => 'textarea',
                'input' => 'text',
                'match' => 'partial',
                'column' => 'licenses.notes',
            ],
            [
                'name' => 'expiration_status',
                'label_key' => 'license_filter_expiration_status',
                'type' => 'select',
                'input' => 'select',
                'match' => 'exact',
                'virtual' => 'expiration_status',
                'options' => [
                    ['value' => 'active', 'label_key' => 'license_filter_expiration_active'],
                    ['value' => 'expiring_soon', 'label_key' => 'license_filter_expiration_expiring_soon'],
                    ['value' => 'expired', 'label_key' => 'license_filter_expiration_expired'],
                    ['value' => 'no_date', 'label_key' => 'license_filter_expiration_no_date'],
                ],
            ],
            [
                'name' => 'seat_availability',
                'label_key' => 'license_filter_seat_availability',
                'type' => 'select',
                'input' => 'select',
                'match' => 'exact',
                'virtual' => 'seat_availability',
                'options' => [
                    ['value' => 'available', 'label_key' => 'license_filter_seats_available'],
                    ['value' => 'full', 'label_key' => 'license_filter_seats_full'],
                ],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $definitions
     *
     * @return list<array<string, mixed>>
     */
    public function resolveOptions(array $definitions, License $licenseModel): array
    {
        return array_map(function (array $definition) use ($licenseModel): array {
            if (($definition['options_source'] ?? '') === 'distinct_vendor') {
                $definition['options'] = array_map(
                    static fn (string $vendor): array => [
                        'value' => $vendor,
                        'label' => $vendor,
                    ],
                    $licenseModel->getDistinctVendors()
                );
            }

            if (isset($definition['options']) && is_array($definition['options'])) {
                $definition['options'] = $this->normalizeStaticOptions($definition['options']);
            }

            return $definition;
        }, $definitions);
    }

    /**
     * @param array<string, mixed> $queryParams
     *
     * @return array<string, string>
     */
    public function parseRequestFilters(array $queryParams): array
    {
        return ListFilterParser::parse($queryParams);
    }

    /**
     * @param list<string|array{value?: string, label?: string, label_key?: string}> $options
     *
     * @return list<array{value: string, label?: string, label_key?: string}>
     */
    private function normalizeStaticOptions(array $options): array
    {
        $normalized = [];

        foreach ($options as $option) {
            if (is_string($option)) {
                $trimmed = trim($option);

                if ($trimmed !== '') {
                    $normalized[] = ['value' => $trimmed, 'label' => $trimmed];
                }

                continue;
            }

            if (!is_array($option)) {
                continue;
            }

            $value = trim((string) ($option['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            $normalized[] = [
                'value' => $value,
                'label' => isset($option['label']) ? (string) $option['label'] : $value,
                'label_key' => isset($option['label_key']) ? (string) $option['label_key'] : null,
            ];
        }

        return $normalized;
    }
}
