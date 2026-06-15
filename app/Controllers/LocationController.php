<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Location;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LocationController
{
    public function __construct(
        private readonly Location $locationModel
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locations = array_map(
            function (array $location): array {
                $location['asset_count'] = $this->locationModel->countAssets((int) $location['id']);

                return $location;
            },
            $this->locationModel->findAll()
        );

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $locations,
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('location_invalid_payload'),
            ]);
        }

        $errors = $this->validatePayload($payload, true);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('location_validation_failed'),
                'errors' => $errors,
            ]);
        }

        try {
            $location = $this->locationModel->create(
                (string) $payload['name'],
                array_key_exists('building', $payload) ? (string) $payload['building'] : null,
                array_key_exists('description', $payload) ? (string) $payload['description'] : null
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('location_create_error'),
            ]);
        }

        $location['asset_count'] = 0;

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('location_create_success'),
            'data' => $location,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $locationId = (int) ($args['id'] ?? 0);

        if ($locationId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('location_invalid_id'),
            ]);
        }

        $existing = $this->locationModel->findById($locationId);

        if ($existing === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('location_not_found'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('location_invalid_payload'),
            ]);
        }

        $errors = $this->validatePayload($payload, false);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('location_validation_failed'),
                'errors' => $errors,
            ]);
        }

        $name = array_key_exists('name', $payload)
            ? (string) $payload['name']
            : (string) ($existing['name'] ?? '');

        $building = array_key_exists('building', $payload)
            ? (string) $payload['building']
            : (string) ($existing['building'] ?? '');

        $description = array_key_exists('description', $payload)
            ? (string) $payload['description']
            : (string) ($existing['description'] ?? '');

        try {
            $location = $this->locationModel->update($locationId, $name, $building, $description);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('location_update_error'),
            ]);
        }

        if ($location === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('location_not_found'),
            ]);
        }

        $location['asset_count'] = $this->locationModel->countAssets($locationId);

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('location_update_success'),
            'data' => $location,
        ]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $locationId = (int) ($args['id'] ?? 0);

        if ($locationId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('location_invalid_id'),
            ]);
        }

        if ($this->locationModel->findById($locationId) === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('location_not_found'),
            ]);
        }

        $assetCount = $this->locationModel->countAssets($locationId);

        if ($assetCount > 0) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('location_delete_in_use'),
                'asset_count' => $assetCount,
            ]);
        }

        try {
            $deleted = $this->locationModel->delete($locationId);
        } catch (\RuntimeException) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('location_delete_in_use'),
            ]);
        }

        if (!$deleted) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('location_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('location_delete_success'),
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

        if ($isCreate || array_key_exists('name', $payload)) {
            if (trim((string) ($payload['name'] ?? '')) === '') {
                $errors['name'][] = __('location_name_required');
            }
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
