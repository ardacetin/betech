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
    public function buildDefinitions(array $categories, array $globalCustomFields): array
    {
        $definitions = [
            $this->coreTextField('asset_tag', 'col_asset_tag', 'assets.asset_tag'),
            $this->coreTextField('serial_number', 'label_serial_number', 'assets.serial_number'),
            $this->coreTextField('name', 'col_name', 'assets.name'),
            [
                'name' => 'category_id',
                'label_key' => 'col_category',
                'type' => 'select',
                'input' => 'select',
                'match' => 'exact',
                'column' => 'assets.category_id',
                'options_source' => 'categories',
                'options' => [],
            ],
            [
                'name' => 'status',
                'label_key' => 'col_status',
                'type' => 'select',
                'input' => 'select',
                'match' => 'exact',
                'column' => 'assets.status',
                'options' => $this->statusOptions(),
            ],
            [
                'name' => 'location_id',
                'label_key' => 'col_location',
                'type' => 'select',
                'input' => 'select',
                'match' => 'exact',
                'column' => 'assets.location_id',
                'options_source' => 'locations',
                'options' => [],
            ],
            $this->coreTextField('personnel_name', 'col_assigned_user', 'personnel.name'),
            $this->propertyTextField('mac_adresi_1', 'label_mac_address_1'),
            $this->propertyTextField('mac_adresi_2', 'label_mac_address_2'),
        ];

        foreach ($this->mergeDynamicFieldDefinitions($categories, $globalCustomFields) as $dynamicField) {
            $definitions[] = $dynamicField;
        }

        return $this->dedupeDefinitions($definitions);
    }

    /**
     * @param list<array<string, mixed>> $definitions
     * @param list<array<string, mixed>> $categories
     * @param list<array<string, mixed>> $locations
     *
     * @return list<array<string, mixed>>
     */
    public function resolveOptions(array $definitions, Asset $assetModel, array $categories, array $locations): array
    {
        return array_map(function (array $definition) use ($assetModel, $categories, $locations): array {
            $optionsSource = (string) ($definition['options_source'] ?? '');

            if ($optionsSource === 'categories') {
                $definition['options'] = array_map(
                    static fn (array $category): array => [
                        'value' => (string) ($category['id'] ?? ''),
                        'label' => (string) ($category['name'] ?? ''),
                    ],
                    $categories
                );

                return $definition;
            }

            if ($optionsSource === 'locations') {
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

                return $definition;
            }

            if ($optionsSource === 'property_distinct') {
                $property = (string) ($definition['property'] ?? '');
                $schemaOptions = is_array($definition['options'] ?? null) ? $definition['options'] : [];
                $distinctValues = $property !== '' ? $assetModel->getDistinctPropertyValues($property) : [];
                $definition['options'] = $this->buildSelectOptions(array_merge($schemaOptions, $distinctValues));

                return $definition;
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
            if (!is_string($name)) {
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
     * @param list<array<string, mixed>> $categories
     * @param list<array<string, mixed>> $globalCustomFields
     *
     * @return list<array<string, mixed>>
     */
    private function mergeDynamicFieldDefinitions(array $categories, array $globalCustomFields): array
    {
        $dynamicFields = [];

        foreach ($categories as $category) {
            $fields = is_array($category['fields'] ?? null) ? $category['fields'] : [];

            foreach ($fields as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $name = trim((string) ($field['name'] ?? ''));

                if ($name === '') {
                    continue;
                }

                $dynamicFields[$name] = $field;
            }
        }

        foreach ($globalCustomFields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = trim((string) ($field['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $dynamicFields[$name] = $field;
        }

        $definitions = [];

        foreach ($dynamicFields as $field) {
            $name = trim((string) ($field['name'] ?? ''));
            $type = trim((string) ($field['type'] ?? 'text'));

            if ($name === '') {
                continue;
            }

            if ($type === 'dropdown') {
                $definitions[] = [
                    'name' => $name,
                    'label' => (string) ($field['label'] ?? $name),
                    'label_en' => isset($field['label_en']) ? (string) $field['label_en'] : null,
                    'type' => 'dropdown',
                    'input' => 'select',
                    'match' => 'exact',
                    'property' => $name,
                    'options' => is_array($field['options'] ?? null) ? $field['options'] : [],
                    'options_source' => 'property_distinct',
                ];

                continue;
            }

            if (in_array($type, ['text', 'textarea', 'number'], true)) {
                $definitions[] = [
                    'name' => $name,
                    'label' => (string) ($field['label'] ?? $name),
                    'label_en' => isset($field['label_en']) ? (string) $field['label_en'] : null,
                    'type' => $type,
                    'input' => 'text',
                    'match' => 'partial',
                    'property' => $name,
                ];
            }
        }

        return $definitions;
    }

    /**
     * @param list<array<string, mixed>> $definitions
     *
     * @return list<array<string, mixed>>
     */
    private function dedupeDefinitions(array $definitions): array
    {
        $deduped = [];

        foreach ($definitions as $definition) {
            $name = (string) ($definition['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $deduped[$name] = $definition;
        }

        return array_values($deduped);
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
     * @return array{name: string, label_key: string, type: string, input: string, match: string, property: string}
     */
    private function propertyTextField(string $name, string $labelKey): array
    {
        return [
            'name' => $name,
            'label_key' => $labelKey,
            'type' => 'text',
            'input' => 'text',
            'match' => 'partial',
            'property' => $name,
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
