<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Models\AssetCustomField;
use App\Models\AssetType;
use App\Models\User;
use App\Services\AssetTypeTableService;
use App\Services\Auth\SessionAuthService;
use App\Services\Translator;
use App\Services\ViewRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class InventoryFormController
{
    public function __construct(
        private readonly Asset $assetModel,
        private readonly AssetType $assetTypeModel,
        private readonly AssetCustomField $assetCustomFieldModel,
        private readonly AssetTypeTableService $assetTypeTableService,
        private readonly ViewRenderer $viewRenderer,
        private readonly SessionAuthService $sessionAuthService,
        private readonly User $userModel,
    ) {
    }

    public function add(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($denied = $this->denyUnlessOperational($request, $response)) {
            return $denied;
        }

        $typeIdentifier = trim((string) ($request->getQueryParams()['type'] ?? ''));
        $typeContext = $typeIdentifier !== ''
            ? $this->assetTypeTableService->resolveWhitelistedType($typeIdentifier)
            : null;

        $assetTypes = $this->assetTypeModel->findAll();
        $activeTypeId = $typeContext['id'] ?? 0;
        $activeTypeSlug = $typeContext['slug'] ?? '';
        $schema = $activeTypeId > 0
            ? $this->assetTypeTableService->buildSchemaDefinition(
                $activeTypeId,
                $this->assetCustomFieldModel->findByAssetTypeId($activeTypeId)
            )
            : [];

        $html = $this->viewRenderer->render('inventory_add', [
            'appName' => __('app_name'),
            'pageTitle' => __('inventory_add_page_title'),
            'locale' => Translator::instance()->getLocale(),
            'csrfToken' => $this->sessionAuthService->getOrCreateCsrfToken(),
            'assetTypes' => $assetTypes,
            'assetTypesJson' => json_encode($assetTypes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'activeAssetTypeId' => $activeTypeId,
            'activeAssetTypeSlug' => $activeTypeSlug,
            'assetSchemaJson' => json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'cancelUrl' => $activeTypeSlug !== ''
                ? '/inventory/' . rawurlencode($activeTypeSlug)
                : '/',
        ]);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function edit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($denied = $this->denyUnlessOperational($request, $response)) {
            return $denied;
        }

        $query = $request->getQueryParams();
        $assetId = (int) ($query['id'] ?? 0);
        $typeIdentifier = trim((string) ($query['type'] ?? ''));

        if ($assetId <= 0) {
            return $this->notFound($response);
        }

        $asset = $this->assetModel->findById($assetId);

        if ($asset === null) {
            return $this->notFound($response);
        }

        // Prefer the asset's own type when the query type is missing or mismatched.
        $resolvedTypeId = (int) ($asset['asset_type_id'] ?? 0);
        $resolvedSlug = trim((string) ($asset['asset_type_slug'] ?? ''));
        $typeContext = null;

        if ($resolvedTypeId > 0) {
            $typeContext = $this->assetTypeTableService->resolveWhitelistedType((string) $resolvedTypeId);
        }

        if ($typeContext === null && $resolvedSlug !== '') {
            $typeContext = $this->assetTypeTableService->resolveWhitelistedType($resolvedSlug);
        }

        if ($typeContext === null && $typeIdentifier !== '') {
            $typeContext = $this->assetTypeTableService->resolveWhitelistedType($typeIdentifier);
        }

        if ($typeContext === null) {
            return $this->notFound($response);
        }

        $assetTypes = $this->assetTypeModel->findAll();
        $schema = $this->assetTypeTableService->buildSchemaDefinition(
            $typeContext['id'],
            $this->assetCustomFieldModel->findByAssetTypeId($typeContext['id'])
        );

        $html = $this->viewRenderer->render('inventory_edit', [
            'appName' => __('app_name'),
            'pageTitle' => __('inventory_edit_page_title'),
            'locale' => Translator::instance()->getLocale(),
            'csrfToken' => $this->sessionAuthService->getOrCreateCsrfToken(),
            'assetTypes' => $assetTypes,
            'assetTypesJson' => json_encode($assetTypes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'activeAssetTypeId' => $typeContext['id'],
            'activeAssetTypeSlug' => $typeContext['slug'],
            'activeAssetTypeName' => $this->resolveTypeName($assetTypes, $typeContext['id']),
            'assetSchemaJson' => json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'assetJson' => json_encode($asset, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'cancelUrl' => '/inventory/' . rawurlencode($typeContext['slug']),
        ]);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * @param list<array<string, mixed>> $assetTypes
     */
    private function resolveTypeName(array $assetTypes, int $typeId): string
    {
        foreach ($assetTypes as $assetType) {
            if ((int) ($assetType['id'] ?? 0) === $typeId) {
                return (string) ($assetType['name'] ?? '');
            }
        }

        return '';
    }

    private function denyUnlessOperational(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ?ResponseInterface {
        $role = $this->sessionAuthService->role();

        if ($this->userModel->isOperationalRole($role)) {
            return null;
        }

        return $response
            ->withHeader('Location', '/unauthorized')
            ->withStatus(302);
    }

    private function notFound(ResponseInterface $response): ResponseInterface
    {
        $html = $this->viewRenderer->render('errors/404', [
            'appName' => __('app_name'),
            'pageTitle' => __('error_404_title'),
            'heading' => __('error_404_title'),
            'message' => __('error_404_message'),
            'locale' => Translator::instance()->getLocale(),
        ]);

        $response->getBody()->write($html);

        return $response->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
