<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\HttpErrorResponses;
use App\Models\User;
use App\Services\Auth\SessionAuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AdminMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionAuthService $sessionAuthService,
        private readonly User $userModel,
        private readonly HttpErrorResponses $httpErrorResponses
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->sessionAuthService->ensureSessionStarted();

        if (!$this->sessionAuthService->isAuthenticated()) {
            return $this->unauthenticatedResponse($request);
        }

        $role = $this->sessionAuthService->role();

        if (!$this->userModel->isOperationalRole($role)) {
            return $this->httpErrorResponses->forbidden($request);
        }

        return $handler->handle($request);
    }

    private function unauthenticatedResponse(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->httpErrorResponses->wantsJson($request)) {
            $response = new Response(401);
            $response->getBody()->write(json_encode([
                'error' => 'Oturum açmanız gerekiyor.',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        return (new Response(302))->withHeader('Location', '/login');
    }
}
