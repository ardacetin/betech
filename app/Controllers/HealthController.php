<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Setting;
use App\Services\AnalyticsService;
use App\Services\QrCodeService;
use App\Services\Translator;
use App\Services\ViewRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HealthController
{
    /**
     * @param array<string, mixed> $appConfig
     */
    public function __construct(
        private readonly array $appConfig,
        private readonly Asset $assetModel,
        private readonly Category $categoryModel,
        private readonly ViewRenderer $viewRenderer,
        private readonly QrCodeService $qrCodeService,
        private readonly AnalyticsService $analyticsService,
        private readonly Setting $settingModel
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $categories = $this->categoryModel->findAll();
        $assets = $this->assetModel->findAllForDashboard();
        $analytics = $this->analyticsService->getDashboardStats();
        $settings = $this->settingModel->getAdminBundle();
        $assetQrCodes = [];

        foreach ($assets as $asset) {
            $assetId = (int) $asset['id'];
            $assetQrCodes[$assetId] = $this->qrCodeService->generateForAsset(
                (string) $asset['asset_tag'],
                $assetId
            );
        }

        $html = $this->viewRenderer->render('dashboard', [
            'appName' => 'Betech',
            'pageTitle' => __('page_title'),
            'environment' => $this->appConfig['env'],
            'locale' => Translator::instance()->getLocale(),
            'assets' => $assets,
            'assetQrCodesJson' => json_encode($assetQrCodes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'analytics' => $analytics,
            'analyticsJson' => json_encode($analytics, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'categories' => $categories,
            'categoryFieldsJson' => json_encode(
                $this->categoryModel->fieldMapByCategoryId(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            ),
            'settings' => $settings,
            'settingsJson' => json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'globalCustomFieldsJson' => json_encode(
                $settings['custom_fields'],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            ),
        ]);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
