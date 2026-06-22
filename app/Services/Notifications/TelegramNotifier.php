<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Services\AppLogger;

class TelegramNotifier
{
    public function __construct(
        private readonly AppLogger $appLogger
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->botToken() !== '' && $this->chatId() !== '';
    }

    public function sendHtml(string $message): bool
    {
        $token = $this->botToken();
        $chatId = $this->chatId();

        if ($token === '' || $chatId === '') {
            $this->appLogger->log('telegram.skipped', [
                'reason' => 'telegram_not_configured',
            ]);

            return false;
        }

        $url = sprintf('https://api.telegram.org/bot%s/sendMessage', $token);
        $payload = http_build_query([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => 'true',
        ]);

        $handle = curl_init($url);

        if ($handle === false) {
            $this->appLogger->log('telegram.failed', [
                'reason' => 'curl_init_failed',
            ]);

            return false;
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $responseBody = curl_exec($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);

        if ($responseBody === false || $httpCode < 200 || $httpCode >= 300) {
            $this->appLogger->log('telegram.failed', [
                'http_code' => $httpCode,
                'error' => $curlError !== '' ? $curlError : 'unexpected_response',
                'response' => is_string($responseBody) ? mb_substr($responseBody, 0, 500) : null,
            ]);

            return false;
        }

        $decoded = json_decode((string) $responseBody, true);

        if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
            $this->appLogger->log('telegram.failed', [
                'reason' => 'api_rejected',
                'response' => is_string($responseBody) ? mb_substr($responseBody, 0, 500) : null,
            ]);

            return false;
        }

        return true;
    }

    public static function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function botToken(): string
    {
        return trim((string) ($_ENV['TELEGRAM_BOT_TOKEN'] ?? ''));
    }

    private function chatId(): string
    {
        return trim((string) ($_ENV['TELEGRAM_CHAT_ID'] ?? ''));
    }
}
