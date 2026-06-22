<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Auth\SessionAuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $exemptPaths
     */
    public function __construct(
        private readonly SessionAuthService $sessionAuthService,
        private readonly array $exemptPaths = []
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());

        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            return $handler->handle($request);
        }

        // Inventory/license/consumable filter requests use GET query strings and are not CSRF-checked.

        $path = $request->getUri()->getPath();

        if ($this->isExemptPath($path)) {
            return $handler->handle($request);
        }

        $this->sessionAuthService->ensureSessionStarted();

        $token = trim($request->getHeaderLine('X-CSRF-TOKEN'));

        if ($token === '') {
            $parsedBody = $request->getParsedBody();

            if (is_array($parsedBody)) {
                $token = trim((string) (
                    $parsedBody['_csrf']
                    ?? $parsedBody['csrf_token']
                    ?? $parsedBody['csrf_value']
                    ?? ''
                ));
            }
        }

        if ($token === '') {
            $queryParams = $request->getQueryParams();

            if (is_array($queryParams)) {
                $token = trim((string) (
                    $queryParams['_csrf']
                    ?? $queryParams['csrf_token']
                    ?? $queryParams['csrf_value']
                    ?? ''
                ));
            }
        }

        if (!$this->sessionAuthService->validateCsrfToken($token)) {
            $response = new Response(403);
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => 'Geçersiz veya eksik CSRF belirteci.',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        return $handler->handle($request);
    }

    private function isExemptPath(string $path): bool
    {
        foreach ($this->exemptPaths as $pattern) {
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
}
