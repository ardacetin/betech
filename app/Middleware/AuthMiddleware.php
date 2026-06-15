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

class AuthMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $publicPaths
     */
    public function __construct(
        private readonly SessionAuthService $sessionAuthService,
        private readonly array $publicPaths = [],
        private readonly ?User $userModel = null
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->sessionAuthService->ensureSessionStarted();

        $path = $request->getUri()->getPath();

        if ($this->isPublicPath($path)) {
            return $handler->handle($request);
        }

        if ($this->sessionAuthService->isAuthenticated()) {
            $this->syncSessionRoleFromDatabase();

            return $handler->handle($request);
        }

        if ($this->wantsJson($request)) {
            $response = new Response(401);
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Oturum açmanız gerekiyor.',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        }

        $redirectTarget = rawurlencode($path !== '/' ? $path : '');
        $location = $redirectTarget !== '' && $redirectTarget !== '%2F'
            ? '/login?redirect=' . $redirectTarget
            : '/login';

        return (new Response(302))->withHeader('Location', $location);
    }

    private function isPublicPath(string $path): bool
    {
        foreach ($this->publicPaths as $pattern) {
            if ($pattern === $path) {
                return true;
            }

            if (str_contains($pattern, '{')) {
                $regex = '#^' . preg_replace('#\{[^/]+\}#', '[^/]+', $pattern) . '$#';

                if (preg_match($regex, $path) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private function wantsJson(ServerRequestInterface $request): bool
    {
        if (str_starts_with($request->getUri()->getPath(), '/api/')) {
            return true;
        }

        $accept = $request->getHeaderLine('Accept');

        return str_contains($accept, 'application/json');
    }

    private function syncSessionRoleFromDatabase(): void
    {
        if ($this->userModel === null) {
            return;
        }

        $userId = $this->sessionAuthService->userId();

        if ($userId === null) {
            return;
        }

        $this->sessionAuthService->setRole($this->userModel->findRoleById($userId));
    }
}
