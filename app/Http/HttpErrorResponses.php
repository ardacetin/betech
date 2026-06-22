<?php

declare(strict_types=1);

namespace App\Http;

use App\Services\Translator;
use App\Services\ViewRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class HttpErrorResponses
{
    public function __construct(
        private readonly ViewRenderer $viewRenderer
    ) {
    }

    public function forbidden(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->wantsJson($request)) {
            return $this->jsonResponse(403, [
                'error' => __('error_forbidden_json'),
            ]);
        }

        return $this->htmlResponse(403, 'errors/403', [
            'pageTitle' => __('error_403_title'),
            'heading' => __('error_403_title'),
            'message' => __('error_403_message'),
            'appName' => __('app_name'),
            'locale' => Translator::instance()->getLocale(),
        ]);
    }

    public function notFound(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->wantsJson($request)) {
            return $this->jsonResponse(404, [
                'error' => __('error_not_found_json'),
            ]);
        }

        return $this->htmlResponse(404, 'errors/404', [
            'pageTitle' => __('error_404_title'),
            'heading' => __('error_404_title'),
            'message' => __('error_404_message'),
            'appName' => __('app_name'),
            'locale' => Translator::instance()->getLocale(),
        ]);
    }

    public function wantsJson(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        if (str_starts_with($path, '/api/')) {
            return true;
        }

        $accept = $request->getHeaderLine('Accept');

        if (str_contains($accept, 'application/json')) {
            return true;
        }

        $requestedWith = $request->getHeaderLine('X-Requested-With');

        return strcasecmp($requestedWith, 'XMLHttpRequest') === 0;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(int $status, array $payload): ResponseInterface
    {
        $response = new Response($status);
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function htmlResponse(int $status, string $view, array $data): ResponseInterface
    {
        $html = $this->viewRenderer->render($view, $data, null);
        $response = new Response($status);
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
