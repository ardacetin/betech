<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\Setting;
use App\Models\User;
use App\Services\Auth\UserIntegrationFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    private const OFFBOARD_HISTORY_NOTE = 'Personnel offboarded. Asset automatically returned to IT Storage / Bilgi İşlem Deposu.';

    public function __construct(
        private readonly UserIntegrationFactory $userIntegrationFactory,
        private readonly User $userModel,
        private readonly Asset $assetModel,
        private readonly AssetHistory $assetHistoryModel,
        private readonly Setting $settingModel
    ) {
    }

    public function search(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $query = trim((string) ($queryParams['q'] ?? ''));

        try {
            $driver = $this->userIntegrationFactory->make();
            $users = $driver->searchUsers($query);
            $activeDriver = strtolower(trim($this->settingModel->get('active_auth_driver', 'local') ?? 'local'));

            if ($activeDriver !== 'local') {
                $users = array_map(
                    fn (array $user): array => $this->userModel->syncFromDirectory($user),
                    $users
                );
            }
        } catch (\Throwable $exception) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('user_search_failed') . $exception->getMessage(),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $users,
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $request->getParsedBody();

        if (!is_array($payload)) {
            $rawBody = (string) $request->getBody();

            if ($rawBody !== '') {
                try {
                    $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
                    $payload = is_array($decoded) ? $decoded : null;
                } catch (\JsonException) {
                    $payload = null;
                }
            }
        }

        if (!is_array($payload)) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('manual_user_invalid_payload'),
            ]);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));

        try {
            $user = $this->userModel->createManual($name, $email);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('manual_user_create_success'),
            'data' => $user,
        ]);
    }

    public function personnelIndex(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($queryParams['per_page'] ?? User::PERSONNEL_PAGE_SIZE)));
        $search = trim((string) ($queryParams['q'] ?? ''));

        $result = $this->userModel->findPersonnelPaginated(
            $page,
            $perPage,
            $search !== '' ? $search : null
        );
        $assetCounts = $this->userModel->assignedAssetCountsForUserIds(
            array_map(static fn (array $user): int => (int) $user['id'], $result['data'])
        );

        $data = array_map(
            static function (array $user) use ($assetCounts): array {
                $userId = (int) $user['id'];

                return [
                    ...$user,
                    'assigned_asset_count' => $assetCounts[$userId] ?? 0,
                ];
            },
            $result['data']
        );

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $data,
            'pagination' => $result['pagination'],
        ]);
    }

    public function personnelSync(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $activeDriver = strtolower(trim($this->settingModel->get('active_auth_driver', 'local') ?? 'local'));

        if (!in_array($activeDriver, [User::PROVIDER_LDAP, User::PROVIDER_GOOGLE], true)) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('personnel_sync_unsupported'),
            ]);
        }

        try {
            $driver = $this->userIntegrationFactory->make($activeDriver);
            $directoryUsers = $driver->listAllUsers();
        } catch (\Throwable $exception) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('personnel_sync_failed') . $exception->getMessage(),
            ]);
        }

        if ($directoryUsers === []) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('personnel_sync_empty'),
            ]);
        }

        $stats = $this->userModel->syncPersonnelDirectory($directoryUsers, $activeDriver);

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __(
                'personnel_sync_success',
                [
                    'created' => (string) $stats['created'],
                    'updated' => (string) $stats['updated'],
                    'skipped' => (string) $stats['skipped'],
                    'total' => (string) $stats['total'],
                ]
            ),
            'data' => $stats,
        ]);
    }

    public function systemUsersIndex(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->userModel->findAllSystemUsers(),
        ]);
    }

    public function storeSystemUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('system_user_invalid_payload'),
            ]);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $role = trim((string) ($payload['role'] ?? User::ROLE_TECHNICIAN));
        $password = (string) ($payload['password'] ?? '');

        try {
            $user = $this->userModel->createSystemUser($name, $email, $role, $password);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('system_user_create_error'),
            ]);
        }

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('system_user_create_success'),
            'data' => $user,
        ]);
    }

    public function updateSystemUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = (int) ($args['id'] ?? 0);

        if ($userId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('system_user_invalid_id'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('system_user_invalid_payload'),
            ]);
        }

        $role = array_key_exists('role', $payload) ? trim((string) $payload['role']) : null;
        $name = array_key_exists('name', $payload) ? trim((string) $payload['name']) : null;
        $email = array_key_exists('email', $payload) ? trim((string) $payload['email']) : null;
        $password = array_key_exists('password', $payload) ? (string) $payload['password'] : null;

        try {
            $user = $this->userModel->updateSystemUser($userId, $role, $name, $email, $password);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('system_user_update_error'),
            ]);
        }

        if ($user === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('system_user_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('system_user_update_success'),
            'data' => $user,
        ]);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->personnelIndex($request, $response);
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

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            return null;
        }
    }

    public function offboard(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = (int) ($args['id'] ?? 0);

        if ($userId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('offboard_invalid_user'),
            ]);
        }

        $user = $this->userModel->findById($userId);

        if ($user === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('offboard_user_not_found'),
            ]);
        }

        if (($user['status'] ?? User::STATUS_ACTIVE) === User::STATUS_OFFBOARDED) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('offboard_already_offboarded'),
            ]);
        }

        $assets = $this->assetModel->findAllByUserId($userId);
        $reclaimedAssets = [];

        foreach ($assets as $asset) {
            $assetId = (int) $asset['id'];

            $updated = $this->assetModel->update($assetId, [
                'user_id' => null,
                'status' => 'storage',
            ]);

            if ($updated === null) {
                continue;
            }

            $this->assetHistoryModel->log(
                $assetId,
                'offboarded',
                null,
                $userId,
                self::OFFBOARD_HISTORY_NOTE
            );

            $reclaimedAssets[] = [
                'id' => $assetId,
                'asset_tag' => (string) $updated['asset_tag'],
                'name' => (string) $updated['name'],
            ];
        }

        $this->userModel->markOffboarded($userId);

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('offboard_success'),
            'data' => [
                'user_id' => $userId,
                'reclaimed_assets' => $reclaimedAssets,
                'reclaimed_count' => count($reclaimedAssets),
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
