<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\TicketCategory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TicketCategoryController
{
    public function __construct(
        private readonly TicketCategory $ticketCategoryModel
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $categories = array_map(
            function (array $category): array {
                $category['ticket_count'] = $this->ticketCategoryModel->countTickets((int) $category['id']);

                return $category;
            },
            $this->ticketCategoryModel->findAll()
        );

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ticket_category_invalid_payload'),
            ]);
        }

        $errors = $this->validatePayload($payload, true);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('ticket_category_validation_failed'),
                'errors' => $errors,
            ]);
        }

        try {
            $category = $this->ticketCategoryModel->create(
                (string) $payload['name'],
                (string) ($payload['color_code'] ?? '#6366f1')
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('ticket_category_create_error'),
            ]);
        }

        $category['ticket_count'] = 0;

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('ticket_category_create_success'),
            'data' => $category,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $categoryId = (int) ($args['id'] ?? 0);

        if ($categoryId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ticket_category_invalid_id'),
            ]);
        }

        $existing = $this->ticketCategoryModel->findById($categoryId);

        if ($existing === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ticket_category_not_found'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ticket_category_invalid_payload'),
            ]);
        }

        $errors = $this->validatePayload($payload, false);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('ticket_category_validation_failed',
                ),
                'errors' => $errors,
            ]);
        }

        $name = array_key_exists('name', $payload)
            ? (string) $payload['name']
            : (string) ($existing['name'] ?? '');

        $colorCode = array_key_exists('color_code', $payload)
            ? (string) $payload['color_code']
            : (string) ($existing['color_code'] ?? '#6366f1');

        try {
            $category = $this->ticketCategoryModel->update($categoryId, $name, $colorCode);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('ticket_category_update_error'),
            ]);
        }

        if ($category === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ticket_category_not_found'),
            ]);
        }

        $category['ticket_count'] = $this->ticketCategoryModel->countTickets($categoryId);

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('ticket_category_update_success'),
            'data' => $category,
        ]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $categoryId = (int) ($args['id'] ?? 0);

        if ($categoryId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ticket_category_invalid_id'),
            ]);
        }

        if ($this->ticketCategoryModel->findById($categoryId) === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ticket_category_not_found'),
            ]);
        }

        if (!$this->ticketCategoryModel->delete($categoryId)) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ticket_category_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('ticket_category_delete_success'),
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
                $errors['name'][] = __('ticket_category_name_required');
            }
        }

        if (array_key_exists('color_code', $payload)) {
            $color = trim((string) $payload['color_code']);

            if ($color !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $errors['color_code'][] = __('ticket_category_color_invalid');
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
