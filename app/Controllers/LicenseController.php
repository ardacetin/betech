<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\License;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LicenseController
{
    public function __construct(
        private readonly License $licenseModel
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // GET /api/licenses returns only license metadata (no assignments here).
        // Assignment queries (in model) and POST /assign strictly use personnel_id + JOIN personnel (never users or user_id).
        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->licenseModel->findAll(),
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('license_invalid_payload'),
            ]);
        }

        $errors = $this->validateCreatePayload($payload);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('license_validation_failed'),
                'errors' => $errors,
            ]);
        }

        try {
            $license = $this->licenseModel->create(
                (string) ($payload['name'] ?? $payload['software_name'] ?? ''),
                (string) ($payload['vendor'] ?? '-'),
                (int) ($payload['seats'] ?? $payload['total_seats'] ?? 1),
                array_key_exists('license_key', $payload) ? (string) $payload['license_key'] : null,
                array_key_exists('expiration_date', $payload) ? (string) $payload['expiration_date'] : null,
                array_key_exists('notes', $payload) ? (string) $payload['notes'] : null
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('license_create_error'),
            ]);
        }

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('license_create_success'),
            'data' => $license,
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $licenseId = (int) ($args['id'] ?? 0);

        if ($licenseId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('license_invalid_id'),
            ]);
        }

        $license = $this->licenseModel->findById($licenseId);

        if ($license === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('license_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $license,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $licenseId = (int) ($args['id'] ?? 0);

        if ($licenseId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('license_invalid_id'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('license_invalid_payload'),
            ]);
        }

        if (array_key_exists('software_name', $payload) && !array_key_exists('name', $payload)) {
            $payload['name'] = $payload['software_name'];
        }

        if (array_key_exists('total_seats', $payload) && !array_key_exists('seats', $payload)) {
            $payload['seats'] = $payload['total_seats'];
        }

        $errors = $this->validateUpdatePayload($payload);

        if ($errors !== []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('license_validation_failed'),
                'errors' => $errors,
            ]);
        }

        try {
            $license = $this->licenseModel->update($licenseId, $payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('license_update_error'),
            ]);
        }

        if ($license === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('license_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('license_update_success'),
            'data' => $license,
        ]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $licenseId = (int) ($args['id'] ?? 0);

        if ($licenseId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('license_invalid_id'),
            ]);
        }

        if (!$this->licenseModel->delete($licenseId)) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('license_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('license_delete_success'),
        ]);
    }

    public function assign(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $licenseId = (int) ($args['id'] ?? 0);

        if ($licenseId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('license_invalid_id'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('license_invalid_payload'),
            ]);
        }

        $assetId = array_key_exists('asset_id', $payload) && $payload['asset_id'] !== null && $payload['asset_id'] !== ''
            ? (int) $payload['asset_id']
            : null;
        $personnelId = null;

        if (array_key_exists('personnel_id', $payload) && $payload['personnel_id'] !== null && $payload['personnel_id'] !== '') {
            $personnelId = (int) $payload['personnel_id'];
        }

        try {
            $assignment = $this->licenseModel->assign($licenseId, $assetId, $personnelId);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\RuntimeException $exception) {
            $message = $exception->getMessage();
            $status = $message === __('license_not_found') ? 404 : 422;

            return $this->jsonResponse($response, $status, [
                'status' => 'error',
                'message' => $message,
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('license_assign_error'),
            ]);
        }

        $license = $this->licenseModel->findById($licenseId);

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('license_assign_success'),
            'data' => [
                'assignment' => $assignment,
                'license' => $license,
            ],
        ]);
    }

    public function unassign(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $licenseId = (int) ($args['id'] ?? 0);

        if ($licenseId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('license_invalid_id'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('license_invalid_payload'),
            ]);
        }

        $assignmentId = (int) ($payload['assignment_id'] ?? 0);

        if ($assignmentId <= 0) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('license_assignment_id_required'),
            ]);
        }

        $assignment = $this->licenseModel->unassign($licenseId, $assignmentId);

        if ($assignment === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('license_assignment_not_found'),
            ]);
        }

        $license = $this->licenseModel->findById($licenseId);

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('license_unassign_success'),
            'data' => [
                'assignment' => $assignment,
                'license' => $license,
            ],
        ]);
    }

    public function forAsset(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $assetId = (int) ($args['id'] ?? 0);

        if ($assetId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('return_asset_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->licenseModel->findAssignmentsForAsset($assetId),
        ]);
    }

    public function assignments(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $licenseId = (int) ($args['id'] ?? 0);

        if ($licenseId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('license_invalid_id'),
            ]);
        }

        if ($this->licenseModel->findById($licenseId) === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('license_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->licenseModel->findAssignmentsForLicense($licenseId),
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
    private function validateCreatePayload(array $payload): array
    {
        $errors = [];

        if (trim((string) ($payload['name'] ?? '')) === '') {
            $errors['name'][] = __('license_name_required');
        }

        if (trim((string) ($payload['vendor'] ?? '')) === '') {
            $errors['vendor'][] = __('license_vendor_required');
        }

        $seats = (int) ($payload['seats'] ?? $payload['total_seats'] ?? 0);

        if ($seats < 1) {
            $errors['seats'][] = __('license_seats_invalid');
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, list<string>>
     */
    private function validateUpdatePayload(array $payload): array
    {
        $errors = [];

        if (array_key_exists('name', $payload) && trim((string) $payload['name']) === '') {
            $errors['name'][] = __('license_name_required');
        }

        if (array_key_exists('vendor', $payload) && trim((string) $payload['vendor']) === '') {
            $errors['vendor'][] = __('license_vendor_required');
        }

        if (array_key_exists('seats', $payload) && (int) $payload['seats'] < 1) {
            $errors['seats'][] = __('license_seats_invalid');
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
