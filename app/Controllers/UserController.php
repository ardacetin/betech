<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Models\AssetHistory;
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
        private readonly AssetHistory $assetHistoryModel
    ) {
    }

    public function search(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $query = trim((string) ($queryParams['q'] ?? ''));

        try {
            $driver = $this->userIntegrationFactory->make();
            $users = $driver->searchUsers($query);
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

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $users = $this->userModel->findAllForPersonnel();
        $assetCounts = $this->userModel->assignedAssetCountsByUserId();

        $data = array_map(
            static function (array $user) use ($assetCounts): array {
                $userId = (int) $user['id'];

                return [
                    ...$user,
                    'assigned_asset_count' => $assetCounts[$userId] ?? 0,
                ];
            },
            $users
        );

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $data,
        ]);
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
