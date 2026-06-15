<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\SecurityHeaders;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly bool $isHttps
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        return SecurityHeaders::apply($response, $this->isHttps);
    }
}
