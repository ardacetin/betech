<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Consumable;
use App\Models\Location;
use App\Services\ConsumableFilterSchemaService;
use App\Services\ListPagination;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ConsumableController
{
    public function __construct(
        private readonly Consumable $consumableModel,
        private readonly Location $locationModel,
        private readonly ConsumableFilterSchemaService $consumableFilterSchemaService,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locations = $this->locationModel->findAll();
        $filterDefinitions = $this->consumableFilterSchemaService->buildDefinitions($locations);
        $filterDefinitions = $this->consumableFilterSchemaService->resolveOptions(
            $filterDefinitions,
            $this->consumableModel,
            $locations
        );
        $activeFilters = $this->consumableFilterSchemaService->parseRequestFilters($request->getQueryParams());
        $page = ListPagination::parsePage($request->getQueryParams());
        $result = $this->consumableModel->findPaginated($page, $activeFilters, $filterDefinitions);

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $result['data'],
            'pagination' => $result['pagination'],
            'meta' => [
                'total' => $result['pagination']['total'],
                'filters' => $activeFilters,
            ],
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $consumableId = (int) ($args['id'] ?? 0);

        if ($consumableId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('consumable_invalid_id'),
            ]);
        }

        $consumable = $this->consumableModel->findById($consumableId);

        if ($consumable === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('consumable_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $consumable,
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('consumable_invalid_payload'),
            ]);
        }

        $errors = $this->validatePayload($payload, true);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('consumable_validation_failed'),
                'errors' => $errors,
            ]);
        }

        try {
            $consumable = $this->consumableModel->create(
                (string) $payload['name'],
                (int) ($payload['quantity'] ?? 0),
                (int) ($payload['min_stock_level'] ?? 0),
                $this->normalizeLocationId($payload['location_id'] ?? null)
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('consumable_create_error'),
            ]);
        }

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('consumable_create_success'),
            'data' => $consumable,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $consumableId = (int) ($args['id'] ?? 0);

        if ($consumableId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('consumable_invalid_id'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('consumable_invalid_payload'),
            ]);
        }

        $errors = $this->validatePayload($payload, false);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('consumable_validation_failed'),
                'errors' => $errors,
            ]);
        }

        try {
            $consumable = $this->consumableModel->update($consumableId, $payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('consumable_update_error'),
            ]);
        }

        if ($consumable === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('consumable_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('consumable_update_success'),
            'data' => $consumable,
        ]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $consumableId = (int) ($args['id'] ?? 0);

        if ($consumableId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('consumable_invalid_id'),
            ]);
        }

        if (!$this->consumableModel->delete($consumableId)) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('consumable_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('consumable_delete_success'),
        ]);
    }

    public function checkout(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->adjustQuantity($request, $response, $args, 'checkout');
    }

    public function restock(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->adjustQuantity($request, $response, $args, 'restock');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function adjustQuantity(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
        string $mode
    ): ResponseInterface {
        $consumableId = (int) ($args['id'] ?? 0);

        if ($consumableId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('consumable_invalid_id'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('consumable_invalid_payload'),
            ]);
        }

        $quantity = (int) ($payload['quantity'] ?? 0);

        if ($quantity < 1) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('consumable_adjust_quantity_invalid'),
            ]);
        }

        try {
            $consumable = $mode === 'checkout'
                ? $this->consumableModel->checkout($consumableId, $quantity)
                : $this->consumableModel->restock($consumableId, $quantity);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\RuntimeException $exception) {
            $status = $exception->getMessage() === __('consumable_not_found') ? 404 : 422;

            return $this->jsonResponse($response, $status, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => $mode === 'checkout'
                    ? __('consumable_checkout_error')
                    : __('consumable_restock_error'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => $mode === 'checkout'
                ? __('consumable_checkout_success')
                : __('consumable_restock_success'),
            'data' => $consumable,
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

        if ($isCreate && trim((string) ($payload['name'] ?? '')) === '') {
            $errors['name'][] = __('consumable_name_required');
        }

        if (array_key_exists('name', $payload) && trim((string) $payload['name']) === '') {
            $errors['name'][] = __('consumable_name_required');
        }

        if (array_key_exists('quantity', $payload) && (int) $payload['quantity'] < 0) {
            $errors['quantity'][] = __('consumable_quantity_invalid');
        }

        if (array_key_exists('min_stock_level', $payload) && (int) $payload['min_stock_level'] < 0) {
            $errors['min_stock_level'][] = __('consumable_min_stock_invalid');
        }

        return $errors;
    }

    /**
     * @param mixed $locationId
     */
    private function normalizeLocationId(mixed $locationId): ?int
    {
        if ($locationId === null || $locationId === '') {
            return null;
        }

        $normalized = (int) $locationId;

        return $normalized > 0 ? $normalized : null;
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
