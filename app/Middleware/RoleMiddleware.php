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

class RoleMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $publicPaths
     * @param list<array{methods: list<string>, pattern: string, roles: list<string>}> $rules
     */
    public function __construct(
        private readonly SessionAuthService $sessionAuthService,
        private readonly array $publicPaths = [],
        private readonly array $rules = []
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if ($this->isPublicPath($path)) {
            return $handler->handle($request);
        }

        if (!$this->sessionAuthService->isAuthenticated()) {
            return $handler->handle($request);
        }

        $method = strtoupper($request->getMethod());
        $role = $this->sessionAuthService->role();

        foreach ($this->rules as $rule) {
            if (!$this->matchesPattern($path, (string) $rule['pattern'])) {
                continue;
            }

            $allowedMethods = array_map('strtoupper', $rule['methods']);

            if (!in_array($method, $allowedMethods, true)) {
                continue;
            }

            $allowedRoles = $rule['roles'];

            if (in_array($role, $allowedRoles, true)) {
                return $handler->handle($request);
            }

            return $this->forbiddenResponse($request);
        }

        return $handler->handle($request);
    }

    /**
     * @param list<string> $allowedRoles
     */
    public static function defaultRules(): array
    {
        $operational = [User::ROLE_SUPER_ADMIN, User::ROLE_TECHNICIAN];

        return [
            [
                'methods' => ['GET', 'PUT'],
                'pattern' => '/api/settings',
                'roles' => [User::ROLE_SUPER_ADMIN],
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/categories',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/categories',
                'roles' => [User::ROLE_SUPER_ADMIN],
            ],
            [
                'methods' => ['PUT'],
                'pattern' => '/api/categories/{id}',
                'roles' => [User::ROLE_SUPER_ADMIN],
            ],
            [
                'methods' => ['DELETE'],
                'pattern' => '/api/categories/{id}',
                'roles' => [User::ROLE_SUPER_ADMIN],
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/locations',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/locations',
                'roles' => [User::ROLE_SUPER_ADMIN],
            ],
            [
                'methods' => ['PUT'],
                'pattern' => '/api/locations/{id}',
                'roles' => [User::ROLE_SUPER_ADMIN],
            ],
            [
                'methods' => ['DELETE'],
                'pattern' => '/api/locations/{id}',
                'roles' => [User::ROLE_SUPER_ADMIN],
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/licenses',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/licenses',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/licenses/{id}/assign',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/licenses/{id}/unassign',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/licenses/{id}/assignments',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/assets/{id}/licenses',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/personnel',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/personnel/sync',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/users',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/system-users',
                'roles' => [User::ROLE_SUPER_ADMIN],
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/system-users',
                'roles' => [User::ROLE_SUPER_ADMIN],
            ],
            [
                'methods' => ['PUT'],
                'pattern' => '/api/system-users/{id}',
                'roles' => [User::ROLE_SUPER_ADMIN],
            ],
            [
                'methods' => ['DELETE'],
                'pattern' => '/api/system-users/{id}',
                'roles' => [User::ROLE_SUPER_ADMIN],
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/users',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/users/search',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/users/{id}/offboard',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/assets',
                'roles' => $operational,
            ],
            [
                'methods' => ['PUT'],
                'pattern' => '/api/assets/{id}',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/assets/{id}/return',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/assets/{id}/transfer',
                'roles' => $operational,
            ],
            [
                'methods' => ['DELETE'],
                'pattern' => '/api/assets/{id}',
                'roles' => [User::ROLE_SUPER_ADMIN],
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/analytics/summary',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/assets/{id}/tutanak',
                'roles' => [User::ROLE_SUPER_ADMIN, User::ROLE_TECHNICIAN, User::ROLE_END_USER],
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/assets/{id}/history',
                'roles' => [User::ROLE_SUPER_ADMIN, User::ROLE_TECHNICIAN, User::ROLE_END_USER],
            ],
        ];
    }

    private function isPublicPath(string $path): bool
    {
        foreach ($this->publicPaths as $pattern) {
            if ($pattern === $path) {
                return true;
            }

            if (str_contains($pattern, '{') && $this->matchesPattern($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesPattern(string $path, string $pattern): bool
    {
        if ($pattern === $path) {
            return true;
        }

        if (!str_contains($pattern, '{')) {
            return false;
        }

        $regex = '#^' . preg_replace('#\{[^/]+\}#', '[^/]+', $pattern) . '$#';

        return preg_match($regex, $path) === 1;
    }

    private function forbiddenResponse(ServerRequestInterface $request): ResponseInterface
    {
        $message = 'Bu işlem için yetkiniz bulunmuyor.';

        if ($this->wantsJson($request)) {
            $response = new Response(403);
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => $message,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        }

        $response = new Response(403);
        $response->getBody()->write($message);

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
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
