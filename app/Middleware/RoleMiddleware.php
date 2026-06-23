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

class RoleMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $publicPaths
     * @param list<array{methods: list<string>, pattern: string, roles: list<string>}> $rules
     */
    public function __construct(
        private readonly SessionAuthService $sessionAuthService,
        private readonly HttpErrorResponses $httpErrorResponses,
        private readonly array $publicPaths = [],
        private readonly array $rules = []
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $this->normalizePath($request->getUri()->getPath());

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

            return $this->httpErrorResponses->forbidden($request);
        }

        if (str_starts_with($path, '/api/')) {
            return $this->httpErrorResponses->forbidden($request);
        }

        return $handler->handle($request);
    }

    /**
     * @param list<string> $allowedRoles
     */
    public static function defaultRules(): array
    {
        $operational = [User::ROLE_ADMIN];
        $withEndUser = [User::ROLE_ADMIN, User::ROLE_USER];

        return [
            [
                'methods' => ['GET', 'PUT'],
                'pattern' => '/api/settings',
                'roles' => [User::ROLE_ADMIN],
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/settings/smtp/test',
                'roles' => [User::ROLE_ADMIN],
            ],
            [
                'methods' => ['GET', 'POST'],
                'pattern' => '/api/backups',
                'roles' => [User::ROLE_ADMIN],
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/backups/{filename}/download',
                'roles' => [User::ROLE_ADMIN],
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/audit-logs',
                'roles' => [User::ROLE_ADMIN],
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/categories',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/categories',
                'roles' => [User::ROLE_ADMIN],
            ],
            [
                'methods' => ['PUT'],
                'pattern' => '/api/categories/{id}',
                'roles' => [User::ROLE_ADMIN],
            ],
            [
                'methods' => ['DELETE'],
                'pattern' => '/api/categories/{id}',
                'roles' => [User::ROLE_ADMIN],
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/locations',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/locations',
                'roles' => [User::ROLE_ADMIN],
            ],
            [
                'methods' => ['PUT'],
                'pattern' => '/api/locations/{id}',
                'roles' => [User::ROLE_ADMIN],
            ],
            [
                'methods' => ['DELETE'],
                'pattern' => '/api/locations/{id}',
                'roles' => [User::ROLE_ADMIN],
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/ticket-categories',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/ticket-categories',
                'roles' => [User::ROLE_ADMIN],
            ],
            [
                'methods' => ['PUT'],
                'pattern' => '/api/ticket-categories/{id}',
                'roles' => [User::ROLE_ADMIN],
            ],
            [
                'methods' => ['DELETE'],
                'pattern' => '/api/ticket-categories/{id}',
                'roles' => [User::ROLE_ADMIN],
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/reports/helpdesk',
                'roles' => [User::ROLE_ADMIN],
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
                'methods' => ['GET'],
                'pattern' => '/api/licenses/{id}',
                'roles' => $operational,
            ],
            [
                'methods' => ['PUT'],
                'pattern' => '/api/licenses/{id}',
                'roles' => $operational,
            ],
            [
                'methods' => ['DELETE'],
                'pattern' => '/api/licenses/{id}',
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
                'pattern' => '/api/ip-networks',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/ip-networks',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/ip-networks/import/template',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/ip-networks/import',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/ip-addresses/import/template',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/ip-addresses/import',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/ip-networks/{id}',
                'roles' => $operational,
            ],
            [
                'methods' => ['PUT'],
                'pattern' => '/api/ip-networks/{id}',
                'roles' => $operational,
            ],
            [
                'methods' => ['DELETE'],
                'pattern' => '/api/ip-networks/{id}',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/ip-networks/{id}/addresses',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/ip-networks/{id}/export',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/ip-networks/{id}/generate',
                'roles' => $operational,
            ],
            [
                'methods' => ['PUT'],
                'pattern' => '/api/ip-addresses/{id}',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/ip-addresses/bulk-update',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/admin/network/ip/bulk-update',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/consumables',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/consumables',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/consumables/{id}',
                'roles' => $operational,
            ],
            [
                'methods' => ['PUT'],
                'pattern' => '/api/consumables/{id}',
                'roles' => $operational,
            ],
            [
                'methods' => ['DELETE'],
                'pattern' => '/api/consumables/{id}',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/consumables/{id}/checkout',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/consumables/{id}/restock',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/assets/{id}/licenses',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/my/assets',
                'roles' => [User::ROLE_USER],
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/knowledge-base/published',
                'roles' => $withEndUser,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/knowledge-base',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/knowledge-base',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/knowledge-base/{id}',
                'roles' => $operational,
            ],
            [
                'methods' => ['PUT'],
                'pattern' => '/api/knowledge-base/{id}',
                'roles' => $operational,
            ],
            [
                'methods' => ['DELETE'],
                'pattern' => '/api/knowledge-base/{id}',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/tickets',
                'roles' => $withEndUser,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/tickets',
                'roles' => $withEndUser,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/tickets/{id}',
                'roles' => $withEndUser,
            ],
            [
                'methods' => ['PUT'],
                'pattern' => '/api/tickets/{id}',
                'roles' => $operational,
            ],
            [
                'methods' => ['DELETE'],
                'pattern' => '/api/tickets/{id}',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/tickets/{id}/comments',
                'roles' => $withEndUser,
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
                'methods' => ['POST'],
                'pattern' => '/api/personnel/sync-ldap',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/personnel',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/personnel/search',
                'roles' => $operational,
            ],
            [
                'methods' => ['PUT'],
                'pattern' => '/api/personnel/{id}/role',
                'roles' => [User::ROLE_ADMIN],
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/personnel/{id}/offboard',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/assets',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/assets',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/assets/import/template',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/assets/export',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/assets/import',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/inventory/import/template',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/inventory/import',
                'roles' => $operational,
            ],
            [
                'methods' => ['PUT'],
                'pattern' => '/api/assets/{id}',
                'roles' => $operational,
            ],
            [
                'methods' => ['POST'],
                'pattern' => '/api/assets/{id}/assign',
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
                'roles' => [User::ROLE_ADMIN],
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/analytics/summary',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/dashboard/stats',
                'roles' => $operational,
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/assets/{id}/tutanak',
                'roles' => [User::ROLE_ADMIN, User::ROLE_USER],
            ],
            [
                'methods' => ['GET'],
                'pattern' => '/api/assets/{id}/history',
                'roles' => [User::ROLE_ADMIN, User::ROLE_USER],
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

    private function normalizePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        return rtrim($path, '/') ?: '/';
    }
}
