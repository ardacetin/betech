<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Services\Auth\SessionAuthService;
use App\Services\NetworkPortMappingService;
use App\Services\Translator;
use App\Services\ViewRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

class SwitchPortController
{
    public function __construct(
        private readonly NetworkPortMappingService $networkPortMappingService,
        private readonly ViewRenderer $viewRenderer,
        private readonly SessionAuthService $sessionAuthService,
        private readonly User $userModel,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withHeader('Location', '/network/switch-ports')
            ->withStatus(302);
    }

    public function portConfig(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($denied = $this->denyUnlessOperational($request, $response)) {
            return $denied;
        }

        $query = $request->getQueryParams();
        $switchId = (int) ($query['switch_id'] ?? 0);
        $portNumber = trim((string) ($query['port'] ?? ''));

        if ($switchId <= 0 || $portNumber === '') {
            return $this->notFound($response);
        }

        try {
            $context = $this->networkPortMappingService->getPortConfigContext($switchId, $portNumber);
        } catch (RuntimeException) {
            return $this->notFound($response);
        }

        if ($context === null) {
            return $this->notFound($response);
        }

        $html = $this->viewRenderer->render('port_config', [
            'appName' => __('app_name'),
            'pageTitle' => __('switch_port_config_page_title'),
            'locale' => Translator::instance()->getLocale(),
            'csrfToken' => $this->sessionAuthService->getOrCreateCsrfToken(),
            'contextJson' => json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'backUrl' => '/network/switch-ports?switch_id=' . rawurlencode((string) $switchId),
        ]);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
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
