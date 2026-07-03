<?php

declare(strict_types=1);

namespace App\Services;

use JsonException;

class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private readonly string $secretKey,
        private readonly string $siteKey = ''
    ) {
    }

    public function isEnabled(): bool
    {
        return trim($this->secretKey) !== '';
    }

    public function siteKey(): string
    {
        $siteKey = trim($this->siteKey);

        return $siteKey !== '' ? $siteKey : '0x4AAAAAACLf0FH4wQScyWEe';
    }

    /**
     * @return array{accepted: bool, transport_error: bool}
     */
    public function verify(string $token, string $remoteIp): array
    {
        if (!$this->isEnabled()) {
            return [
                'accepted' => true,
                'transport_error' => false,
            ];
        }

        if (trim($token) === '') {
            return [
                'accepted' => false,
                'transport_error' => false,
            ];
        }

        $responseBody = $this->postSiteVerify([
            'secret' => $this->secretKey,
            'response' => trim($token),
            'remoteip' => $remoteIp,
        ]);

        if ($responseBody === null) {
            return [
                'accepted' => false,
                'transport_error' => true,
            ];
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [
                'accepted' => false,
                'transport_error' => true,
            ];
        }

        return [
            'accepted' => ($decoded['success'] ?? false) === true,
            'transport_error' => false,
        ];
    }

    /**
     * @param array<string, string> $fields
     */
    private function postSiteVerify(array $fields): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $curl = curl_init(self::VERIFY_URL);

        if ($curl === false) {
            return null;
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($response === false || $statusCode >= 400) {
            error_log(sprintf(
                '[TurnstileVerifier] siteverify request failed (HTTP %d): %s',
                $statusCode,
                $curlError !== '' ? $curlError : 'empty response'
            ));

            return null;
        }

        return (string) $response;
    }
}
