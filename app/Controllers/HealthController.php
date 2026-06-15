<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Models\Category;
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
        private readonly ViewRenderer $viewRenderer
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $categories = $this->categoryModel->findAll();

        $html = $this->viewRenderer->render('dashboard', [
            'appName' => 'Betech',
            'pageTitle' => __('page_title'),
            'environment' => $this->appConfig['env'],
            'locale' => Translator::instance()->getLocale(),
            'assets' => $this->assetModel->findAllForDashboard(),
            'metrics' => $this->assetModel->getMetrics(),
            'categories' => $categories,
            'categoryFieldsJson' => json_encode(
                $this->categoryModel->fieldMapByCategoryId(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            ),
        ]);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
