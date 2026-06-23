<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
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

        $html = $this->viewRenderer->render('asset_view', [
            'appName' => __('app_name'),
            'pageTitle' => (string) $asset['name'],
            'locale' => Translator::instance()->getLocale(),
            'asset' => $asset,
            'attributeRows' => $this->buildAttributeRows($asset),
        ]);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * @param array<string, mixed> $asset
     *
     * @return list<array{label: string, value: string}>
     */
    private function buildAttributeRows(array $asset): array
    {
        $fields = [
            ['key' => 'model', 'label' => __('col_model')],
            ['key' => 'brand', 'label' => __('col_brand')],
            ['key' => 'serial_number', 'label' => __('label_serial_number')],
            ['key' => 'type', 'label' => __('col_category')],
            ['key' => 'location', 'label' => __('col_location')],
            ['key' => 'building', 'label' => __('col_building')],
            ['key' => 'mac_address_1', 'label' => __('label_mac_address_1')],
            ['key' => 'mac_address_2', 'label' => __('label_mac_address_2')],
        ];

        $rows = [];

        foreach ($fields as $field) {
            $value = trim((string) ($asset[$field['key']] ?? ''));

            if ($value === '') {
                continue;
            }

            $rows[] = [
                'label' => $field['label'],
                'value' => $value,
            ];
        }

        return $rows;
    }

    private function renderNotFound(ResponseInterface $response): ResponseInterface
    {
        $html = $this->viewRenderer->render('asset_view_not_found', [
            'appName' => __('app_name'),
            'pageTitle' => __('asset_not_found_title'),
            'locale' => Translator::instance()->getLocale(),
        ]);

        $response->getBody()->write($html);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus(404);
    }
}
