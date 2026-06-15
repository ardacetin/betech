<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Models\Setting;
use App\Services\Auth\UserIntegrationFactory;
use App\Services\Translator;
use App\Services\ViewRenderer;
use App\Services\ZimmetTutanakService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AssetTutanakController
{
    public function __construct(
        private readonly Asset $assetModel,
        private readonly Setting $settingModel,
        private readonly UserIntegrationFactory $userIntegrationFactory,
        private readonly ZimmetTutanakService $zimmetTutanakService,
        private readonly ViewRenderer $viewRenderer
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $assetId = (int) ($args['id'] ?? 0);

        if ($assetId <= 0) {
            return $this->renderError($response, __('tutanak_invalid_asset'), 400);
        }

        $asset = $this->assetModel->findByIdForView($assetId);

        if ($asset === null) {
            return $this->renderError($response, __('tutanak_asset_not_found'), 404);
        }

        $userId = $asset['user_id'] ?? null;

        if ($userId === null) {
            return $this->renderError($response, __('tutanak_no_assignee'), 422);
        }

        $assignedUser = $this->userIntegrationFactory->make()->getUserById((string) $userId);
        $template = $this->settingModel->get('zimmet_template', '') ?? '';

        if (trim($template) === '') {
            return $this->renderError($response, __('tutanak_template_missing'), 422);
        }

        $body = $this->zimmetTutanakService->renderTemplate($template, [
            'personnel_name' => $assignedUser['name'] ?? (string) ($asset['user_name'] ?? ''),
            'asset_name' => (string) $asset['name'],
            'serial_number' => (string) ($asset['serial_number'] ?? '-'),
            'date' => date('d.m.Y'),
        ]);

        $html = $this->viewRenderer->render('tutanak', [
            'pageTitle' => __('tutanak_page_title'),
            'locale' => Translator::instance()->getLocale(),
            'asset' => $asset,
            'assignedUser' => $assignedUser,
            'body' => $body,
        ]);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function renderError(ResponseInterface $response, string $message, int $statusCode): ResponseInterface
    {
        $html = $this->viewRenderer->render('tutanak_error', [
            'pageTitle' => __('tutanak_page_title'),
            'locale' => Translator::instance()->getLocale(),
            'message' => $message,
        ]);

        $response->getBody()->write($html);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus($statusCode);
    }
}
