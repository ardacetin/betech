<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HealthController
{
    /**
     * @param array<string, mixed> $appConfig
     */
    public function __construct(
        private readonly array $appConfig,
        private readonly Asset $assetModel
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $assets = $this->assetModel->findAll();

        $payload = json_encode([
            'status' => 'ok',
            'message' => 'Betech API is up and running',
            'service' => 'Betech ITAM',
            'environment' => $this->appConfig['env'],
            'count' => count($assets),
            'assets' => $assets,
        ], JSON_THROW_ON_ERROR);

        $response->getBody()->write($payload);

        return $response->withHeader('Content-Type', 'application/json');
    }
}
