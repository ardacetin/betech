<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Announcement;
use App\Models\KnowledgeBaseArticle;
use App\Models\QualityDocument;
use App\Models\Setting;
use App\Services\Auth\SessionAuthService;
use App\Services\TurnstileVerifier;
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
        private readonly Announcement $announcementModel,
        private readonly QualityDocument $qualityDocumentModel,
        private readonly SessionAuthService $sessionAuthService,
        private readonly HealthController $healthController,
        private readonly TurnstileVerifier $turnstileVerifier,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->sessionAuthService->isAuthenticated()) {
            return $this->healthController->index($request, $response);
        }

        return $this->renderPublic($response, 'landing', [
            'featuredArticles' => $this->safePublishedArticles(4),
            'announcements' => $this->safePublishedAnnouncements(5),
            'documents' => $this->safePublicDocuments(4),
        ]);
    }

    public function knowledgeBase(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->sessionAuthService->isAuthenticated()) {
            return $this->healthController->panelView($request, $response, 'knowledge_base');
        }

        return $this->renderPublic($response, 'public_knowledge_base', [
            'articles' => $this->safePublishedArticles(200),
        ]);
    }

    public function documents(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->sessionAuthService->isAuthenticated()) {
            return $this->healthController->index($request, $response);
        }

        return $this->renderPublic($response, 'public_documents', [
            'documents' => $this->safePublicDocuments(200),
        ]);
    }

    public function announcements(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->sessionAuthService->isAuthenticated()) {
            return $this->healthController->index($request, $response);
        }

        return $this->renderPublic($response, 'public_announcements', [
            'announcements' => $this->safePublishedAnnouncements(500),
        ]);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function renderPublic(ResponseInterface $response, string $view, array $extra): ResponseInterface
    {
        $landing = $this->settingModel->getLandingContent();

        $html = $this->viewRenderer->render($view, array_merge([
            'pageTitle' => $landing['hero_title'] !== ''
                ? $landing['hero_title']
                : __('landing_default_hero_title'),
            'appName' => __('app_name'),
            'appSubtitle' => __('app_subtitle'),
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
            'appUrl' => (string) ($this->appConfig['url'] ?? ''),
            'csrfToken' => $this->sessionAuthService->getOrCreateCsrfToken(),
            'turnstileEnabled' => $this->turnstileVerifier->isEnabled(),
            'turnstileSiteKey' => $this->turnstileVerifier->siteKey(),
        ], $extra), null);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function safePublishedArticles(int $limit): array
    {
        try {
            return array_slice($this->knowledgeBaseArticleModel->findPublished(), 0, $limit);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function safePublishedAnnouncements(int $limit): array
    {
        try {
            return $this->announcementModel->findPublished($limit);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function safePublicDocuments(int $limit): array
    {
        try {
            return $this->qualityDocumentModel->findPublic($limit);
        } catch (\Throwable) {
            return [];
        }
    }
}
