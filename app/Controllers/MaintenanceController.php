<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\MaintenanceLog;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MaintenanceController
{
    public function __construct(
        private readonly MaintenanceLog $maintenanceLogModel
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $activeOnly = isset($query['active']) && (string) $query['active'] === '1';

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $activeOnly
                ? $this->maintenanceLogModel->findActive()
                : $this->maintenanceLogModel->findAll(),
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $logId = (int) ($args['id'] ?? 0);

        if ($logId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('maintenance_invalid_id'),
            ]);
        }

        $log = $this->maintenanceLogModel->findById($logId);

        if ($log === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('maintenance_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $log,
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('maintenance_invalid_payload'),
            ]);
        }

        $errors = $this->validatePayload($payload, true);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('maintenance_validation_failed'),
                'errors' => $errors,
            ]);
        }

        try {
            $log = $this->maintenanceLogModel->create(
                (int) $payload['asset_id'],
                (string) $payload['provider_name'],
                (string) $payload['issue_description'],
                (string) ($payload['sent_date'] ?? date('Y-m-d')),
                (string) ($payload['status'] ?? MaintenanceLog::STATUS_UNDER_REPAIR),
                array_key_exists('repair_cost', $payload) ? $payload['repair_cost'] : null,
                array_key_exists('return_date', $payload) ? ($payload['return_date'] ?? null) : null
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('maintenance_create_error'),
            ]);
        }

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('maintenance_create_success'),
            'data' => $log,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $logId = (int) ($args['id'] ?? 0);

        if ($logId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('maintenance_invalid_id'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('maintenance_invalid_payload'),
            ]);
        }

        try {
            $log = $this->maintenanceLogModel->update($logId, $payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('maintenance_update_error'),
            ]);
        }

        if ($log === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('maintenance_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('maintenance_update_success'),
            'data' => $log,
        ]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $logId = (int) ($args['id'] ?? 0);

        if ($logId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('maintenance_invalid_id'),
            ]);
        }

        if (!$this->maintenanceLogModel->delete($logId)) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('maintenance_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('maintenance_delete_success'),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePayload(ServerRequestInterface $request): ?array
    {
        $parsedBody = $request->getParsedBody();

        if (is_array($parsedBody)) {
            return $parsedBody;
        }

        $rawBody = (string) $request->getBody();

        if ($rawBody === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, list<string>>
     */
    private function validatePayload(array $payload, bool $isCreate): array
    {
        $errors = [];

        if ($isCreate && (int) ($payload['asset_id'] ?? 0) <= 0) {
            $errors['asset_id'][] = __('maintenance_asset_required');
        }

        if ($isCreate && trim((string) ($payload['provider_name'] ?? '')) === '') {
            $errors['provider_name'][] = __('maintenance_provider_required');
        }

        if ($isCreate && trim((string) ($payload['issue_description'] ?? '')) === '') {
            $errors['issue_description'][] = __('maintenance_issue_required');
        }

        return $errors;
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
