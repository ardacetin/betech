<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\QualityDocument;
use App\Services\AppLogger;
use App\Services\AuditLogger;
use App\Services\Auth\SessionAuthService;
use App\Services\QualityDocumentStorageService;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

class QualityDocumentController
{
    public function __construct(
        private readonly QualityDocument $qualityDocumentModel,
        private readonly QualityDocumentStorageService $storageService,
        private readonly SessionAuthService $sessionAuthService,
        private readonly AuditLogger $auditLogger,
        private readonly AppLogger $logger
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            return $this->jsonResponse($response, 200, [
                'status' => 'success',
                'data' => $this->qualityDocumentModel->findAll(),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('quality_documents.index.failed', [
                'message' => $exception->getMessage(),
            ]);

            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('quality_document_fetch_error'),
            ]);
        }
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $title = trim((string) ($request->getParsedBody()['title'] ?? $_POST['title'] ?? ''));

        if ($title === '') {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('quality_document_title_required'),
            ]);
        }

        $file = $this->resolveUploadedFile($request);

        if ($file === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('quality_document_file_missing'),
            ]);
        }

        try {
            $stored = $this->storageService->storeUploadedFile($file);
            $document = $this->qualityDocumentModel->create(
                $title,
                $stored['original_filename'],
                $stored['relative_path'],
                $stored['file_size'],
                $this->sessionAuthService->userId()
            );
        } catch (InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            if (isset($stored['absolute_path']) && is_file($stored['absolute_path'])) {
                unlink($stored['absolute_path']);
            }

            $this->logger->error('quality_documents.store.failed', [
                'message' => $exception->getMessage(),
            ]);

            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('quality_document_upload_error'),
            ]);
        }

        $this->auditLogger->logFromRequest(
            $request,
            $this->sessionAuthService->userId(),
            AuditLog::ACTION_CREATED,
            AuditLog::ENTITY_QUALITY_DOCUMENT,
            (int) ($document['id'] ?? 0),
            null,
            [
                'title' => $document['title'] ?? null,
                'filename' => $document['filename'] ?? null,
            ]
        );

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('quality_document_upload_success'),
            'data' => $document,
        ]);
    }

    public function download(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $documentId = (int) ($args['id'] ?? 0);

        if ($documentId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('quality_document_invalid_id'),
            ]);
        }

        try {
            $document = $this->qualityDocumentModel->findById($documentId);
        } catch (Throwable $exception) {
            $this->logger->error('quality_documents.download.lookup_failed', [
                'document_id' => $documentId,
                'message' => $exception->getMessage(),
            ]);

            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('quality_document_fetch_error'),
            ]);
        }

        if ($document === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('quality_document_not_found'),
            ]);
        }

        try {
            $absolutePath = $this->storageService->resolveAbsolutePath((string) ($document['file_path'] ?? ''));
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        $downloadName = basename((string) ($document['filename'] ?? 'document'));
        $mimeType = mime_content_type($absolutePath) ?: 'application/octet-stream';
        $stream = fopen($absolutePath, 'rb');

        if ($stream === false) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('quality_document_download_error'),
            ]);
        }

        $response->getBody()->write((string) stream_get_contents($stream));
        fclose($stream);

        return $response
            ->withHeader('Content-Type', $mimeType)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $this->sanitizeDownloadFilename($downloadName) . '"')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->withHeader('Pragma', 'no-cache');
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $documentId = (int) ($args['id'] ?? 0);

        if ($documentId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('quality_document_invalid_id'),
            ]);
        }

        try {
            $document = $this->qualityDocumentModel->findById($documentId);
        } catch (Throwable $exception) {
            $this->logger->error('quality_documents.destroy.lookup_failed', [
                'document_id' => $documentId,
                'message' => $exception->getMessage(),
            ]);

            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('quality_document_fetch_error'),
            ]);
        }

        if ($document === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('quality_document_not_found'),
            ]);
        }

        try {
            $deleted = $this->qualityDocumentModel->deleteById($documentId);
        } catch (Throwable $exception) {
            $this->logger->error('quality_documents.destroy.failed', [
                'document_id' => $documentId,
                'message' => $exception->getMessage(),
            ]);

            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('quality_document_delete_error'),
            ]);
        }

        if (!$deleted) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('quality_document_not_found'),
            ]);
        }

        $this->auditLogger->logFromRequest(
            $request,
            $this->sessionAuthService->userId(),
            AuditLog::ACTION_DELETED,
            AuditLog::ENTITY_QUALITY_DOCUMENT,
            $documentId,
            [
                'title' => $document['title'] ?? null,
                'filename' => $document['filename'] ?? null,
            ],
            null
        );

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('quality_document_delete_success'),
        ]);
    }

    private function resolveUploadedFile(ServerRequestInterface $request): ?UploadedFileInterface
    {
        $uploadedFiles = $request->getUploadedFiles();

        $file = $uploadedFiles['file'] ?? $uploadedFiles['document'] ?? null;

        return $file instanceof UploadedFileInterface ? $file : null;
    }

    private function sanitizeDownloadFilename(string $filename): string
    {
        $sanitized = preg_replace('/[^\w.\-() ]+/u', '_', $filename) ?? 'document';

        return $sanitized !== '' ? $sanitized : 'document';
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
