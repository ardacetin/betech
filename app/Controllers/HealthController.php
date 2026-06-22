<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Personnel;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\AssetFilterSchemaService;
use App\Services\ListPagination;
use App\Services\Auth\SessionAuthService;
use App\Services\EndUserContextService;
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
        private readonly Personnel $personnelModel,
        private readonly SessionAuthService $sessionAuthService,
        private readonly EndUserContextService $endUserContextService,
        private readonly Location $locationModel,
        private readonly AssetFilterSchemaService $assetFilterSchemaService,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $this->sessionAuthService->userId() ?? 0;
        $role = $this->sessionAuthService->role();
        $isEndUser = $this->userModel->isEndUserRole($role);
        $canManageAssets = $this->userModel->isOperationalRole($role);
        $canAccessSettings = $this->userModel->isSuperAdmin($role);
        $canAccessPersonnel = $canManageAssets;

        $currentUser = $userId > 0 ? $this->personnelModel->findById($userId) : null;
        $currentUserEmail = trim((string) ($currentUser['email'] ?? ''));

        $personnelProfile = $isEndUser ? $this->endUserContextService->resolvePersonnel() : null;
        $hasPersonnelProfile = $personnelProfile !== null;
        $userName = trim((string) ($personnelProfile['name'] ?? $currentUser['name'] ?? ''));
        $userEmail = trim((string) ($personnelProfile['email'] ?? $currentUserEmail));

        if ($canManageAssets) {
            $categories = $this->categoryModel->findAll();
            $locations = $this->locationModel->findAll();
            $analytics = $this->analyticsService->getDashboardStats();
            $settings = $this->settingModel->getAdminBundle();
            $globalCustomFields = is_array($settings['custom_fields'] ?? null) ? $settings['custom_fields'] : [];

            $assetFilterDefinitions = $this->assetFilterSchemaService->buildDefinitions($categories, $globalCustomFields);
            $assetFilterDefinitions = $this->assetFilterSchemaService->resolveOptions(
                $assetFilterDefinitions,
                $this->assetModel,
                $categories,
                $locations
            );
            $assetActiveFilters = $this->assetFilterSchemaService->parseRequestFilters($request->getQueryParams());
            $assetPage = ListPagination::parsePage($request->getQueryParams());
            $assetListResult = $this->assetModel->findPaginatedForDashboard(
                $assetActiveFilters,
                $assetFilterDefinitions,
                $assetPage
            );
            $assets = $assetListResult['data'];
            $assetPagination = $assetListResult['pagination'];
        } else {
            $categories = [];
            $locations = [];
            $assets = [];
            $analytics = $this->emptyAnalytics();
            $settings = [];
            $assetFilterDefinitions = [];
            $assetActiveFilters = [];
            $assetPagination = ListPagination::meta(1, 0);
        }

        $personnelRows = [];
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
            'pageTitle' => $isEndUser ? __('portal_page_title') : __('page_title'),
            'environment' => $this->appConfig['env'],
            'locale' => Translator::instance()->getLocale(),
            'csrfToken' => $this->sessionAuthService->getOrCreateCsrfToken(),
            'userRole' => $role,
            'canManageAssets' => $canManageAssets,
            'canAccessSettings' => $canAccessSettings,
            'canAccessPersonnel' => $canAccessPersonnel,
            'currentUserId' => $userId,
            'currentUserEmail' => $currentUserEmail,
            'isEndUser' => $isEndUser,
            'isSuperAdmin' => $canAccessSettings,
            'hasPersonnelProfile' => $hasPersonnelProfile,
            'userName' => $userName,
            'userEmail' => $userEmail,
            'assets' => $assets,
            'assetFilterDefinitions' => $assetFilterDefinitions ?? [],
            'assetActiveFilters' => $assetActiveFilters ?? [],
            'assetPagination' => $assetPagination ?? ListPagination::meta(1, 0),
            'assetQrCodesJson' => json_encode($assetQrCodes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'analytics' => $analytics,
            'analyticsJson' => json_encode($analytics, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'categories' => $categories,
            'categoryFieldsJson' => json_encode(
                $canManageAssets ? $this->categoryModel->fieldMapByCategoryId() : [],
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

    /**
     * @return array<string, mixed>
     */
    private function emptyAnalytics(): array
    {
        return [
            'total' => 0,
            'summary_cards' => [
                'total' => 0,
                'deployed' => 0,
                'in_storage' => 0,
                'broken' => 0,
            ],
            'by_status' => [],
            'by_category' => [],
            'assignment' => [
                'assigned' => 0,
                'unassigned' => 0,
                'assigned_percentage' => 0.0,
                'unassigned_percentage' => 0.0,
            ],
            'help_desk' => [
                'open' => 0,
                'in_progress' => 0,
                'critical' => 0,
            ],
            'licenses' => [
                'total' => 0,
                'expiring_soon' => 0,
                'seat_usage' => [],
            ],
            'consumables' => [
                'total' => 0,
                'low_stock' => 0,
                'low_stock_items' => [],
            ],
        ];
    }
}
