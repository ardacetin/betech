<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AnalyticsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AnalyticsController
{
    public function __construct(
        private readonly AnalyticsService $analyticsService
    ) {
    }

    public function summary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->analyticsService->getDashboardStats(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(ResponseInterface $response, int $statusCode, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}
