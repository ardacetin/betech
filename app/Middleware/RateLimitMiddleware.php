<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\ClientIpResolver;
use App\Services\LoginAttemptService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class RateLimitMiddleware implements MiddlewareInterface
{
    private const RATE_LIMIT_MESSAGE = 'Çok fazla hatalı giriş denemesi. Lütfen 15 dakika sonra tekrar deneyin.';

    /**
     * @param list<string> $protectedPaths
     */
    public function __construct(
        private readonly LoginAttemptService $loginAttemptService,
        private readonly ClientIpResolver $clientIpResolver,
        private readonly array $protectedPaths = ['/api/login', '/login']
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $path = $request->getUri()->getPath();

        if ($method !== 'POST' || !$this->isProtectedPath($path)) {
            return $handler->handle($request);
        }

        $ipAddress = $this->clientIpResolver->resolveFromRequest($request);

        if ($this->loginAttemptService->isRateLimited($ipAddress)) {
            return $this->rateLimitResponse($path);
        }

        return $handler->handle($request);
    }

    private function isProtectedPath(string $path): bool
    {
        return in_array($path, $this->protectedPaths, true);
    }

    private function rateLimitResponse(string $path): ResponseInterface
    {
        if ($path === '/login') {
            $query = http_build_query(['error' => 'login_rate_limited']);

            return (new Response(302))->withHeader('Location', '/login?' . $query);
        }

        $response = new Response(429);
        $response->getBody()->write(json_encode([
            'error' => self::RATE_LIMIT_MESSAGE,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
