<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Models\Category;
use App\Services\Translator;
use App\Services\ViewRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AssetViewController
{
    /**
     * @param array<string, mixed> $appConfig
     */
    public function __construct(
        private readonly array $appConfig,
        private readonly Asset $assetModel,
        private readonly Category $categoryModel,
        private readonly ViewRenderer $viewRenderer
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $assetId = (int) ($args['id'] ?? 0);

        if ($assetId <= 0) {
            return $this->renderNotFound($response);
        }

        $asset = $this->assetModel->findByIdForView($assetId);

        if ($asset === null) {
            return $this->renderNotFound($response);
        }

        $categoryFields = $this->resolveCategoryFields((int) $asset['category_id']);
        $propertyRows = $this->buildPropertyRows(
            is_array($asset['properties'] ?? null) ? $asset['properties'] : [],
            $categoryFields
        );

        $html = $this->viewRenderer->render('asset_view', [
            'appName' => 'Betech',
            'pageTitle' => (string) $asset['name'],
            'locale' => Translator::instance()->getLocale(),
            'asset' => $asset,
            'propertyRows' => $propertyRows,
        ]);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * @return list<array{name: string, label: string, value: string}>
     */
    private function buildPropertyRows(array $properties, array $categoryFields): array
    {
        if ($properties === []) {
            return [];
        }

        $fieldLabels = [];

        foreach ($categoryFields as $field) {
            if (!is_array($field) || !isset($field['name'])) {
                continue;
            }

            $name = (string) $field['name'];
            $label = (string) ($field['label'] ?? $name);

            if (Translator::instance()->getLocale() === 'en' && !empty($field['label_en'])) {
                $label = (string) $field['label_en'];
            }

            $fieldLabels[$name] = $label;
        }

        $rows = [];

        foreach ($properties as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $rows[] = [
                'name' => $key,
                'label' => $fieldLabels[$key] ?? $key,
                'value' => $this->formatPropertyValue($value),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveCategoryFields(int $categoryId): array
    {
        foreach ($this->categoryModel->findAll() as $category) {
            if ((int) $category['id'] === $categoryId) {
                return is_array($category['fields'] ?? null) ? $category['fields'] : [];
            }
        }

        return [];
    }

    private function formatPropertyValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function renderNotFound(ResponseInterface $response): ResponseInterface
    {
        $html = $this->viewRenderer->render('asset_view_not_found', [
            'appName' => 'Betech',
            'pageTitle' => __('asset_not_found_title'),
            'locale' => Translator::instance()->getLocale(),
        ]);

        $response->getBody()->write($html);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus(404);
    }
}
