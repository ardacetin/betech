<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Announcement;
use App\Services\AppLogger;
use App\Services\Auth\SessionAuthService;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class AnnouncementController
{
    public function __construct(
        private readonly Announcement $announcementModel,
        private readonly SessionAuthService $sessionAuthService,
        private readonly AppLogger $logger
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            return $this->jsonResponse($response, 200, [
                'status' => 'success',
                'data' => $this->announcementModel->findAll(),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('announcements.index.failed', ['message' => $exception->getMessage()]);

            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('announcement_fetch_error'),
            ]);
        }
    }

    public function published(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            return $this->jsonResponse($response, 200, [
                'status' => 'success',
                'data' => $this->announcementModel->findPublished(),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('announcements.published.failed', ['message' => $exception->getMessage()]);

            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('announcement_fetch_error'),
            ]);
        }
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->payload($request);

        try {
            $row = $this->announcementModel->create($payload, $this->sessionAuthService->userId());

            return $this->jsonResponse($response, 201, [
                'status' => 'success',
                'message' => __('announcement_create_success'),
                'data' => $row,
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('announcements.store.failed', ['message' => $exception->getMessage()]);

            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('announcement_create_error'),
            ]);
        }
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) ($args['id'] ?? 0);
        $payload = $this->payload($request);

        try {
            $row = $this->announcementModel->update($id, $payload);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('announcements.update.failed', ['message' => $exception->getMessage()]);

            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('announcement_update_error'),
            ]);
        }

        if ($row === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('announcement_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('announcement_update_success'),
            'data' => $row,
        ]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) ($args['id'] ?? 0);

        try {
            if (!$this->announcementModel->deleteById($id)) {
                return $this->jsonResponse($response, 404, [
                    'status' => 'error',
                    'message' => __('announcement_not_found'),
                ]);
            }
        } catch (Throwable $exception) {
            $this->logger->error('announcements.destroy.failed', ['message' => $exception->getMessage()]);

            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('announcement_delete_error'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('announcement_delete_success'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ServerRequestInterface $request): array
    {
        $parsed = $request->getParsedBody();

        if (is_array($parsed) && $parsed !== []) {
            return $parsed;
        }

        $rawBody = trim((string) $request->getBody());

        if ($rawBody === '') {
            return is_array($parsed) ? $parsed : [];
        }

        $decoded = json_decode($rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}
