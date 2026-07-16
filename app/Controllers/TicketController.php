<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\SessionAuthService;
use App\Services\EndUserContextService;
use App\Services\ListPagination;
use App\Services\Mail\TicketNotificationService;
use App\Services\TicketAttachmentStorageService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Psr7\Stream;

class TicketController
{
    public function __construct(
        private readonly Ticket $ticketModel,
        private readonly User $userModel,
        private readonly Asset $assetModel,
        private readonly SessionAuthService $sessionAuthService,
        private readonly EndUserContextService $endUserContextService,
        private readonly TicketNotificationService $ticketNotificationService,
        private readonly TicketAttachmentStorageService $ticketAttachmentStorageService,
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $statusParam = isset($query['status']) ? strtolower(trim((string) $query['status'])) : null;
        $priority = isset($query['priority']) ? (string) $query['priority'] : null;

        if ($priority === 'all') {
            $priority = null;
        }

        $page = ListPagination::parsePage($query);
        $sortOrder = $this->ticketModel->buildSortOrderFromQuery($query);

        if ($this->endUserContextService->isEndUser()) {
            $personnelId = $this->endUserContextService->resolvePersonnelId();

            if ($personnelId === null) {
                return $this->jsonResponse($response, 200, [
                    'status' => 'success',
                    'data' => [],
                    'pagination' => ListPagination::meta(1, 0, ListPagination::TICKET_PAGE_SIZE),
                ]);
            }

            $status = $statusParam === 'all' || $statusParam === null || $statusParam === ''
                ? null
                : $statusParam;

            $result = $this->ticketModel->findPaginatedByPersonnelId($personnelId, $status, $priority, $page, null, $sortOrder);

            return $this->jsonResponse($response, 200, [
                'status' => 'success',
                'data' => $result['data'],
                'pagination' => $result['pagination'],
            ]);
        }

        [$status, $scope] = $this->resolveAdminTicketListFilters($statusParam);
        $result = $this->ticketModel->findPaginated($status, $priority, $page, $scope, $sortOrder);

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $ticketId = (int) ($args['id'] ?? 0);

        if ($ticketId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ticket_invalid_id'),
            ]);
        }

        if ($this->endUserContextService->isEndUser()) {
            $personnelId = $this->endUserContextService->resolvePersonnelId();

            if ($personnelId === null) {
                return $this->jsonResponse($response, 403, [
                    'status' => 'error',
                    'message' => __('portal_profile_not_linked'),
                ]);
            }

            $ticket = $this->ticketModel->findByIdForPersonnel($ticketId, $personnelId, true);
        } else {
            $ticket = $this->ticketModel->findById($ticketId, true);
        }

        if ($ticket === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ticket_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $ticket,
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ticket_invalid_payload'),
            ]);
        }

        $isEndUser = $this->endUserContextService->isEndUser();

        if ($isEndUser) {
            unset($payload['personnel_id'], $payload['user_id'], $payload['assigned_user_id']);

            $personnelId = $this->endUserContextService->resolvePersonnelId();

            if ($personnelId === null) {
                return $this->jsonResponse($response, 403, [
                    'status' => 'error',
                    'message' => __('portal_profile_not_linked'),
                ]);
            }

            $payload['personnel_id'] = $personnelId;
        } else {
            $personnelId = (int) ($payload['personnel_id'] ?? 0);
        }

        $errors = $this->validatePayload($payload, true, $isEndUser);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('ticket_validation_failed'),
                'errors' => $errors,
            ]);
        }

        $assetId = $this->normalizeOptionalId($payload['asset_id'] ?? null);

        if ($isEndUser && $assetId !== null && !$this->assetModel->isAssignedToPersonnel($assetId, $personnelId)) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('portal_ticket_asset_not_owned'),
            ]);
        }

        try {
            $ticket = $this->ticketModel->create(
                (string) $payload['subject'],
                (string) $payload['description'],
                (int) $payload['personnel_id'],
                $assetId,
                (string) ($payload['priority'] ?? Ticket::PRIORITY_MEDIUM),
                $this->endUserContextService->resolveLegacyUserId()
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('ticket_create_error'),
            ]);
        }

        $this->safeDeferNewTicketAlert($ticket);

        $this->auditLogger->logFromRequest(
            $request,
            $this->endUserContextService->resolveLegacyUserId(),
            AuditLog::ACTION_CREATED,
            AuditLog::ENTITY_TICKET,
            (int) ($ticket['id'] ?? 0),
            null,
            $this->snapshotTicket($ticket)
        );

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('ticket_create_success'),
            'data' => $ticket,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if ($this->endUserContextService->isEndUser()) {
            return $this->jsonResponse($response, 403, [
                'status' => 'error',
                'message' => __('portal_action_not_allowed'),
            ]);
        }

        $ticketId = (int) ($args['id'] ?? 0);

        if ($ticketId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ticket_invalid_id'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ticket_invalid_payload'),
            ]);
        }

        $existing = $this->ticketModel->findById($ticketId);

        if ($existing === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ticket_not_found'),
            ]);
        }

        try {
            $ticket = $this->ticketModel->update($ticketId, $payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('ticket_update_error'),
            ]);
        }

        if ($ticket === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ticket_not_found'),
            ]);
        }

        if (array_key_exists('status', $payload)) {
            $previousStatus = (string) ($existing['status'] ?? '');
            $newStatus = (string) ($ticket['status'] ?? '');

            if ($previousStatus !== $newStatus) {
                $this->safeDeferStatusChangeAlert($ticket, $previousStatus);
            }
        }

        $changes = $this->snapshotTicketChanges($existing, $ticket, $payload);

        if ($changes['new'] !== []) {
            try {
                $this->auditLogger->logFromRequest(
                    $request,
                    $this->sessionAuthService->userId(),
                    AuditLog::ACTION_UPDATED,
                    AuditLog::ENTITY_TICKET,
                    $ticketId,
                    $changes['old'],
                    $changes['new']
                );
            } catch (\Throwable) {
                // Audit logging must never block ticket updates.
            }
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('ticket_update_success'),
            'data' => $ticket,
        ]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if ($this->endUserContextService->isEndUser()) {
            return $this->jsonResponse($response, 403, [
                'status' => 'error',
                'message' => __('portal_action_not_allowed'),
            ]);
        }

        $ticketId = (int) ($args['id'] ?? 0);

        if ($ticketId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ticket_invalid_id'),
            ]);
        }

        $existing = $this->ticketModel->findById($ticketId);

        if ($existing === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ticket_not_found'),
            ]);
        }

        if (!$this->ticketModel->delete($ticketId)) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ticket_not_found'),
            ]);
        }

        $this->auditLogger->logFromRequest(
            $request,
            $this->sessionAuthService->userId(),
            AuditLog::ACTION_DELETED,
            AuditLog::ENTITY_TICKET,
            $ticketId,
            $this->snapshotTicket($existing),
            null
        );

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('ticket_delete_success'),
        ]);
    }

    public function addComment(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $ticketId = (int) ($args['id'] ?? 0);

        if ($ticketId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ticket_invalid_id'),
            ]);
        }

        $isEndUser = $this->endUserContextService->isEndUser();

        if ($isEndUser) {
            $personnelId = $this->endUserContextService->resolvePersonnelId();

            if ($personnelId === null || !$this->ticketModel->belongsToPersonnel($ticketId, $personnelId)) {
                return $this->jsonResponse($response, 404, [
                    'status' => 'error',
                    'message' => __('ticket_not_found'),
                ]);
            }
        }

        $payload = $this->resolvePayload($request) ?? [];
        $body = trim((string) ($payload['body'] ?? ''));
        $isInternal = !$isEndUser && (
            (string) ($payload['is_internal'] ?? '') === '1'
            || (string) ($payload['is_internal'] ?? '') === 'true'
            || ($payload['is_internal'] ?? false) === true
        );

        if ($body === '') {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('ticket_comment_required'),
            ]);
        }

        $userId = $this->endUserContextService->resolveLegacyUserId();
        $authorName = $this->resolveAuthorName($userId);
        $uploadedFiles = $this->collectUploadedFiles($request);

        try {
            $comment = $this->ticketModel->addComment(
                $ticketId,
                $body,
                $userId,
                $authorName,
                $isInternal
            );

            if ($uploadedFiles !== []) {
                $stored = [];

                foreach ($uploadedFiles as $file) {
                    $stored[] = $this->ticketAttachmentStorageService->storeUploadedFile($file);
                }

                $attachments = $this->ticketModel->addCommentAttachments(
                    $ticketId,
                    (int) $comment['id'],
                    array_map(static fn (array $item): array => [
                        'original_filename' => $item['original_filename'],
                        'stored_filename' => $item['stored_filename'],
                        'file_path' => $item['relative_path'],
                        'file_size' => $item['file_size'],
                        'mime_type' => $item['mime_type'] ?? null,
                    ], $stored),
                    $userId
                );

                $comment['attachments'] = $attachments;
            }
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('ticket_comment_create_error'),
            ]);
        }

        if (!$isEndUser && !$isInternal) {
            $ticket = $this->ticketModel->findById($ticketId);

            if ($ticket !== null) {
                $this->safeDeferStaffReplyAlert($ticket, $comment);
            }
        }

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'success' => true,
            'message' => __('ticket_comment_create_success'),
            'data' => $comment,
        ]);
    }

    public function downloadAttachment(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $ticketId = (int) ($args['id'] ?? 0);
        $attachmentId = (int) ($args['attachmentId'] ?? 0);

        if ($ticketId <= 0 || $attachmentId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ticket_invalid_id'),
            ]);
        }

        if (!$this->canAccessTicket($ticketId)) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ticket_not_found'),
            ]);
        }

        $attachment = $this->ticketModel->findAttachmentById($attachmentId);

        if ($attachment === null || (int) ($attachment['ticket_id'] ?? 0) !== $ticketId) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ticket_attachment_not_found'),
            ]);
        }

        if ($this->endUserContextService->isEndUser()) {
            $comment = $this->ticketModel->findCommentById((int) ($attachment['comment_id'] ?? 0));

            if ($comment === null || !empty($comment['is_internal'])) {
                return $this->jsonResponse($response, 404, [
                    'status' => 'error',
                    'message' => __('ticket_attachment_not_found'),
                ]);
            }
        }

        try {
            $absolutePath = $this->ticketAttachmentStorageService->resolveAbsolutePath(
                (string) ($attachment['file_path'] ?? '')
            );
        } catch (\Throwable) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ticket_attachment_not_found'),
            ]);
        }

        $stream = fopen($absolutePath, 'rb');

        if ($stream === false) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('ticket_attachment_not_found'),
            ]);
        }

        $filename = (string) ($attachment['original_filename'] ?? 'attachment');
        $mime = (string) ($attachment['mime_type'] ?? 'application/octet-stream');

        return $response
            ->withBody(new Stream($stream))
            ->withHeader('Content-Type', $mime)
            ->withHeader(
                'Content-Disposition',
                'attachment; filename="' . str_replace('"', '', $filename) . '"'
            );
    }

    public function followers(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $ticketId = (int) ($args['id'] ?? 0);

        if ($ticketId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ticket_invalid_id'),
            ]);
        }

        if ($this->endUserContextService->isEndUser()) {
            return $this->jsonResponse($response, 403, [
                'status' => 'error',
                'message' => __('portal_action_not_allowed'),
            ]);
        }

        if ($this->ticketModel->findById($ticketId) === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ticket_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->ticketModel->findFollowersByTicketId($ticketId),
        ]);
    }

    public function updateFollowers(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        if ($this->endUserContextService->isEndUser()) {
            return $this->jsonResponse($response, 403, [
                'status' => 'error',
                'message' => __('portal_action_not_allowed'),
            ]);
        }

        $ticketId = (int) ($args['id'] ?? 0);

        if ($ticketId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ticket_invalid_id'),
            ]);
        }

        $payload = $this->resolvePayload($request) ?? [];
        $items = $payload['items'] ?? [];

        if (!is_array($items)) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('ticket_followers_invalid_payload'),
            ]);
        }

        try {
            $followers = $this->ticketModel->replaceFollowers($ticketId, $items);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('ticket_followers_update_error'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('ticket_followers_update_success'),
            'data' => $followers,
        ]);
    }

    public function transfer(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        if ($this->endUserContextService->isEndUser()) {
            return $this->jsonResponse($response, 403, [
                'status' => 'error',
                'message' => __('portal_action_not_allowed'),
            ]);
        }

        $ticketId = (int) ($args['id'] ?? 0);
        $payload = $this->resolvePayload($request) ?? [];
        $categoryId = (int) ($payload['category_id'] ?? 0);
        $note = isset($payload['note']) ? trim((string) $payload['note']) : null;

        if ($ticketId <= 0 || $categoryId <= 0) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('ticket_transfer_invalid_payload'),
            ]);
        }

        $userId = $this->sessionAuthService->userId();
        $authorName = $this->resolveAuthorName($userId);

        try {
            $ticket = $this->ticketModel->transferToCategory(
                $ticketId,
                $categoryId,
                $userId,
                $authorName,
                $note
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('ticket_transfer_error'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('ticket_transfer_success'),
            'data' => $ticket,
        ]);
    }

    public function attachEmail(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        if ($this->endUserContextService->isEndUser()) {
            return $this->jsonResponse($response, 403, [
                'status' => 'error',
                'message' => __('portal_action_not_allowed'),
            ]);
        }

        $ticketId = (int) ($args['id'] ?? 0);
        $payload = $this->resolvePayload($request) ?? [];

        if ($ticketId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ticket_invalid_id'),
            ]);
        }

        $ticket = $this->ticketModel->findById($ticketId);

        if ($ticket === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ticket_not_found'),
            ]);
        }

        $body = trim((string) ($payload['body'] ?? ''));
        $fromAddress = trim((string) ($payload['from_address'] ?? ''));
        $subject = trim((string) ($payload['subject'] ?? ''));
        $messageId = trim((string) ($payload['message_id'] ?? ''));
        $inReplyTo = trim((string) ($payload['in_reply_to'] ?? ''));

        if ($body === '') {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('ticket_comment_required'),
            ]);
        }

        if ($messageId !== '' && $this->ticketModel->emailMessageExists($messageId)) {
            return $this->jsonResponse($response, 409, [
                'status' => 'error',
                'message' => __('ticket_email_already_attached'),
            ]);
        }

        $authorName = $fromAddress !== '' ? $fromAddress : __('ticket_email_author');

        try {
            $comment = $this->ticketModel->addComment(
                $ticketId,
                $body,
                null,
                $authorName,
                false,
                $messageId !== '' ? $messageId : null,
                $inReplyTo !== '' ? $inReplyTo : null
            );

            if ($messageId !== '') {
                $this->ticketModel->recordEmailMessage(
                    $ticketId,
                    $messageId,
                    Ticket::EMAIL_DIRECTION_INBOUND,
                    $inReplyTo !== '' ? $inReplyTo : null,
                    (int) $comment['id'],
                    $fromAddress !== '' ? $fromAddress : null,
                    $subject !== '' ? $subject : null
                );
            }
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('ticket_email_attach_error'),
            ]);
        }

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('ticket_email_attach_success'),
            'data' => $this->ticketModel->findById($ticketId, true),
        ]);
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function safeDeferNewTicketAlert(array $ticket): void
    {
        try {
            $this->ticketNotificationService->deferNewTicketAlert($ticket);
        } catch (\Throwable) {
            // Notification scheduling must never block ticket creation.
        }
    }

    /**
     * @param array<string, mixed> $ticket
     */
    private function safeDeferStatusChangeAlert(array $ticket, string $previousStatus): void
    {
        try {
            $this->ticketNotificationService->deferStatusChangeAlert($ticket, $previousStatus);
        } catch (\Throwable) {
            // Notification scheduling must never block ticket updates.
        }
    }

    /**
     * @param array<string, mixed> $ticket
     * @param array<string, mixed> $comment
     */
    private function safeDeferStaffReplyAlert(array $ticket, array $comment): void
    {
        try {
            $this->ticketNotificationService->deferStaffReplyAlert($ticket, $comment);
        } catch (\Throwable) {
            // Notification scheduling must never block ticket replies.
        }
    }

    private function resolveAuthorName(?int $userId): string
    {
        if ($userId === null || $userId <= 0) {
            return __('ticket_comment_system_author');
        }

        $person = $this->endUserContextService->resolvePersonnel();

        if ($person !== null) {
            $personName = trim((string) ($person['name'] ?? ''));

            if ($personName !== '') {
                return $personName;
            }
        }

        $user = $this->userModel->findById($userId);

        if ($user === null) {
            return __('ticket_comment_system_author');
        }

        $name = trim((string) ($user['name'] ?? ''));

        return $name !== '' ? $name : __('ticket_comment_system_author');
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveAdminTicketListFilters(?string $statusParam): array
    {
        if ($statusParam === null || $statusParam === '') {
            return [null, 'active'];
        }

        return match ($statusParam) {
            'active' => [null, 'active'],
            'all' => [null, null],
            'closed' => [Ticket::STATUS_CLOSED, null],
            default => [$statusParam, null],
        };
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
    private function validatePayload(array $payload, bool $isCreate, bool $isEndUser = false): array
    {
        $errors = [];

        if ($isCreate && trim((string) ($payload['subject'] ?? '')) === '') {
            $errors['subject'][] = __('ticket_subject_required');
        }

        if ($isCreate && trim((string) ($payload['description'] ?? '')) === '') {
            $errors['description'][] = __('ticket_description_required');
        }

        if ($isCreate && $isEndUser) {
            $priority = strtolower(trim((string) ($payload['priority'] ?? Ticket::PRIORITY_MEDIUM)));
            if (!in_array($priority, [Ticket::PRIORITY_LOW, Ticket::PRIORITY_MEDIUM, Ticket::PRIORITY_HIGH], true)) {
                $errors['priority'][] = __('ticket_priority_invalid');
            }
        }

        if ($isCreate && !$isEndUser && (int) ($payload['personnel_id'] ?? 0) <= 0) {
            $errors['personnel_id'][] = __('ticket_personnel_required');
        }

        return $errors;
    }

    /**
     * @param mixed $value
     */
    private function normalizeOptionalId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function canAccessTicket(int $ticketId): bool
    {
        if ($ticketId <= 0) {
            return false;
        }

        if ($this->endUserContextService->isEndUser()) {
            $personnelId = $this->endUserContextService->resolvePersonnelId();

            return $personnelId !== null
                && $this->ticketModel->belongsToPersonnel($ticketId, $personnelId);
        }

        return $this->ticketModel->findById($ticketId) !== null;
    }

    /**
     * @return list<UploadedFileInterface>
     */
    private function collectUploadedFiles(ServerRequestInterface $request): array
    {
        $uploaded = $request->getUploadedFiles();
        $files = $uploaded['files'] ?? $uploaded['files[]'] ?? [];

        if ($files instanceof UploadedFileInterface) {
            return [$files];
        }

        if (!is_array($files)) {
            return [];
        }

        $list = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFileInterface) {
                $list[] = $file;
            }
        }

        return $list;
    }

    /**
     * @param array<string, mixed> $ticket
     *
     * @return array<string, mixed>
     */
    private function snapshotTicket(array $ticket): array
    {
        return [
            'ticket_number' => (string) ($ticket['ticket_number'] ?? ''),
            'subject' => (string) ($ticket['subject'] ?? ''),
            'status' => (string) ($ticket['status'] ?? ''),
            'priority' => (string) ($ticket['priority'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param array<string, mixed> $payload
     *
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    private function snapshotTicketChanges(array $before, array $after, array $payload): array
    {
        $fields = ['subject', 'description', 'status', 'priority', 'assigned_user_id', 'personnel_id', 'asset_id', 'category_id'];
        $old = ['ticket_number' => (string) ($before['ticket_number'] ?? '')];
        $new = ['ticket_number' => (string) ($after['ticket_number'] ?? '')];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $oldValue = $before[$field] ?? null;
            $newValue = $after[$field] ?? null;

            if ((string) $oldValue === (string) $newValue) {
                continue;
            }

            $old[$field] = $oldValue;
            $new[$field] = $newValue;
        }

        return ['old' => $old, 'new' => $new];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        $encoded = json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        $response->getBody()->write($encoded);

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
