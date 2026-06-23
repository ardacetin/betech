<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\IpAddress;
use App\Models\IpNetwork;
use App\Services\AuditLogger;
use App\Services\Auth\SessionAuthService;
use App\Services\IpamCsvImportService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

class IpNetworkController
{
    private const MAX_IMPORT_BYTES = 5_242_880;

    public function __construct(
        private readonly IpNetwork $ipNetworkModel,
        private readonly IpAddress $ipAddressModel,
        private readonly Asset $assetModel,
        private readonly IpamCsvImportService $ipamCsvImportService,
        private readonly SessionAuthService $sessionAuthService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $this->ipNetworkModel->findAll(),
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ipam_invalid_payload'),
            ]);
        }

        try {
            $network = $this->ipNetworkModel->create(
                (string) ($payload['name'] ?? ''),
                (string) ($payload['network_address'] ?? ''),
                (int) ($payload['cidr'] ?? 0),
                array_key_exists('gateway', $payload) ? (string) $payload['gateway'] : null,
                isset($payload['vlan_id']) && (int) $payload['vlan_id'] > 0 ? (int) $payload['vlan_id'] : null,
                array_key_exists('description', $payload) ? (string) $payload['description'] : null,
                !array_key_exists('auto_generate', $payload) || (bool) $payload['auto_generate']
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('ipam_network_create_error'),
            ]);
        }

        return $this->jsonResponse($response, 201, [
            'status' => 'success',
            'message' => __('ipam_network_create_success'),
            'data' => $network,
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $networkId = (int) ($args['id'] ?? 0);

        if ($networkId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ipam_invalid_id'),
            ]);
        }

        $network = $this->ipNetworkModel->findById($networkId);

        if ($network === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ipam_network_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => $network,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $networkId = (int) ($args['id'] ?? 0);

        if ($networkId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ipam_invalid_id'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ipam_invalid_payload'),
            ]);
        }

        try {
            $network = $this->ipNetworkModel->update($networkId, $payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('ipam_network_update_error'),
            ]);
        }

        if ($network === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ipam_network_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('ipam_network_update_success'),
            'data' => $network,
        ]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $networkId = (int) ($args['id'] ?? 0);

        if ($networkId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ipam_invalid_id'),
            ]);
        }

        if (!$this->ipNetworkModel->delete($networkId)) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ipam_network_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('ipam_network_delete_success'),
        ]);
    }

    public function addresses(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $networkId = (int) ($args['id'] ?? 0);

        if ($networkId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ipam_invalid_id'),
            ]);
        }

        $network = $this->ipNetworkModel->findById($networkId);

        if ($network === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ipam_network_not_found'),
            ]);
        }

        $query = $request->getQueryParams();
        $status = isset($query['status']) ? (string) $query['status'] : null;

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'data' => [
                'network' => $network,
                'addresses' => $this->ipAddressModel->findByNetworkId($networkId, $status),
            ],
        ]);
    }

    public function exportNetworkAddresses(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $networkId = (int) ($args['id'] ?? 0);

        if ($networkId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ipam_invalid_id'),
            ]);
        }

        $network = $this->ipNetworkModel->findById($networkId);

        if ($network === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ipam_network_not_found'),
            ]);
        }

        $addresses = $this->ipAddressModel->findByNetworkId($networkId);
        $csv = $this->ipamCsvImportService->exportNetworkAddressesToCsv($addresses);
        $filename = $this->buildNetworkExportFilename($network);

        $response->getBody()->write("\xEF\xBB\xBF" . $csv);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function generateAddresses(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $networkId = (int) ($args['id'] ?? 0);

        if ($networkId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ipam_invalid_id'),
            ]);
        }

        try {
            $generated = $this->ipNetworkModel->generateAddresses($networkId);
            $network = $this->ipNetworkModel->findById($networkId);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('ipam_generate_error'),
            ]);
        }

        if ($network === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ipam_network_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => sprintf(__('ipam_generate_success'), $generated),
            'data' => $network,
        ]);
    }

    public function updateAddress(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $addressId = (int) ($args['id'] ?? 0);

        if ($addressId <= 0) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ipam_invalid_id'),
            ]);
        }

        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ipam_invalid_payload'),
            ]);
        }

        if (array_key_exists('asset_id', $payload) && $payload['asset_id'] !== null && (int) $payload['asset_id'] > 0) {
            $asset = $this->assetModel->findById((int) $payload['asset_id']);

            if ($asset === null) {
                return $this->jsonResponse($response, 422, [
                    'status' => 'error',
                    'message' => __('ipam_asset_not_found'),
                ]);
            }
        }

        try {
            $address = $this->ipAddressModel->update($addressId, $payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            return $this->jsonResponse($response, 500, [
                'status' => 'error',
                'message' => __('ipam_address_update_error'),
            ]);
        }

        if ($address === null) {
            return $this->jsonResponse($response, 404, [
                'status' => 'error',
                'message' => __('ipam_address_not_found'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => __('ipam_address_update_success'),
            'data' => $address,
        ]);
    }

    public function bulkUpdateAddresses(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->resolvePayload($request);

        if ($payload === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('ipam_invalid_payload'),
            ]);
        }

        $ids = $payload['ids'] ?? [];

        if (!is_array($ids)) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('ipam_bulk_update_ids_required'),
            ]);
        }

        $fields = $payload['fields'] ?? [];

        if (!is_array($fields)) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('ipam_bulk_update_fields_required'),
            ]);
        }

        $networkId = isset($payload['network_id']) ? (int) $payload['network_id'] : 0;

        try {
            $normalizedIds = array_values(array_unique(array_filter(
                array_map(static fn (mixed $id): int => (int) $id, $ids),
                static fn (int $id): bool => $id > 0
            )));

            if ($networkId > 0) {
                $existing = $this->ipAddressModel->findByIds($normalizedIds);

                foreach ($existing as $address) {
                    if ((int) ($address['network_id'] ?? 0) !== $networkId) {
                        return $this->jsonResponse($response, 422, [
                            'status' => 'error',
                            'message' => __('ipam_bulk_update_network_mismatch'),
                        ]);
                    }
                }
            }

            $result = $this->ipAddressModel->bulkUpdate($normalizedIds, $fields);
            $actorUserId = $this->sessionAuthService->userId();
            $afterById = [];

            foreach ($result['after'] as $afterRow) {
                $afterById[(int) ($afterRow['id'] ?? 0)] = $afterRow;
            }

            foreach ($result['before'] as $beforeRow) {
                $addressId = (int) ($beforeRow['id'] ?? 0);
                $afterRow = $afterById[$addressId] ?? null;

                if ($addressId <= 0 || $afterRow === null) {
                    continue;
                }

                $this->auditLogger->logFromRequest(
                    $request,
                    $actorUserId,
                    AuditLog::ACTION_UPDATED,
                    AuditLog::ENTITY_IP_ADDRESS,
                    $addressId,
                    $this->auditSnapshot($beforeRow),
                    $this->auditSnapshot($afterRow)
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
                'message' => __('ipam_bulk_update_error'),
            ]);
        }

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => sprintf(__('ipam_bulk_update_success'), (int) $result['updated']),
            'data' => [
                'updated' => (int) $result['updated'],
                'addresses' => $result['after'],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function auditSnapshot(array $row): array
    {
        return [
            'ip_address' => (string) ($row['ip_address'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'notes' => $row['notes'] !== null ? (string) $row['notes'] : null,
            'network_id' => (int) ($row['network_id'] ?? 0),
        ];
    }

    public function networkImportTemplate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $csv = "\xEF\xBB\xBF" . IpamCsvImportService::networkTemplateCsvContent();
        $response->getBody()->write($csv);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="ip_networks_import_template.csv"');
    }

    public function addressImportTemplate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $csv = "\xEF\xBB\xBF" . IpamCsvImportService::addressTemplateCsvContent();
        $response->getBody()->write($csv);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="ip_addresses_import_template.csv"');
    }

    public function importNetworks(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->handleImport($request, $response, 'networks');
    }

    public function importAddresses(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->handleImport($request, $response, 'addresses');
    }

    private function handleImport(ServerRequestInterface $request, ResponseInterface $response, string $type): ResponseInterface
    {
        $csvContent = $this->resolveUploadedCsv($request);

        if ($csvContent === null) {
            return $this->jsonResponse($response, 400, [
                'status' => 'error',
                'message' => __('import_file_missing'),
            ]);
        }

        if ($csvContent === '') {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('import_csv_empty'),
            ]);
        }

        if (strlen($csvContent) > self::MAX_IMPORT_BYTES) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('import_file_too_large'),
            ]);
        }

        $result = $type === 'networks'
            ? $this->ipamCsvImportService->importNetworksFromString($csvContent)
            : $this->ipamCsvImportService->importAddressesFromString($csvContent);

        $imported = (int) $result['imported'];
        $failed = (int) $result['failed'];

        if ($imported === 0 && $failed > 0) {
            return $this->jsonResponse($response, 422, [
                'status' => 'error',
                'message' => __('import_all_failed'),
                'errors' => $result['errors'],
            ]);
        }

        $message = $failed > 0
            ? sprintf(__('ipam_import_partial_success'), $imported, $failed)
            : sprintf(__('ipam_import_success'), $imported);

        return $this->jsonResponse($response, 200, [
            'status' => 'success',
            'message' => $message,
            'data' => $result,
        ]);
    }

    private function resolveUploadedCsv(ServerRequestInterface $request): ?string
    {
        $uploadedFiles = $request->getUploadedFiles();

        foreach (['file', 'csv'] as $key) {
            if (!isset($uploadedFiles[$key])) {
                continue;
            }

            $file = $uploadedFiles[$key];

            if (!$file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
                return null;
            }

            $stream = $file->getStream();
            $stream->rewind();

            return $stream->getContents();
        }

        return null;
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
     * @param array<string, mixed> $network
     */
    private function buildNetworkExportFilename(array $network): string
    {
        $slug = strtolower(trim((string) ($network['name'] ?? 'network')));
        $slug = preg_replace('/[^a-z0-9._-]+/i', '-', $slug) ?? 'network';
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'network';
        }

        return sprintf('ipam-network-%s-%s.csv', $slug, date('Y-m-d'));
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
