<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Consumable;

class ConsumableFilterSchemaService
{
    /**
     * @param list<array<string, mixed>> $locations
     *
     * @return list<array<string, mixed>>
     */
    public function buildDefinitions(array $locations): array
    {
        return [
            [
                'name' => 'name',
                'label_key' => 'col_consumable_name',
                'type' => 'text',
                'input' => 'text',
                'match' => 'partial',
                'column' => 'consumables.name',
            ],
            [
                'name' => 'location_id',
                'label_key' => 'col_consumable_location',
                'type' => 'select',
                'input' => 'select',
                'match' => 'exact',
                'column' => 'consumables.location_id',
                'options_source' => 'locations',
                'options' => [],
            ],
            [
                'name' => 'stock_status',
                'label_key' => 'consumable_filter_stock_status',
                'type' => 'select',
                'input' => 'select',
                'match' => 'exact',
                'virtual' => 'stock_status',
                'options' => [
                    ['value' => 'in_stock', 'label_key' => 'consumable_filter_in_stock'],
                    ['value' => 'low_stock', 'label_key' => 'consumable_filter_low_stock'],
                ],
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $definitions
     * @param list<array<string, mixed>> $locations
     *
     * @return list<array<string, mixed>>
     */
    public function resolveOptions(array $definitions, Consumable $consumableModel, array $locations): array
    {
        return array_map(function (array $definition) use ($locations): array {
            if (($definition['options_source'] ?? '') === 'locations') {
                $definition['options'] = array_map(
                    static function (array $location): array {
                        $building = trim((string) ($location['building'] ?? ''));
                        $name = trim((string) ($location['name'] ?? ''));
                        $label = $name;

                        if ($building !== '' && $name !== '') {
                            $label = $building . ' / ' . $name;
                        }

                        return [
                            'value' => (string) ($location['id'] ?? ''),
                            'label' => $label,
                        ];
                    },
                    $locations
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
