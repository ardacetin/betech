<?php

declare(strict_types=1);

namespace App\Services;

use JsonException;

class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private readonly string $secretKey
    ) {
    }

    public function verify(string $token, string $remoteIp): bool
    {
        if ($this->secretKey === '' || trim($token) === '') {
            return false;
        }

        $responseBody = $this->postSiteVerify([
            'secret' => $this->secretKey,
            'response' => trim($token),
            'remoteip' => $remoteIp,
        ]);

        if ($responseBody === null) {
            return false;
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return ($decoded['success'] ?? false) === true;
    }

    /**
     * @param array<string, string> $fields
     */
    private function postSiteVerify(array $fields): ?string
    {
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
        curl_close($curl);

        if ($response === false || $statusCode >= 400) {
            return null;
        }

        return (string) $response;
    }
}
