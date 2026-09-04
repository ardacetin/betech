<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Ticket;
use App\Models\TodoCard;
use App\Models\User;
use App\Services\Auth\SessionAuthService;
use App\Services\Mail\TicketNotificationService;
use App\Services\Mail\TodoNotificationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TodoController
{
    public function __construct(
        private readonly TodoCard $todoCardModel,
        private readonly Ticket $ticketModel,
        private readonly User $userModel,
        private readonly SessionAuthService $sessionAuthService,
        private readonly TodoNotificationService $todoNotificationService,
        private readonly TicketNotificationService $ticketNotificationService
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $archived = filter_var($query['archived'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->todoCardModel->findBoard($archived),
            'users' => $this->userModel->findOperationalUsers(),
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('todo_invalid_payload'),
            ]);
        }

        try {
            $card = $this->todoCardModel->create($payload, $this->sessionAuthService->userId());
            $this->safeNotifyCreated($card);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('todo_create_error'),
            ]);
        }

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('todo_create_success'),
            'data' => $card,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $cardId = (int) ($args['id'] ?? 0);
        $payload = $this->resolvePayload($request);

        if ($cardId <= 0 || $payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('todo_invalid_payload'),
            ]);
        }

        $existing = $this->todoCardModel->findById($cardId);

        if ($existing === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('todo_not_found'),
            ]);
        }

        try {
            $ticketChange = $this->syncLinkedTicket($existing, $payload);

            if ($ticketChange !== null) {
                $card = $this->todoCardModel->syncFromTicket($ticketChange['updated']);

                if ($ticketChange['previous_status'] !== $ticketChange['new_status']) {
                    $this->safeNotifyTicketStatus($ticketChange['updated'], $ticketChange['previous_status']);
                }
            } else {
                $card = $existing;
            }

            $card = $this->todoCardModel->update($cardId, $this->cardOnlyFields($existing, $payload)) ?? $card;

            if ($card === null) {
                throw new \RuntimeException(__('todo_not_found'));
            }

            $previousAssignee = $existing['assigned_user_id'] ?? null;
            $newAssignee = $card['assigned_user_id'] ?? null;

            if ($newAssignee !== null && $previousAssignee !== $newAssignee) {
                $this->safeNotifyAssignment($card);
            }
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('todo_update_error'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('todo_update_success'),
            'data' => $card,
        ]);
    }

    public function move(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $cardId = (int) ($args['id'] ?? 0);
        $payload = $this->resolvePayload($request);

        if ($cardId <= 0 || $payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('todo_invalid_payload'),
            ]);
        }

        $existing = $this->todoCardModel->findById($cardId);

        if ($existing === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('todo_not_found'),
            ]);
        }

        $status = (string) ($payload['status'] ?? TodoCard::STATUS_TODO);
        $position = max(0, (int) ($payload['position'] ?? 0));

        try {
            if (($existing['ticket_id'] ?? null) !== null) {
                $ticketId = (int) $existing['ticket_id'];
                $ticket = $this->ticketModel->findById($ticketId);

                if ($ticket === null) {
                    throw new \InvalidArgumentException(__('ticket_not_found'));
                }

                $previousStatus = (string) $ticket['status'];
                $updatedTicket = $ticket;

                if ($status !== (string) ($existing['status'] ?? TodoCard::STATUS_TODO)) {
                    $updatedTicket = $this->ticketModel->update($ticketId, [
                        'status' => $this->todoCardModel->ticketStatusFromCard($status),
                    ]);
                }

                if ($updatedTicket === null) {
                    throw new \RuntimeException(__('ticket_update_error'));
                }

                if ($status !== (string) ($existing['status'] ?? TodoCard::STATUS_TODO)) {
                    $this->todoCardModel->syncFromTicket($updatedTicket);
                }
                $card = $this->todoCardModel->move($cardId, $status, $position);

                if ($previousStatus !== (string) $updatedTicket['status']) {
                    $this->safeNotifyTicketStatus($updatedTicket, $previousStatus);
                }
            } else {
                $card = $this->todoCardModel->move($cardId, $status, $position);
            }
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('todo_move_error'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('todo_move_success'),
            'data' => $card,
        ]);
    }

    public function archive(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $cardId = (int) ($args['id'] ?? 0);
        $payload = $this->resolvePayload($request);

        if ($cardId <= 0 || $payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('todo_invalid_payload'),
            ]);
        }

        try {
            $card = $this->todoCardModel->update($cardId, [
                'archived' => filter_var($payload['archived'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('todo_update_error'),
            ]);
        }

        if ($card === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('todo_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => $card['archived'] ? __('todo_archive_success') : __('todo_restore_success'),
            'data' => $card,
        ]);
    }

    /**
     * @param array<string, mixed> $card
     * @param array<string, mixed> $payload
     *
     * @return array{updated: array<string, mixed>, previous_status: string, new_status: string}|null
     */
    private function syncLinkedTicket(array $card, array $payload): ?array
    {
        $ticketId = (int) ($card['ticket_id'] ?? 0);

        if ($ticketId <= 0) {
            return null;
        }

        $ticket = $this->ticketModel->findById($ticketId);

        if ($ticket === null) {
            throw new \InvalidArgumentException(__('ticket_not_found'));
        }

        $fields = [];

        if (array_key_exists('title', $payload)) {
            $fields['subject'] = $payload['title'];
        }
        if (array_key_exists('description', $payload)) {
            $fields['description'] = $payload['description'];
        }
        if (array_key_exists('priority', $payload)) {
            $fields['priority'] = $payload['priority'];
        }
        if (array_key_exists('assigned_user_id', $payload)) {
            $fields['assigned_user_id'] = $payload['assigned_user_id'];
        }
        if (array_key_exists('status', $payload)
            && (string) $payload['status'] !== (string) ($card['status'] ?? TodoCard::STATUS_TODO)
        ) {
            $fields['status'] = $this->todoCardModel->ticketStatusFromCard((string) $payload['status']);
        }

        if ($fields === []) {
            return [
                'updated' => $ticket,
                'previous_status' => (string) $ticket['status'],
                'new_status' => (string) $ticket['status'],
            ];
        }

        $updated = $this->ticketModel->update($ticketId, $fields);

        if ($updated === null) {
            throw new \RuntimeException(__('ticket_update_error'));
        }

        return [
            'updated' => $updated,
            'previous_status' => (string) $ticket['status'],
            'new_status' => (string) $updated['status'],
        ];
    }

    /**
     * Keep board-only metadata separate from fields shared with a linked ticket.
     *
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function cardOnlyFields(array $existing, array $payload): array
    {
        if (($existing['ticket_id'] ?? null) === null) {
            return $payload;
        }

        return array_intersect_key($payload, array_flip([
            'start_date',
            'due_date',
            'labels',
            'checklist',
            'archived',
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePayload(ServerRequestInterface $request): ?array
    {
        $parsed = $request->getParsedBody();

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $response->getBody()->write(json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /** @param array<string, mixed> $card */
    private function safeNotifyCreated(array $card): void
    {
        try {
            $this->todoNotificationService->deferCreatedAlert($card);
        } catch (\Throwable) {
            // Notification scheduling must never block task creation.
        }
    }

    /** @param array<string, mixed> $card */
    private function safeNotifyAssignment(array $card): void
    {
        try {
            $this->todoNotificationService->deferAssignmentAlert($card);
        } catch (\Throwable) {
            // Notification scheduling must never block assignment changes.
        }
    }

    /** @param array<string, mixed> $ticket */
    private function safeNotifyTicketStatus(array $ticket, string $previousStatus): void
    {
        try {
            $this->ticketNotificationService->deferStatusChangeAlert($ticket, $previousStatus);
        } catch (\Throwable) {
            // Notification scheduling must never block board updates.
        }
    }
}
