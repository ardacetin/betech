<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Http\HttpErrorResponses;
use App\Services\AppLogger;
use App\Services\SecurityHeaders;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Exception\HttpException;
use Slim\Handlers\ErrorHandler;
use Slim\Interfaces\CallableResolverInterface;

class HttpErrorHandler extends ErrorHandler
{
    private const GENERIC_ERROR_MESSAGE = 'Sunucu hatası oluştu. Lütfen sistem yöneticisiyle iletişime geçin.';

    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
        private readonly AppLogger $appLogger,
        private readonly HttpErrorResponses $httpErrorResponses,
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
        $this->writeToErrorLog();

        $statusCode = $this->resolveStatusCode();

        if ($statusCode === 403) {
            return SecurityHeaders::apply(
                $this->httpErrorResponses->forbidden($this->request),
                $this->isHttps
            );
        }

        if ($statusCode === 404) {
            return SecurityHeaders::apply(
                $this->httpErrorResponses->notFound($this->request),
                $this->isHttps
            );
        }

        if ($this->displayErrorDetails && $statusCode >= 500) {
            return SecurityHeaders::apply(parent::respond(), $this->isHttps);
        }

        $response = $this->responseFactory->createResponse($statusCode >= 400 ? $statusCode : 500);

        if ($this->httpErrorResponses->wantsJson($this->request)) {
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

    private function resolveStatusCode(): int
    {
        if ($this->exception instanceof HttpException) {
            return $this->exception->getCode();
        }

        if ($this->statusCode >= 400) {
            return $this->statusCode;
        }

        return 500;
    }
}
