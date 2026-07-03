<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\NetworkPortMappingService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class NetworkPortMappingController
{
    public function __construct(
        private readonly NetworkPortMappingService $networkPortMappingService,
    ) {
    }

    public function switches(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->networkPortMappingService->listSwitchAssets(),
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $sourceType = trim((string) ($query['source_type'] ?? ''));
        $sourceId = (int) ($query['source_id'] ?? 0);

        if ($sourceType === '' || $sourceId <= 0) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('network_port_mapping_invalid_source'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->networkPortMappingService->findForSource($sourceType, $sourceId),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
