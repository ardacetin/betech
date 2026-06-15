<?php

declare(strict_types=1);

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrCodeService
{
    public function __construct(
        private readonly string $appUrl
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
        return rtrim($this->appUrl, '/') . '/assets/view/' . $assetId;
    }
}
