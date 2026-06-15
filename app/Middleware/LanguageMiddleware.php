<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Translator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LanguageMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Translator $translator
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $queryLang = $request->getQueryParams()['lang'] ?? null;

        if (is_string($queryLang) && in_array($queryLang, Translator::SUPPORTED_LOCALES, true)) {
            $_SESSION['lang'] = $queryLang;
        }

        $locale = $_SESSION['lang'] ?? Translator::DEFAULT_LOCALE;

        if (!is_string($locale) || !in_array($locale, Translator::SUPPORTED_LOCALES, true)) {
            $locale = Translator::DEFAULT_LOCALE;
        }

        $this->translator->setLocale($locale);

        return $handler->handle($request);
    }
}
