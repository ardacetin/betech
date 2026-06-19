<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Services\EndUserContextService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class EndUserController
{
    public function __construct(
        private readonly Asset $assetModel,
        private readonly EndUserContextService $endUserContextService
    ) {
    }

    public function assets(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->endUserContextService->isEndUser()) {
            return $this->jsonResponse($response, 403, [
                'status' => 'error',
                'message' => __('portal_access_denied'),
            ]);
        }

        $personnelId = $this->endUserContextService->resolvePersonnelId();

        if ($personnelId === null) {
            return $this->jsonResponse($response, 200, [
                'status' => 'success',
                'data' => [],
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->assetModel->findForDashboardByPersonnelId($personnelId),
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
