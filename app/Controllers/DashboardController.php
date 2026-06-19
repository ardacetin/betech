<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AssetHistory;
use App\Models\AuditLog;
use App\Models\IpNetwork;
use App\Services\AnalyticsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DashboardController
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly AssetHistory $assetHistoryModel,
        private readonly IpNetwork $ipNetworkModel,
        private readonly AuditLog $auditLogModel
    ) {
    }

    public function stats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $stats = $this->analyticsService->getDashboardStats();
        $stats['recent_activities'] = $this->assetHistoryModel->findRecent(5);
        $stats['recent_logs'] = $this->auditLogModel->findRecent(5);
        $stats['infrastructure'] = $this->buildInfrastructureStats();

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Aggregate IP pool allocation across every managed network.
     *
     * @return array{ip_capacity: int, ip_used: int, ip_available: int, ip_utilization: int, network_count: int}
     */
    private function buildInfrastructureStats(): array
    {
        $networks = $this->ipNetworkModel->findAll();

        $capacity = 0;
        $used = 0;

        foreach ($networks as $network) {
            $capacity += (int) ($network['capacity_ips'] ?? 0);
            $used += (int) ($network['used_ips'] ?? 0);
        }

        $used = min($used, $capacity);

        return [
            'ip_capacity' => $capacity,
            'ip_used' => $used,
            'ip_available' => max(0, $capacity - $used),
            'ip_utilization' => $capacity > 0 ? (int) round(($used / $capacity) * 100) : 0,
            'network_count' => count($networks),
        ];
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
