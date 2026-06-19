<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Services\Auth\SessionAuthService;
use App\Services\DatabaseBackupService;
use App\Services\R2BackupStorage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

class BackupController
{
    public function __construct(
        private readonly DatabaseBackupService $backupService,
        private readonly SessionAuthService $sessionAuthService,
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $backups = $this->backupService->listBackups();
        } catch (RuntimeException $exception) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => [
                'backups' => $backups,
                'retention_days' => DatabaseBackupService::RETENTION_DAYS,
                'presigned_url_ttl_minutes' => (int) (R2BackupStorage::PRESIGNED_URL_TTL_SECONDS / 60),
            ],
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $result = $this->backupService->run();

        if (!$result['success']) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => $result['message'],
            ]);
        }

        $this->auditLogger->logFromRequest(
            $request,
            $this->sessionAuthService->userId(),
            AuditLog::ACTION_CREATED,
            AuditLog::ENTITY_SETTING,
            null,
            null,
            [
                'backup_filename' => $result['filename'] ?? null,
                'backup_size_bytes' => $result['size_bytes'] ?? null,
                'backup_storage' => 'r2',
            ]
        );

        try {
            $backups = $this->backupService->listBackups();
        } catch (RuntimeException $exception) {
            return $this->jsonResponse($response, 201, [
                'status' => 'success',
                'message' => $result['message'],
                'data' => [
                    'filename' => $result['filename'] ?? null,
                    'size_bytes' => $result['size_bytes'] ?? null,
                    'deleted_count' => $result['deleted_count'] ?? 0,
                    'backups' => [],
                    'list_error' => $exception->getMessage(),
                ],
            ]);
        }

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('backup_create_success'),
            'data' => [
                'filename' => $result['filename'] ?? null,
                'size_bytes' => $result['size_bytes'] ?? null,
                'deleted_count' => $result['deleted_count'] ?? 0,
                'backups' => $backups,
            ],
        ]);
    }

    public function download(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $filename = basename(trim((string) ($args['filename'] ?? '')));
        $downloadUrl = $this->backupService->createDownloadUrl($filename);

        if ($downloadUrl === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('backup_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => [
                'filename' => $filename,
                'download_url' => $downloadUrl,
                'expires_in_seconds' => R2BackupStorage::PRESIGNED_URL_TTL_SECONDS,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(ResponseInterface $response, int $statusCode, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}
