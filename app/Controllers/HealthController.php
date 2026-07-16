<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Models\AssetCustomField;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\Consumable;
use App\Models\License;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Personnel;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\AssetFilterSchemaService;
use App\Services\AssetTypeTableService;
use App\Services\ConsumableFilterSchemaService;
use App\Services\LicenseFilterSchemaService;
use App\Services\ListPagination;
use App\Services\Auth\SessionAuthService;
use App\Services\EndUserContextService;
use App\Services\AssetPublicViewService;
use App\Services\NetworkPortMappingService;
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
        private readonly AssetType $assetTypeModel,
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
        private readonly License $licenseModel,
        private readonly LicenseFilterSchemaService $licenseFilterSchemaService,
        private readonly Consumable $consumableModel,
        private readonly ConsumableFilterSchemaService $consumableFilterSchemaService,
        private readonly AssetCustomField $assetCustomFieldModel,
        private readonly AssetTypeTableService $assetTypeTableService,
        private readonly NetworkPortMappingService $networkPortMappingService,
        private readonly AssetPublicViewService $assetPublicViewService,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $typeIdentifier = trim((string) ($request->getQueryParams()['type'] ?? ''));
        $typeContext = $typeIdentifier !== ''
            ? $this->assetTypeTableService->resolveWhitelistedType($typeIdentifier)
            : null;

        return $this->renderDashboard(
            $request,
            $response,
            $typeContext,
            false,
            null
        );
    }

    public function documents(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->renderDashboard(
            $request,
            $response,
            null,
            false,
            'documents'
        );
    }

    public function switchPorts(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $role = $this->sessionAuthService->role();

        if (!$this->userModel->isOperationalRole($role)) {
            return $response
                ->withHeader('Location', '/unauthorized')
                ->withStatus(302);
        }

        $selectedSwitchId = (int) ($request->getQueryParams()['switch_id'] ?? 0);
        $switches = [];
        $matrix = null;

        try {
            $switches = $this->networkPortMappingService->listSwitchDirectory();
        } catch (\Throwable) {
            $switches = [];
        }

        if ($selectedSwitchId > 0) {
            try {
                $matrix = $this->networkPortMappingService->getSwitchPortMatrix($selectedSwitchId);
            } catch (\Throwable) {
                $matrix = null;
            }
        }

        return $this->renderDashboard(
            $request,
            $response,
            null,
            false,
            'switch_ports',
            [
                'switches' => $switches,
                'selected_switch_id' => $selectedSwitchId,
                'matrix' => $matrix,
            ]
        );
    }

    public function inventorySection(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $typeIdentifier = trim((string) ($args['typeId'] ?? ''));
        $typeContext = $this->assetTypeTableService->resolveWhitelistedType($typeIdentifier);

        return $this->renderDashboard(
            $request,
            $response,
            $typeContext,
            true,
            null
        );
    }

    /**
     * @param array{id: int, slug: string, table: string}|null $requestedAssetType
     * @param array{switches?: list<array<string, mixed>>, selected_switch_id?: int, matrix?: array<string, mixed>|null} $switchPortsBootstrap
     */
    private function renderDashboard(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ?array $requestedAssetType,
        bool $forceAssetsView,
        ?string $initialActiveView = null,
        array $switchPortsBootstrap = [],
    ): ResponseInterface {
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
        $assetTypes = $isEndUser ? [] : $this->assetTypeModel->findAll();
        $activeAssetTypeId = $requestedAssetType['id'] ?? null;
        $activeAssetTypeSlug = $requestedAssetType['slug'] ?? null;
        $activeAssetTable = $requestedAssetType['table'] ?? null;

        if (!$isEndUser && $activeAssetTypeId === null && $assetTypes !== [] && !$forceAssetsView) {
            $fallbackType = $this->assetTypeTableService->resolveWhitelistedType('');
            $activeAssetTypeId = $fallbackType['id'] ?? (int) ($assetTypes[0]['id'] ?? 0);
            $activeAssetTypeSlug = $fallbackType['slug'] ?? null;
            $activeAssetTable = $fallbackType['table'] ?? null;
        }

        if ($canManageAssets) {
            $categories = $this->categoryModel->findAll();
            $locations = $this->locationModel->findAll();
            $analytics = $this->analyticsService->getDashboardStats();
            $settings = $this->settingModel->getAdminBundle();
            $settings['qr_public_view_config'] = $this->assetPublicViewService->getConfig();
            $globalCustomFields = $activeAssetTypeId !== null
                ? array_map(
                    static fn (array $field): array => [
                        'name' => (string) ($field['column_name'] ?? ''),
                        'label' => (string) ($field['label'] ?? ''),
                        'type' => (string) ($field['field_type'] ?? 'varchar'),
                    ],
                    $this->assetCustomFieldModel->findByAssetTypeId($activeAssetTypeId)
                )
                : (is_array($settings['custom_fields'] ?? null) ? $settings['custom_fields'] : []);

            $assetFilterDefinitions = $this->assetFilterSchemaService->buildDefinitions($categories, $globalCustomFields);
            $assetFilterDefinitions = $this->assetFilterSchemaService->resolveOptions(
                $assetFilterDefinitions,
                $this->assetModel,
                $categories,
                $locations,
                $activeAssetTypeId
            );
            $assetActiveFilters = $this->assetFilterSchemaService->parseRequestFilters($request->getQueryParams());
            $assetPage = ListPagination::parsePage($request->getQueryParams());
            $assetSortOrder = $this->assetModel->buildSortOrderFromQuery($request->getQueryParams(), $activeAssetTypeId);
            $assetListResult = $this->assetModel->findPaginatedForDashboard(
                $assetActiveFilters,
                $assetFilterDefinitions,
                $assetPage,
                ListPagination::PAGE_SIZE,
                $activeAssetTypeId,
                $assetSortOrder,
                $activeAssetTable
            );
            $assets = $assetListResult['data'];
            $assetPagination = $assetListResult['pagination'];

            $licenseFilterDefinitions = $this->licenseFilterSchemaService->buildDefinitions();
            $licenseFilterDefinitions = $this->licenseFilterSchemaService->resolveOptions(
                $licenseFilterDefinitions,
                $this->licenseModel
            );
            $licenseActiveFilters = $this->licenseFilterSchemaService->parseRequestFilters($request->getQueryParams());

            $consumableFilterDefinitions = $this->consumableFilterSchemaService->buildDefinitions($locations);
            $consumableFilterDefinitions = $this->consumableFilterSchemaService->resolveOptions(
                $consumableFilterDefinitions,
                $this->consumableModel,
                $locations
            );
            $consumableActiveFilters = $this->consumableFilterSchemaService->parseRequestFilters($request->getQueryParams());
        } else {
            $categories = [];
            $locations = [];
            $assets = [];
            $analytics = $this->emptyAnalytics();
            $settings = [];
            $assetFilterDefinitions = [];
            $assetActiveFilters = [];
            $assetPagination = ListPagination::meta(1, 0);
            $licenseFilterDefinitions = [];
            $licenseActiveFilters = [];
            $consumableFilterDefinitions = [];
            $consumableActiveFilters = [];
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
            'pageTitle' => $this->resolveDashboardPageTitle(
                $isEndUser,
                $requestedAssetType,
                $forceAssetsView,
                $initialActiveView,
                $assetTypes
            ),
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
            'licenseFilterDefinitions' => $licenseFilterDefinitions ?? [],
            'licenseActiveFilters' => $licenseActiveFilters ?? [],
            'consumableFilterDefinitions' => $consumableFilterDefinitions ?? [],
            'consumableActiveFilters' => $consumableActiveFilters ?? [],
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
            'assetTypes' => $assetTypes,
            'assetTypesJson' => json_encode($assetTypes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'activeAssetTypeId' => $activeAssetTypeId ?? 0,
            'activeAssetTypeSlug' => $activeAssetTypeSlug ?? '',
            'forceAssetsView' => $forceAssetsView,
            'initialActiveView' => $initialActiveView,
            'assetSchemaJson' => json_encode(
                $activeAssetTypeId !== null
                    ? $this->assetTypeTableService->buildSchemaDefinition(
                        $activeAssetTypeId,
                        $this->assetCustomFieldModel->findByAssetTypeId($activeAssetTypeId)
                    )
                    : [],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            ),
            'switchPortsSwitchesJson' => json_encode(
                $switchPortsBootstrap['switches'] ?? [],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            ),
            'switchPortsSelectedSwitchId' => (int) ($switchPortsBootstrap['selected_switch_id'] ?? 0),
            'switchPortsMatrixJson' => json_encode(
                $switchPortsBootstrap['matrix'] ?? null,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            ),
        ]);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * @param array{id: int, slug: string, table: string}|null $requestedAssetType
     * @param list<array<string, mixed>> $assetTypes
     */
    private function resolveDashboardPageTitle(
        bool $isEndUser,
        ?array $requestedAssetType,
        bool $forceAssetsView,
        ?string $initialActiveView,
        array $assetTypes
    ): string {
        if ($isEndUser) {
            return __('portal_page_title');
        }

        if ($initialActiveView === 'documents') {
            return __('quality_documents_page_title');
        }

        if ($initialActiveView === 'switch_ports') {
            return __('switch_ports_page_title');
        }

        if ($forceAssetsView && $requestedAssetType !== null) {
            foreach ($assetTypes as $assetType) {
                if ((int) ($assetType['id'] ?? 0) === $requestedAssetType['id']) {
                    return (string) ($assetType['name'] ?? __('nav_assets'));
                }
            }
        }

        return __('dashboard_page_title');
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
