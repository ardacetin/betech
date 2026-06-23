<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AssetHistory;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Services\Auth\SessionAuthService;
use App\Services\InventoryImportService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

class InventoryImportController
{
    public function __construct(
        private readonly InventoryImportService $inventoryImportService,
        private readonly AssetHistory $assetHistoryModel,
        private readonly SessionAuthService $sessionAuthService,
        private readonly AuditLogger $auditLogger,
        private readonly bool $exposeDebugDetails = false,
    ) {
    }

    public function template(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $csv = InventoryImportService::templateCsvContent();

            $response->getBody()->write("\xEF\xBB\xBF" . $csv);

            return $response
                ->withHeader('Content-Type', 'text/csv; charset=utf-8')
                ->withHeader('Content-Disposition', 'attachment; filename="glpi_asset_import_template.csv"');
        } catch (Throwable $exception) {
            return $this->errorResponse($response, 500, __('import_template_error'), $exception);
        }
    }

    public function import(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->importFromExcel($request, $response);
    }

    public function importFromExcel(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $file = $this->resolveUploadedFile($request);

            if ($file === null) {
                return $this->jsonResponse($response, 400, [
                    'status' => 'error',
                    'message' => __('import_file_missing'),
                ]);
            }

            if ($file->getError() !== UPLOAD_ERR_OK) {
                return $this->jsonResponse($response, 400, [
                    'status' => 'error',
                    'message' => __('import_file_upload_error'),
                ]);
            }

            $originalFilename = $file->getClientFilename() ?? 'import.csv';
            $contents = (string) $file->getStream()->getContents();
            $columnMapping = $this->resolveColumnMapping($request);
            $result = $this->inventoryImportService->importFromUploadedFile($contents, $originalFilename, $columnMapping);

            $actorUserId = $this->sessionAuthService->userId();

            foreach ($result['created_assets'] as $assetId) {
                $this->logAssetHistory(
                    $request,
                    $assetId,
                    'created',
                    $actorUserId,
                    __('asset_history_imported_inventory')
                );
            }

            foreach ($result['updated_assets'] as $assetId) {
                $this->logAssetHistory(
                    $request,
                    $assetId,
                    'updated',
                    $actorUserId,
                    __('asset_history_updated_inventory_import')
                );
            }

            $imported = (int) $result['imported'];
            $updated = (int) $result['updated'];
            $failed = (int) $result['failed'];
            $processed = $imported + $updated;

            if ($processed === 0 && $failed > 0) {
                return $this->jsonResponse($response, 422, [
                    'status' => 'error',
                    'message' => __('import_all_failed'),
                    'data' => $result,
                ]);
            }

            if ($failed > 0) {
                $message = sprintf(__('inventory_import_partial_success'), $imported, $updated, $failed);
            } elseif ($updated > 0 && $imported > 0) {
                $message = sprintf(__('inventory_import_mixed_success'), $imported, $updated);
            } elseif ($updated > 0) {
                $message = sprintf(__('inventory_import_update_success'), $updated);
            } else {
                $message = sprintf(__('import_success'), $imported);
            }

            return $this->jsonResponse($response, 200, [
                'status' => 'success',
                'message' => $message,
                'data' => $result,
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($response, 500, __('inventory_import_failed'), $exception);
        }
    }

    public function preview(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $file = $this->resolveUploadedFile($request);

            if ($file === null) {
                return $this->jsonResponse($response, 400, [
                    'status' => 'error',
                    'message' => __('import_file_missing'),
                ]);
            }

            if ($file->getError() !== UPLOAD_ERR_OK) {
                return $this->jsonResponse($response, 400, [
                    'status' => 'error',
                    'message' => __('import_file_upload_error'),
                ]);
            }

            $originalFilename = $file->getClientFilename() ?? 'import.csv';
            $contents = (string) $file->getStream()->getContents();
            $preview = $this->inventoryImportService->buildImportMappingPreview($contents, $originalFilename);

            return $this->jsonResponse($response, 200, [
                'status' => 'success',
                'data' => $preview,
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($response, 422, __('import_csv_invalid_headers'), $exception);
        }
    }

    /**
     * @return array<int, string>|null
     */
    private function resolveColumnMapping(ServerRequestInterface $request): ?array
    {
        $parsedBody = $request->getParsedBody();
        $rawMapping = null;

        if (is_array($parsedBody)) {
            $rawMapping = $parsedBody['column_mapping'] ?? null;
        }

        if ($rawMapping === null || $rawMapping === '') {
            return null;
        }

        if (is_string($rawMapping)) {
            $decoded = json_decode($rawMapping, true);

            return is_array($decoded) ? $decoded : null;
        }

        return is_array($rawMapping) ? $rawMapping : null;
    }

    private function resolveUploadedFile(ServerRequestInterface $request): ?UploadedFileInterface
    {
        $uploadedFiles = $request->getUploadedFiles();

        foreach (['file', 'csv', 'spreadsheet'] as $key) {
            $candidate = $uploadedFiles[$key] ?? null;

            if ($candidate instanceof UploadedFileInterface) {
                return $candidate;
            }
        }

        return null;
    }

    private function logAssetHistory(
        ServerRequestInterface $request,
        int $assetId,
        string $action,
        ?int $actorUserId,
        string $note
    ): void {
        $this->assetHistoryModel->log(
            $assetId,
            $action,
            $actorUserId,
            null,
            $note
        );

        $this->auditLogger->logFromRequest(
            $request,
            $actorUserId,
            $action === 'created' ? AuditLog::ACTION_CREATED : AuditLog::ACTION_UPDATED,
            AuditLog::ENTITY_ASSET,
            $assetId,
            null,
            ['note' => $note]
        );
    }

    private function errorResponse(
        ResponseInterface $response,
        int $statusCode,
        string $fallbackMessage,
        Throwable $exception
    ): ResponseInterface {
        $payload = [
            'status' => 'error',
            'message' => $this->exposeDebugDetails ? $exception->getMessage() : $fallbackMessage,
        ];

        if ($this->exposeDebugDetails) {
            $payload['debug'] = [
                'type' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        return $this->jsonResponse($response, $statusCode, $payload);
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
