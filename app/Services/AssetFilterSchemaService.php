<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;

class AssetFilterSchemaService
{
    /**
     * @param list<array<string, mixed>> $categories
     * @param list<array<string, mixed>> $globalCustomFields
     *
     * @return list<array<string, mixed>>
     */
    public function buildDefinitions(array $categories = [], array $globalCustomFields = []): array
    {
        unset($categories, $globalCustomFields);

        return [
            $this->coreTextField('asset_tag', 'col_asset_tag', 'assets.asset_tag'),
            $this->coreTextField('name', 'col_name', 'assets.name'),
            $this->coreTextField('model', 'col_model', 'assets.model'),
            $this->coreTextField('brand', 'col_brand', 'assets.brand'),
            $this->coreTextField('serial_number', 'label_serial_number', 'assets.serial_number'),
            $this->coreTextField('type', 'col_category', 'assets.type'),
            [
                'name' => 'status',
                'label_key' => 'col_status',
                'type' => 'select',
                'input' => 'select',
                'match' => 'exact',
                'column' => 'assets.status',
                'options' => $this->statusOptions(),
            ],
            $this->coreTextField('location', 'col_location', 'assets.location'),
            $this->coreTextField('building', 'col_building', 'assets.building'),
            $this->coreTextField('assigned_to', 'col_assigned_user', 'assets.assigned_to'),
            $this->coreTextField('mac_address_1', 'label_mac_address_1', 'assets.mac_address_1'),
            $this->coreTextField('mac_address_2', 'label_mac_address_2', 'assets.mac_address_2'),
        ];
    }

    /**
     * @param list<array<string, mixed>> $definitions
     * @param list<array<string, mixed>> $categories
     * @param list<array<string, mixed>> $locations
     *
     * @return list<array<string, mixed>>
     */
    public function resolveOptions(array $definitions, Asset $assetModel, array $categories = [], array $locations = []): array
    {
        unset($categories, $locations);

        return array_map(function (array $definition) use ($assetModel): array {
            if (($definition['options_source'] ?? '') === 'column_distinct') {
                $column = ltrim((string) ($definition['column'] ?? ''), 'assets.');
                $definition['options'] = $this->buildSelectOptions($assetModel->getDistinctColumnValues($column));
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
        $rawFilters = $queryParams['filter'] ?? [];

        if (!is_array($rawFilters)) {
            return [];
        }

        $filters = [];

        foreach ($rawFilters as $name => $value) {
            if (!is_string($name) || is_object($value) || is_array($value)) {
                continue;
            }

            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $trimmed = trim((string) $value);

            if ($trimmed !== '') {
                $filters[$name] = $trimmed;
            }
        }

        return $filters;
    }

    /**
     * @param list<array<string, mixed>> $definitions
     *
     * @return array<string, array<string, mixed>>
     */
    public function indexDefinitionsByName(array $definitions): array
    {
        $indexed = [];

        foreach ($definitions as $definition) {
            $name = (string) ($definition['name'] ?? '');

            if ($name !== '') {
                $indexed[$name] = $definition;
            }
        }

        return $indexed;
    }

    /**
     * @return array{name: string, label_key: string, type: string, input: string, match: string, column: string}
     */
    private function coreTextField(string $name, string $labelKey, string $column): array
    {
        return [
            'name' => $name,
            'label_key' => $labelKey,
            'type' => 'text',
            'input' => 'text',
            'match' => 'partial',
            'column' => $column,
        ];
    }

    /**
     * @return list<array{value: string, label_key: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => 'ready', 'label_key' => 'status_ready'],
            ['value' => 'deployed', 'label_key' => 'status_deployed'],
            ['value' => 'storage', 'label_key' => 'status_storage'],
            ['value' => 'broken', 'label_key' => 'status_broken'],
            ['value' => 'under_repair', 'label_key' => 'status_under_repair'],
        ];
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

    /**
     * @param list<string> $values
     *
     * @return list<array{value: string, label: string}>
     */
    private function buildSelectOptions(array $values): array
    {
        $options = [];
        $seen = [];

        foreach ($values as $value) {
            $trimmed = trim((string) $value);

            if ($trimmed === '' || isset($seen[$trimmed])) {
                continue;
            }

            $seen[$trimmed] = true;
            $options[] = [
                'value' => $trimmed,
                'label' => $trimmed,
            ];
        }

        usort(
            $options,
            static fn (array $left, array $right): int => strcasecmp($left['label'], $right['label'])
        );

        return $options;
    }
}
