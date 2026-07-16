<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\Setting;
use App\Services\Auth\SessionAuthService;
use Medoo\Medoo;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

class AssetPublicViewService
{
    public const ACCESS_PUBLIC = 'public';
    public const ACCESS_AUTHENTICATED = 'authenticated';
    public const ACCESS_NETWORK = 'network';

    /** @var list<string> */
    public const CONFIGURABLE_FIELDS = [
        'status',
        'assigned_to',
        'model',
        'brand',
        'type',
        'location',
        'building',
        'serial_number',
        'mac_address_1',
        'mac_address_2',
    ];

    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly Asset $assetModel,
        private readonly Setting $settingModel,
        private readonly SessionAuthService $sessionAuthService,
        private readonly ClientIpResolver $clientIpResolver,
        private readonly string $appUrl
    ) {
    }

    /**
     * @return array{
     *     access_mode: string,
     *     allowed_cidrs: list<string>,
     *     visible_fields: array<string, bool>
     * }
     */
    public function getConfig(): array
    {
        $stored = $this->settingModel->getJson('qr_public_view_config', []);

        return $this->normalizeConfig(is_array($stored) ? $stored : []);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{
     *     access_mode: string,
     *     allowed_cidrs: list<string>,
     *     visible_fields: array<string, bool>
     * }
     */
    public function saveConfig(array $payload): array
    {
        $normalized = $this->normalizeConfig($payload);
        $this->settingModel->setJson('qr_public_view_config', $normalized);

        return $normalized;
    }

    public function ensureActiveToken(int $assetId): string
    {
        if ($assetId <= 0) {
            throw new RuntimeException('Invalid asset id.');
        }

        if ($this->assetModel->findById($assetId) === null) {
            throw new RuntimeException(__('assign_asset_not_found'));
        }

        if (!$this->tokensTableReady()) {
            throw new RuntimeException(__('asset_public_view_unavailable'));
        }

        $existing = $this->db()->get('asset_public_view_tokens', ['token', 'is_active'], [
            'asset_id' => $assetId,
        ]);

        if (is_array($existing) && (int) ($existing['is_active'] ?? 0) === 1) {
            $token = trim((string) ($existing['token'] ?? ''));

            if ($token !== '') {
                return $token;
            }
        }

        return $this->rotateToken($assetId);
    }

    public function rotateToken(int $assetId): string
    {
        if ($assetId <= 0 || $this->assetModel->findById($assetId) === null) {
            throw new RuntimeException(__('assign_asset_not_found'));
        }

        if (!$this->tokensTableReady()) {
            throw new RuntimeException(__('asset_public_view_unavailable'));
        }

        $token = $this->generateToken();
        $now = date('Y-m-d H:i:s');

        if ($this->db()->has('asset_public_view_tokens', ['asset_id' => $assetId])) {
            $this->db()->update('asset_public_view_tokens', [
                'token' => $token,
                'is_active' => 1,
                'rotated_at' => $now,
                'revoked_at' => null,
            ], ['asset_id' => $assetId]);
        } else {
            $this->db()->insert('asset_public_view_tokens', [
                'asset_id' => $assetId,
                'token' => $token,
                'is_active' => 1,
                'created_at' => $now,
            ]);
        }

        return $token;
    }

    public function revokeToken(int $assetId): void
    {
        if (!$this->tokensTableReady()) {
            throw new RuntimeException(__('asset_public_view_unavailable'));
        }

        if (!$this->db()->has('asset_public_view_tokens', ['asset_id' => $assetId])) {
            return;
        }

        $this->db()->update('asset_public_view_tokens', [
            'is_active' => 0,
            'revoked_at' => date('Y-m-d H:i:s'),
        ], ['asset_id' => $assetId]);
    }

    public function findAssetIdByToken(string $token): ?int
    {
        $normalized = strtolower(trim($token));

        if ($normalized === '' || !preg_match('/^[a-f0-9]{64}$/', $normalized) || !$this->tokensTableReady()) {
            return null;
        }

        $row = $this->db()->get('asset_public_view_tokens', ['asset_id', 'is_active'], [
            'token' => $normalized,
        ]);

        if (!is_array($row) || (int) ($row['is_active'] ?? 0) !== 1) {
            return null;
        }

        $assetId = (int) ($row['asset_id'] ?? 0);

        return $assetId > 0 ? $assetId : null;
    }

    public function buildPublicUrl(string $token): string
    {
        return rtrim($this->appUrl, '/') . '/assets/view/' . $token;
    }

    /**
     * @return array{allowed: bool, reason: string|null, redirect_login: bool}
     */
    public function evaluateAccess(ServerRequestInterface $request): array
    {
        $config = $this->getConfig();
        $mode = $config['access_mode'];

        if ($mode === self::ACCESS_PUBLIC) {
            return ['allowed' => true, 'reason' => null, 'redirect_login' => false];
        }

        $this->sessionAuthService->ensureSessionStarted();
        $isAuthenticated = $this->sessionAuthService->isAuthenticated();

        if ($mode === self::ACCESS_AUTHENTICATED) {
            if ($isAuthenticated) {
                return ['allowed' => true, 'reason' => null, 'redirect_login' => false];
            }

            return [
                'allowed' => false,
                'reason' => __('asset_public_view_login_required'),
                'redirect_login' => true,
            ];
        }

        // network: corporate CIDR OR authenticated
        if ($isAuthenticated) {
            return ['allowed' => true, 'reason' => null, 'redirect_login' => false];
        }

        $clientIp = $this->clientIpResolver->resolveFromRequest($request);

        if ($this->clientIpResolver->ipMatchesAnyCidr($clientIp, $config['allowed_cidrs'])) {
            return ['allowed' => true, 'reason' => null, 'redirect_login' => false];
        }

        return [
            'allowed' => false,
            'reason' => __('asset_public_view_network_denied'),
            'redirect_login' => false,
        ];
    }

    /**
     * @param array<string, mixed> $asset
     *
     * @return list<array{label: string, value: string}>
     */
    public function buildVisibleAttributeRows(array $asset): array
    {
        $config = $this->getConfig();
        $visible = $config['visible_fields'];

        $fieldMeta = [
            'model' => __('col_model'),
            'brand' => __('col_brand'),
            'serial_number' => __('label_serial_number'),
            'type' => __('col_category'),
            'location' => __('col_location'),
            'building' => __('col_building'),
            'mac_address_1' => __('label_mac_address_1'),
            'mac_address_2' => __('label_mac_address_2'),
        ];

        $rows = [];

        foreach ($fieldMeta as $key => $label) {
            if (!($visible[$key] ?? false)) {
                continue;
            }

            $value = trim((string) ($asset[$key] ?? ''));

            if ($value === '') {
                continue;
            }

            $rows[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        return $rows;
    }

    public function shouldShowAssignedTo(): bool
    {
        return $this->shouldShowField('assigned_to');
    }

    public function shouldShowStatus(): bool
    {
        return $this->shouldShowField('status');
    }

    public function shouldShowField(string $field): bool
    {
        return (bool) ($this->getConfig()['visible_fields'][$field] ?? false);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{
     *     access_mode: string,
     *     allowed_cidrs: list<string>,
     *     visible_fields: array<string, bool>
     * }
     */
    private function normalizeConfig(array $payload): array
    {
        $mode = strtolower(trim((string) ($payload['access_mode'] ?? self::ACCESS_PUBLIC)));

        if (!in_array($mode, [self::ACCESS_PUBLIC, self::ACCESS_AUTHENTICATED, self::ACCESS_NETWORK], true)) {
            $mode = self::ACCESS_PUBLIC;
        }

        $cidrs = [];
        $rawCidrs = $payload['allowed_cidrs'] ?? [];

        if (is_string($rawCidrs)) {
            $rawCidrs = preg_split('/[\s,]+/', $rawCidrs) ?: [];
        }

        if (is_array($rawCidrs)) {
            foreach ($rawCidrs as $cidr) {
                $entry = trim((string) $cidr);

                if ($entry === '' || !$this->isValidCidrOrIp($entry)) {
                    continue;
                }

                $cidrs[$entry] = true;
            }
        }

        $defaults = $this->defaultVisibleFields();
        $incoming = is_array($payload['visible_fields'] ?? null) ? $payload['visible_fields'] : [];
        $visible = [];

        foreach (self::CONFIGURABLE_FIELDS as $field) {
            if (array_key_exists($field, $incoming)) {
                $visible[$field] = (bool) $incoming[$field];
            } else {
                $visible[$field] = $defaults[$field];
            }
        }

        return [
            'access_mode' => $mode,
            'allowed_cidrs' => array_keys($cidrs),
            'visible_fields' => $visible,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function defaultVisibleFields(): array
    {
        return [
            'status' => true,
            'assigned_to' => true,
            'model' => true,
            'brand' => true,
            'type' => true,
            'location' => true,
            'building' => true,
            'serial_number' => false,
            'mac_address_1' => false,
            'mac_address_2' => false,
        ];
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function tokensTableReady(): bool
    {
        try {
            $this->db()->query('SELECT 1 FROM asset_public_view_tokens LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function isValidCidrOrIp(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if (!str_contains($value, '/')) {
            return false;
        }

        [$subnet, $maskRaw] = explode('/', $value, 2);
        $mask = (int) $maskRaw;

        if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $mask >= 0 && $mask <= 32;
        }

        if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return $mask >= 0 && $mask <= 128;
        }

        return false;
    }

    private function db(): Medoo
    {
        return $this->databaseService->getConnection();
    }
}
