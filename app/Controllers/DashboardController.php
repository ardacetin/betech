<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AssetHistory;
use App\Services\AnalyticsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DashboardController
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly AssetHistory $assetHistoryModel
    ) {
    }

    public function stats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $stats = $this->analyticsService->getDashboardStats();
        $stats['recent_activities'] = $this->assetHistoryModel->findRecent(5);

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(ResponseInterface $response, int $statusCode, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($statusCode);
    }
}
