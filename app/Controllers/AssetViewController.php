<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Services\AssetPublicViewService;
use App\Services\Translator;
use App\Services\ViewRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class AssetViewController
{
    /**
     * @param array<string, mixed> $appConfig
     */
    public function __construct(
        private readonly array $appConfig,
        private readonly Asset $assetModel,
        private readonly AssetPublicViewService $assetPublicViewService,
        private readonly ViewRenderer $viewRenderer
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $token = strtolower(trim((string) ($args['token'] ?? '')));

        // Reject legacy sequential numeric IDs and other non-token values.
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return $this->renderNotFound($response);
        }

        $access = $this->assetPublicViewService->evaluateAccess($request);

        if (!$access['allowed']) {
            if ($access['redirect_login']) {
                $redirect = rawurlencode('/assets/view/' . $token);

                return (new Response(302))->withHeader('Location', '/login?redirect=' . $redirect);
            }

            return $this->renderForbidden($response, (string) ($access['reason'] ?? ''));
        }

        $assetId = $this->assetPublicViewService->findAssetIdByToken($token);

        if ($assetId === null) {
            return $this->renderNotFound($response);
        }

        $asset = $this->assetModel->findByIdForView($assetId);

        if ($asset === null) {
            return $this->renderNotFound($response);
        }

        $html = $this->viewRenderer->render('asset_view', [
            'appName' => __('app_name'),
            'pageTitle' => (string) $asset['name'],
            'locale' => Translator::instance()->getLocale(),
            'asset' => $asset,
            'attributeRows' => $this->assetPublicViewService->buildVisibleAttributeRows($asset),
            'showAssignedTo' => $this->assetPublicViewService->shouldShowAssignedTo(),
            'showStatus' => $this->assetPublicViewService->shouldShowStatus(),
            'showType' => $this->assetPublicViewService->shouldShowField('type'),
        ]);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function renderNotFound(ResponseInterface $response): ResponseInterface
    {
        $html = $this->viewRenderer->render('asset_view_not_found', [
            'appName' => __('app_name'),
            'pageTitle' => __('asset_not_found_title'),
            'locale' => Translator::instance()->getLocale(),
        ]);

        $response->getBody()->write($html);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus(404);
    }

    private function renderForbidden(ResponseInterface $response, string $message): ResponseInterface
    {
        $html = $this->viewRenderer->render('asset_view_forbidden', [
            'appName' => __('app_name'),
            'pageTitle' => __('asset_public_view_forbidden_title'),
            'locale' => Translator::instance()->getLocale(),
            'message' => $message !== '' ? $message : __('asset_public_view_network_denied'),
        ]);

        $response->getBody()->write($html);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus(403);
    }
}
