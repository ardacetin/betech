<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Services\AppLogger;
use App\Services\SecurityHeaders;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Handlers\ErrorHandler;
use Slim\Interfaces\CallableResolverInterface;

class HttpErrorHandler extends ErrorHandler
{
    private const GENERIC_ERROR_MESSAGE = 'Sunucu hatası oluştu. Lütfen sistem yöneticisiyle iletişime geçin.';

    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
        private readonly AppLogger $appLogger,
        private readonly bool $isHttps = false,
    ) {
        parent::__construct($callableResolver, $responseFactory);
    }

    protected function writeToErrorLog(): void
    {
        if (!$this->logErrors) {
            return;
        }

        $this->appLogger->logException($this->request, $this->exception);
    }

    protected function respond(): ResponseInterface
    {
        if ($this->displayErrorDetails) {
            return SecurityHeaders::apply(parent::respond(), $this->isHttps);
        }

        $statusCode = $this->statusCode >= 400 ? $this->statusCode : 500;
        $response = $this->responseFactory->createResponse($statusCode);

        if ($this->shouldReturnJson()) {
            $response->getBody()->write(json_encode([
                'error' => self::GENERIC_ERROR_MESSAGE,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            return SecurityHeaders::apply(
                $response->withHeader('Content-Type', 'application/json; charset=utf-8'),
                $this->isHttps
            );
        }

        $response->getBody()->write(
            '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><title>Hata</title></head>'
            . '<body style="font-family:system-ui,sans-serif;padding:2rem;color:#18181b;">'
            . '<h1>Bir hata oluştu</h1>'
            . '<p>' . htmlspecialchars(self::GENERIC_ERROR_MESSAGE, ENT_QUOTES, 'UTF-8') . '</p>'
            . '</body></html>'
        );

        return SecurityHeaders::apply(
            $response->withHeader('Content-Type', 'text/html; charset=utf-8'),
            $this->isHttps
        );
    }

    private function shouldReturnJson(): bool
    {
        $path = $this->request->getUri()->getPath();

        if (str_starts_with($path, '/api/')) {
            return true;
        }

        $accept = $this->request->getHeaderLine('Accept');

        return str_contains($accept, 'application/json');
    }
}
