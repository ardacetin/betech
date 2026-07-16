<?php

declare(strict_types=1);

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrCodeService
{
    public function __construct(
        private readonly string $appUrl,
        private readonly ?AssetPublicViewService $assetPublicViewService = null
    ) {
    }

    public function generateForAsset(string $assetTag, int $assetId): string
    {
        $options = new QROptions([
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'scale' => 4,
        ]);

        return (new QRCode($options))->render($this->buildAssetViewUrl($assetId));
    }

    public function buildAssetViewUrl(int $assetId): string
    {
        if ($this->assetPublicViewService !== null) {
            try {
                $token = $this->assetPublicViewService->ensureActiveToken($assetId);

                return $this->assetPublicViewService->buildPublicUrl($token);
            } catch (\Throwable) {
                // Fall through to a non-enumerable placeholder rather than leaking numeric IDs.
            }
        }

        return rtrim($this->appUrl, '/') . '/assets/view/unavailable';
    }
}
