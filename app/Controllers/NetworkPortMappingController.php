<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\NetworkPortMappingService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

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

    public function directory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $data = $this->networkPortMappingService->listSwitchDirectory();
        } catch (\Throwable) {
            $data = [];
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function matrix(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $switchId = (int) ($request->getQueryParams()['switch_id'] ?? 0);

        if ($switchId <= 0) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('network_port_mapping_invalid_switch'),
            ]);
        }

        $matrix = $this->networkPortMappingService->getSwitchPortMatrix($switchId);

        if ($matrix === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('network_port_mapping_invalid_switch'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $matrix,
        ]);
    }

    public function searchAssets(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = trim((string) ($request->getQueryParams()['q'] ?? ''));

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->networkPortMappingService->searchConnectableAssets($query),
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

    public function assign(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->parseJsonBody($request);

        $switchId = (int) ($body['switch_asset_id'] ?? 0);
        $portNumber = trim((string) ($body['port_number'] ?? ''));
        $description = trim((string) ($body['description'] ?? ''));

        if ($switchId <= 0 || $portNumber === '') {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('switch_port_assign_invalid_payload'),
            ]);
        }

        try {
            $this->networkPortMappingService->savePortDescription($switchId, $portNumber, $description);
        } catch (RuntimeException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => $description === ''
                ? __('switch_port_disconnect_success')
                : __('switch_port_assign_success'),
            'data' => $this->networkPortMappingService->getPortConfigContext($switchId, $portNumber),
        ]);
    }

    public function disconnect(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->parseJsonBody($request);

        $switchId = (int) ($body['switch_asset_id'] ?? 0);
        $portNumber = trim((string) ($body['port_number'] ?? ''));

        if ($switchId <= 0 || $portNumber === '') {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('switch_port_disconnect_invalid_payload'),
            ]);
        }

        try {
            $this->networkPortMappingService->disconnectPort($switchId, $portNumber);
        } catch (RuntimeException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('switch_port_disconnect_success'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonBody(ServerRequestInterface $request): array
    {
        $raw = (string) $request->getBody();

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
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
