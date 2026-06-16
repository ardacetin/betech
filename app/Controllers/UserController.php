<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Models\AssetHistory;
use App\Models\Personnel;
use App\Models\Setting;
use App\Models\User;
use App\Services\Auth\SessionAuthService;
use App\Services\Auth\UserIntegrationFactory;
use App\Services\ClientIpResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    private const OFFBOARD_HISTORY_NOTE = 'Personnel offboarded. Asset automatically returned to IT Storage / Bilgi İşlem Deposu.';

    public function __construct(
        private readonly UserIntegrationFactory $userIntegrationFactory,
        private readonly User $userModel,
        private readonly Personnel $personnelModel,
        private readonly Asset $assetModel,
        private readonly AssetHistory $assetHistoryModel,
        private readonly Setting $settingModel,
        private readonly SessionAuthService $sessionAuthService,
        private readonly ClientIpResolver $clientIpResolver
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->userModel->findAll(),
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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
            $user = $this->userModel->create($name, $email, $role, $password);
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

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
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

        $name = array_key_exists('name', $payload) ? trim((string) $payload['name']) : null;
        $email = array_key_exists('email', $payload) ? trim((string) $payload['email']) : null;
        $role = array_key_exists('role', $payload) ? trim((string) $payload['role']) : null;
        $password = array_key_exists('password', $payload) ? (string) $payload['password'] : null;

        try {
            $user = $this->userModel->update($userId, $name, $email, $role, $password);
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

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = (int) ($args['id'] ?? 0);
        $currentUserId = $this->sessionAuthService->userId() ?? 0;

        if ($userId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('system_user_invalid_id'),
            ]);
        }

        if ($currentUserId === $userId) {
            return $this->jsonResponse($response, 403, [
                'status' => 'error',
                'message' => __('system_user_delete_self'),
            ]);
        }

        try {
            $deleted = $this->userModel->delete($userId);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('system_user_delete_error'),
            ]);
        }

        if (!$deleted) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('system_user_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('system_user_delete_success'),
        ]);
    }

    public function searchPersonnel(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $query = trim((string) ($queryParams['q'] ?? ''));

        try {
            $driver = $this->userIntegrationFactory->make();
            $users = $driver->searchUsers($query);
            $activeDriver = strtolower(trim($this->settingModel->get('active_auth_driver', 'local') ?? 'local'));

            if ($activeDriver !== 'local') {
                $users = array_map(
                    fn (array $user): array => $this->personnelModel->syncFromDirectory($user),
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

    public function storePersonnel(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('manual_user_invalid_payload'),
            ]);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));

        try {
            $person = $this->personnelModel->createManual($name, $email);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('manual_user_create_success'),
            'data' => $person,
        ]);
    }

    public function personnelIndex(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($queryParams['per_page'] ?? Personnel::PAGE_SIZE)));
        $search = trim((string) ($queryParams['q'] ?? ''));

        $result = $this->personnelModel->findPaginated(
            $page,
            $perPage,
            $search !== '' ? $search : null
        );
        $assetCounts = $this->personnelModel->assignedAssetCountsForIds(
            array_map(static fn (array $person): int => (int) $person['id'], $result['data'])
        );

        $data = array_map(
            static function (array $person) use ($assetCounts): array {
                $personnelId = (int) $person['id'];

                return [
                    ...$person,
                    'assigned_asset_count' => $assetCounts[$personnelId] ?? 0,
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

        if (!in_array($activeDriver, [Personnel::PROVIDER_LDAP, Personnel::PROVIDER_GOOGLE], true)) {
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

        $stats = $this->personnelModel->syncDirectory($directoryUsers, $activeDriver);

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

    public function offboard(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $personnelId = (int) ($args['id'] ?? 0);

        if ($personnelId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('offboard_invalid_user'),
            ]);
        }

        $person = $this->personnelModel->findById($personnelId);

        if ($person === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('offboard_user_not_found'),
            ]);
        }

        if (($person['status'] ?? Personnel::STATUS_ACTIVE) === Personnel::STATUS_OFFBOARDED) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('offboard_already_offboarded'),
            ]);
        }

        $assets = $this->assetModel->findAllByPersonnelId($personnelId);
        $reclaimedAssets = [];

        foreach ($assets as $asset) {
            $assetId = (int) $asset['id'];

            $updated = $this->assetModel->update($assetId, [
                'personnel_id' => null,
                'status' => 'storage',
            ]);

            if ($updated === null) {
                continue;
            }

            $this->assetHistoryModel->log(
                $assetId,
                'offboarded',
                $this->sessionAuthService->userId(),
                $personnelId,
                $this->appendClientIpToNotes(self::OFFBOARD_HISTORY_NOTE, $request)
            );

            $reclaimedAssets[] = [
                'id' => $assetId,
                'asset_tag' => (string) $updated['asset_tag'],
                'name' => (string) $updated['name'],
            ];
        }

        $this->personnelModel->markOffboarded($personnelId);

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('offboard_success'),
            'data' => [
                'personnel_id' => $personnelId,
                'reclaimed_assets' => $reclaimedAssets,
                'reclaimed_count' => count($reclaimedAssets),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePayload(ServerRequestInterface $request): ?array
    {
        $parsedBody = $request->getParsedBody();

        if (is_array($parsedBody) && $parsedBody !== []) {
            return $parsedBody;
        }

        $rawBody = (string) $request->getBody();

        if ($rawBody === '') {
            return is_array($parsedBody) ? $parsedBody : [];
        }

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            return is_array($parsedBody) ? $parsedBody : null;
        }
    }

    private function appendClientIpToNotes(?string $notes, ServerRequestInterface $request): ?string
    {
        $clientIp = $this->clientIpResolver->resolveFromRequest($request);

        if ($clientIp === '') {
            return $notes;
        }

        $suffix = sprintf('[client_ip: %s]', $clientIp);

        if ($notes === null || trim($notes) === '') {
            return $suffix;
        }

        return $notes . ' ' . $suffix;
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
