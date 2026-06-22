<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Models\User;
use App\Services\Auth\SessionAuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class EndUserMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionAuthService $sessionAuthService,
        private readonly User $userModel
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->sessionAuthService->ensureSessionStarted();

        if (!$this->sessionAuthService->isAuthenticated()) {
            return $this->unauthenticatedResponse($request);
        }

        $role = $this->sessionAuthService->role();

        if (!$this->userModel->isEndUserRole($role)) {
            return $this->forbiddenResponse($request);
        }

        return $handler->handle($request);
    }

    private function unauthenticatedResponse(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->wantsJson($request)) {
            $response = new Response(401);
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Oturum açmanız gerekiyor.',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        }

        return (new Response(302))->withHeader('Location', '/login');
    }

    private function forbiddenResponse(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->wantsJson($request)) {
            $response = new Response(403);
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Bu kaynağa erişim yetkiniz bulunmuyor.',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        }

        return (new Response(302))->withHeader('Location', '/unauthorized');
    }

    private function wantsJson(ServerRequestInterface $request): bool
    {
        if (str_starts_with($request->getUri()->getPath(), '/api/')) {
            return true;
        }

        $accept = $request->getHeaderLine('Accept');

        return str_contains($accept, 'application/json');
    }
}
