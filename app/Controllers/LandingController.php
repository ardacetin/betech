<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\KnowledgeBaseArticle;
use App\Models\Setting;
use App\Services\Auth\SessionAuthService;
use App\Services\ViewRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LandingController
{
    /**
     * @param array<string, mixed> $appConfig
     */
    public function __construct(
        private readonly array $appConfig,
        private readonly ViewRenderer $viewRenderer,
        private readonly Setting $settingModel,
        private readonly KnowledgeBaseArticle $knowledgeBaseArticleModel,
        private readonly SessionAuthService $sessionAuthService,
        private readonly HealthController $healthController,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->sessionAuthService->isAuthenticated()) {
            return $this->healthController->index($request, $response);
        }

        $landing = $this->settingModel->getLandingContent();
        $articles = [];

        try {
            $articles = $this->knowledgeBaseArticleModel->findPublished();
        } catch (\Throwable) {
            $articles = [];
        }

        $html = $this->viewRenderer->render('landing', [
            'pageTitle' => $landing['hero_title'] !== ''
                ? $landing['hero_title']
                : __('landing_default_hero_title'),
            'appName' => __('app_name'),
            'locale' => \App\Services\Translator::instance()->getLocale(),
            'heroTitle' => $landing['hero_title'] !== ''
                ? $landing['hero_title']
                : __('landing_default_hero_title'),
            'heroSubtitle' => $landing['hero_subtitle'] !== ''
                ? $landing['hero_subtitle']
                : __('landing_default_hero_subtitle'),
            'heroCtaLabel' => $landing['hero_cta_label'] !== ''
                ? $landing['hero_cta_label']
                : __('landing_default_hero_cta'),
            'articles' => $articles,
            'appUrl' => (string) ($this->appConfig['url'] ?? ''),
        ], null);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
