<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Models\Personnel;
use App\Models\Setting;
use App\Models\User;
use App\Services\Auth\SessionAuthService;
use App\Services\Auth\UserIntegrationFactory;
use App\Services\EndUserContextService;
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
        private readonly Personnel $personnelModel,
        private readonly User $userModel,
        private readonly UserIntegrationFactory $userIntegrationFactory,
        private readonly ZimmetTutanakService $zimmetTutanakService,
        private readonly ViewRenderer $viewRenderer,
        private readonly SessionAuthService $sessionAuthService,
        private readonly EndUserContextService $endUserContextService
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

        if (!$this->canAccessAsset($asset)) {
            return $this->renderError($response, 'Bu tutanağa erişim yetkiniz bulunmuyor.', 403);
        }

        $personnelId = $asset['personnel_id'] ?? null;

        if ($personnelId === null) {
            return $this->renderError($response, __('tutanak_no_assignee'), 422);
        }

        $assignedPerson = $this->resolveAssignedPersonnel((int) $personnelId, $asset);
        $template = $this->settingModel->get('zimmet_template', '') ?? '';
        $transactionDate = date('d.m.Y');
        $operatorName = $this->resolveOperatorName();

        if (trim($template) === '') {
            return $this->renderError($response, __('tutanak_template_missing'), 422);
        }

        $body = $this->zimmetTutanakService->renderTemplate($template, [
            'personnel_name' => $assignedPerson['name'] ?? (string) ($asset['personnel_name'] ?? $asset['user_name'] ?? ''),
            'asset_name' => (string) $asset['name'],
            'serial_number' => (string) ($asset['serial_number'] ?? '-'),
            'asset_tag' => (string) ($asset['asset_tag'] ?? '-'),
            'category_name' => (string) ($asset['category_name'] ?? '-'),
            'date' => $transactionDate,
        ]);

        $html = $this->viewRenderer->render('tutanak', [
            'pageTitle' => __('tutanak_page_title'),
            'locale' => Translator::instance()->getLocale(),
            'asset' => $asset,
            'assignedUser' => $assignedPerson,
            'operatorName' => $operatorName,
            'transactionDate' => $transactionDate,
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

    /**
     * @param array<string, mixed> $asset
     *
     * @return array<string, mixed>|null
     */
    private function resolveAssignedPersonnel(int $personnelId, array $asset): ?array
    {
        $person = $this->personnelModel->findById($personnelId);

        if ($person !== null) {
            return [
                'id' => (string) $person['id'],
                'external_id' => (string) ($person['external_id'] ?? ''),
                'name' => (string) $person['name'],
                'email' => (string) $person['email'],
                'department' => $person['department'] ?? null,
            ];
        }

        return $this->userIntegrationFactory->make()->getUserById((string) $personnelId);
    }

    /**
     * @param array<string, mixed> $asset
     */
    private function canAccessAsset(array $asset): bool
    {
        $role = $this->sessionAuthService->role();

        if ($this->userModel->isOperationalRole($role)) {
            return true;
        }

        return $this->endUserContextService->ownsAsset($asset);
    }

    private function resolveOperatorName(): string
    {
        $userId = $this->sessionAuthService->userId();

        if ($userId === null) {
            return '';
        }

        $user = $this->userModel->findById($userId);

        return trim((string) ($user['name'] ?? ''));
    }
}
