<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\KnowledgeBaseArticle;
use App\Services\Auth\SessionAuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class KnowledgeBaseController
{
    public function __construct(
        private readonly KnowledgeBaseArticle $knowledgeBaseArticleModel,
        private readonly SessionAuthService $sessionAuthService
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->knowledgeBaseArticleModel->findAll(),
        ]);
    }

    public function published(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->knowledgeBaseArticleModel->findPublished(),
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $articleId = (int) ($args['id'] ?? 0);

        if ($articleId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('kb_invalid_id'),
            ]);
        }

        $article = $this->knowledgeBaseArticleModel->findById($articleId);

        if ($article === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('kb_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $article,
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('kb_invalid_payload'),
            ]);
        }

        $errors = $this->validatePayload($payload, true);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('kb_validation_failed'),
                'errors' => $errors,
            ]);
        }

        try {
            $authorId = $this->sessionAuthService->userId();
            $article = $this->knowledgeBaseArticleModel->create(
                (string) $payload['title'],
                (string) $payload['content'],
                $this->normalizePublished($payload['is_published'] ?? false),
                $authorId !== null && $authorId > 0 ? $authorId : null
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('kb_create_error'),
            ]);
        }

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('kb_create_success'),
            'data' => $article,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $articleId = (int) ($args['id'] ?? 0);

        if ($articleId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('kb_invalid_id'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('kb_invalid_payload'),
            ]);
        }

        $errors = $this->validatePayload($payload, false);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('kb_validation_failed'),
                'errors' => $errors,
            ]);
        }

        try {
            $updatePayload = [];

            if (array_key_exists('title', $payload)) {
                $updatePayload['title'] = (string) $payload['title'];
            }

            if (array_key_exists('content', $payload)) {
                $updatePayload['content'] = (string) $payload['content'];
            }

            if (array_key_exists('is_published', $payload)) {
                $updatePayload['is_published'] = $this->normalizePublished($payload['is_published']);
            }

            $article = $this->knowledgeBaseArticleModel->update($articleId, $updatePayload);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('kb_update_error'),
            ]);
        }

        if ($article === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('kb_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('kb_update_success'),
            'data' => $article,
        ]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $articleId = (int) ($args['id'] ?? 0);

        if ($articleId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('kb_invalid_id'),
            ]);
        }

        try {
            $deleted = $this->knowledgeBaseArticleModel->delete($articleId);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('kb_delete_error'),
            ]);
        }

        if (!$deleted) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('kb_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('kb_delete_success'),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePayload(ServerRequestInterface $request): ?array
    {
        $parsed = $request->getParsedBody();

        if (is_array($parsed) && $parsed !== []) {
            return $parsed;
        }

        $raw = (string) $request->getBody();

        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

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

        if ($isCreate && trim((string) ($payload['title'] ?? '')) === '') {
            $errors['title'][] = __('kb_title_required');
        }

        if ($isCreate && trim((string) ($payload['content'] ?? '')) === '') {
            $errors['content'][] = __('kb_content_required');
        }

        if (array_key_exists('title', $payload) && trim((string) $payload['title']) === '') {
            $errors['title'][] = __('kb_title_required');
        }

        if (array_key_exists('content', $payload) && trim((string) $payload['content']) === '') {
            $errors['content'][] = __('kb_content_required');
        }

        return $errors;
    }

    private function normalizePublished(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
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
