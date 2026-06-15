<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\Auth\SessionAuthService;
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
        private readonly Setting $settingModel,
        private readonly User $userModel,
        private readonly SessionAuthService $sessionAuthService
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $this->sessionAuthService->userId() ?? 0;
        $role = $this->sessionAuthService->role();
        $isEndUser = $role === User::ROLE_END_USER;
        $canManageAssets = $this->userModel->isOperationalRole($role);
        $canAccessSettings = $this->userModel->isSuperAdmin($role);
        $canAccessSystemUsers = $canAccessSettings;
        $canAccessPersonnel = $canManageAssets;

        $categories = $this->categoryModel->findAll();

        if ($isEndUser) {
            $assets = $userId > 0
                ? $this->assetModel->findForDashboardByPersonnelId($userId)
                : [];
            $analytics = $this->analyticsService->getEmptyDashboardStats();
            $settings = [];
            $personnelRows = [];
        } else {
            $assets = $this->assetModel->findAllForDashboard();
            $analytics = $this->analyticsService->getDashboardStats();
            $settings = $this->settingModel->getAdminBundle();
            $personnelRows = [];
        }

        $assetQrCodes = [];

        foreach ($assets as $asset) {
            $assetId = (int) $asset['id'];
            $assetQrCodes[$assetId] = $this->qrCodeService->generateForAsset(
                (string) $asset['asset_tag'],
                $assetId
            );
        }

        $html = $this->viewRenderer->render('dashboard', [
            'appName' => __('app_name'),
            'pageTitle' => $isEndUser ? __('page_title_end_user') : __('page_title'),
            'environment' => $this->appConfig['env'],
            'locale' => Translator::instance()->getLocale(),
            'userRole' => $role,
            'canManageAssets' => $canManageAssets,
            'canAccessSettings' => $canAccessSettings,
            'canAccessPersonnel' => $canAccessPersonnel,
            'canAccessSystemUsers' => $canAccessSystemUsers,
            'currentUserId' => $userId,
            'isEndUser' => $isEndUser,
            'isSuperAdmin' => $canAccessSettings,
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
                $settings['custom_fields'] ?? [],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            ),
            'personnel' => $personnelRows,
            'personnelJson' => json_encode($personnelRows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
