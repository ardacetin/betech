<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Services\AuditChangeFormatter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuditLogController
{
    public function __construct(
        private readonly AuditLog $auditLogModel,
        private readonly AuditChangeFormatter $changeFormatter
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $userId = isset($query['user_id']) ? (int) $query['user_id'] : null;
        $actionType = isset($query['action_type']) ? (string) $query['action_type'] : null;
        $entityType = isset($query['entity_type']) ? (string) $query['entity_type'] : null;
        $dateFrom = isset($query['date_from']) ? (string) $query['date_from'] : null;
        $dateTo = isset($query['date_to']) ? (string) $query['date_to'] : null;
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($query['per_page'] ?? 50)));

        $result = $this->auditLogModel->findFiltered(
            $userId !== null && $userId > 0 ? $userId : null,
            $actionType,
            $entityType,
            $dateFrom,
            $dateTo,
            $page,
            $perPage
        );

        $data = array_map(function (array $row): array {
            $row['summary'] = $this->changeFormatter->formatSummary(
                (string) $row['action_type'],
                (string) $row['entity_type'],
                $row['entity_id'] !== null ? (int) $row['entity_id'] : null,
                is_array($row['old_values']) ? $row['old_values'] : null,
                is_array($row['new_values']) ? $row['new_values'] : null
            );

            return $row;
        }, $result['data']);

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $data,
            'pagination' => $result['pagination'],
            'filters' => [
                'users' => $this->auditLogModel->findDistinctUsers(),
                'action_types' => [
                    AuditLog::ACTION_CREATED,
                    AuditLog::ACTION_UPDATED,
                    AuditLog::ACTION_DELETED,
                    AuditLog::ACTION_LOGIN,
                    AuditLog::ACTION_ASSIGNED,
                    AuditLog::ACTION_RETURNED,
                    AuditLog::ACTION_TRANSFERRED,
                ],
                'entity_types' => [
                    AuditLog::ENTITY_ASSET,
                    AuditLog::ENTITY_TICKET,
                    AuditLog::ENTITY_CATEGORY,
                    AuditLog::ENTITY_SETTING,
                    AuditLog::ENTITY_USER,
                ],
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
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($statusCode);
    }
}
