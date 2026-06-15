<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HealthController
{
    /**
     * @param array<string, mixed> $appConfig
     */
    public function __construct(
        private readonly array $appConfig
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = json_encode([
            'status' => 'ok',
            'message' => 'Betech API is up and running',
            'service' => 'Betech ITAM',
            'environment' => $this->appConfig['env'],
        ], JSON_THROW_ON_ERROR);

        $response->getBody()->write($payload);

        return $response->withHeader('Content-Type', 'application/json');
    }
}
