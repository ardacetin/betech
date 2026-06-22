<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\Personnel;
use App\Models\User;
use App\Services\Auth\LdapAuthenticator;
use App\Services\Auth\SessionAuthService;
use App\Services\AppLogger;
use App\Services\AuditLogger;
use App\Services\ClientIpResolver;
use App\Services\LoginAttemptService;
use App\Services\Translator;
use App\Services\ViewRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthController
{
    /**
     * @param array<string, mixed> $appConfig
     */
    public function __construct(
        private readonly array $appConfig,
        private readonly Personnel $personnelModel,
        private readonly SessionAuthService $sessionAuthService,
        private readonly LoginAttemptService $loginAttemptService,
        private readonly ClientIpResolver $clientIpResolver,
        private readonly LdapAuthenticator $ldapAuthenticator,
        private readonly ViewRenderer $viewRenderer,
        private readonly AuditLogger $auditLogger,
        private readonly AppLogger $appLogger
    ) {
    }

    public function showLoginForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        Translator::instance()->setLocale('tr');

        if ($this->sessionAuthService->isAuthenticated()) {
            return $response
                ->withHeader('Location', $this->resolveRedirectTarget($request))
                ->withStatus(302);
        }

        $queryParams = $request->getQueryParams();
        $errorKey = trim((string) ($queryParams['error'] ?? ''));

        $html = $this->viewRenderer->render('login', [
            'pageTitle' => __('login_page_title'),
            'appName' => __('app_name'),
            'locale' => 'tr',
            'errorMessage' => $this->resolveErrorMessage($errorKey),
            'redirectTarget' => $this->resolveRedirectTarget($request),
            'csrfToken' => $this->sessionAuthService->getOrCreateCsrfToken(),
        ], null);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function showUnauthorized(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        Translator::instance()->setLocale('tr');

        $html = $this->viewRenderer->render('errors/403', [
            'pageTitle' => __('error_403_title'),
            'heading' => __('error_403_title'),
            'message' => __('error_403_message'),
            'appName' => __('app_name'),
            'locale' => Translator::instance()->getLocale(),
        ], null);

        $response->getBody()->write($html);

        return $response->withStatus(403)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        Translator::instance()->setLocale('tr');

        $clientIp = $this->clientIpResolver->resolveFromRequest($request);
        $parsedBody = $request->getParsedBody();
        $payload = is_array($parsedBody) ? $parsedBody : [];
        $identifier = trim((string) ($payload['identifier'] ?? $payload['username'] ?? ''));
        $password = trim((string) ($payload['password'] ?? $_POST['password'] ?? ''));
        $redirectTarget = $this->sanitizeRedirect((string) ($payload['redirect'] ?? ''));

        return $this->completeLdapLogin($response, $clientIp, $identifier, $password, $redirectTarget, 'form');
    }

    public function apiLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        Translator::instance()->setLocale('tr');

        $clientIp = $this->clientIpResolver->resolveFromRequest($request);
        $parsedBody = $request->getParsedBody();
        $payload = is_array($parsedBody) ? $parsedBody : [];
        $identifier = trim((string) ($payload['identifier'] ?? $payload['username'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($identifier === '' || $password === '') {
            $this->logFailedLogin($clientIp, $identifier, 'missing_credentials', false, 'api_ldap');

            return $this->jsonAuthError($response, 'login_missing_credentials', 422);
        }

        $person = $this->authenticateWithLdap($clientIp, $identifier, $password, 'api_ldap');

        if ($person === null) {
            return $this->jsonAuthError($response, 'login_ldap_failed', 401);
        }

        $this->loginAttemptService->clearFailures($clientIp);
        $this->sessionLoginFromPersonnel($person);
        $this->auditLogin($clientIp, $person, 'ldap', $identifier);

        return $this->jsonAuthSuccess($response, $person);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->sessionAuthService->logout();

        return $response
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }

    private function completeLdapLogin(
        ResponseInterface $response,
        string $clientIp,
        string $identifier,
        string $password,
        string $redirectTarget,
        string $method
    ): ResponseInterface {
        if ($identifier === '' || $password === '') {
            $this->logFailedLogin($clientIp, $identifier, 'missing_credentials', false, $method);

            return $this->redirectWithError($response, 'login_missing_credentials', $redirectTarget);
        }

        $person = $this->authenticateWithLdap($clientIp, $identifier, $password, $method);

        if ($person === null) {
            return $this->redirectWithError($response, 'login_ldap_failed', $redirectTarget);
        }

        $this->loginAttemptService->clearFailures($clientIp);
        $this->sessionLoginFromPersonnel($person);
        $this->auditLogin($clientIp, $person, 'ldap', $identifier);

        return $response->withHeader('Location', $redirectTarget)->withStatus(302);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function authenticateWithLdap(
        string $clientIp,
        string $identifier,
        string $password,
        string $method
    ): ?array {
        $ldapProfile = $this->ldapAuthenticator->authenticate($identifier, $password);

        if ($ldapProfile === null) {
            $this->logFailedLogin($clientIp, $identifier, 'ldap_failed', true, $method);

            return null;
        }

        $username = trim((string) ($ldapProfile['external_id'] ?? $identifier));
        $person = $this->personnelModel->findByExternalId($username);

        if ($person === null && trim((string) ($ldapProfile['email'] ?? '')) !== '') {
            $person = $this->personnelModel->findByEmail((string) $ldapProfile['email']);
        }

        if ($person === null) {
            return $this->personnelModel->provisionFromAuth([
                ...$ldapProfile,
                'auth_provider' => Personnel::PROVIDER_LDAP,
                'provider_subject' => $username,
            ]);
        }

        return $this->personnelModel->refreshProfileFromAuth((int) $person['id'], [
            ...$ldapProfile,
            'auth_provider' => Personnel::PROVIDER_LDAP,
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function auditLogin(string $clientIp, array $user, string $method, string $identifier = ''): void
    {
        $userId = (int) ($user['id'] ?? 0);
        $email = (string) ($user['email'] ?? '');

        $this->auditLogger->log(
            $userId > 0 ? $userId : null,
            AuditLog::ACTION_LOGIN,
            AuditLog::ENTITY_USER,
            $userId > 0 ? $userId : null,
            null,
            [
                'email' => $email,
                'method' => $method,
            ],
            $clientIp
        );

        $this->appLogger->logAuthSuccess(
            $identifier !== '' ? $identifier : $email,
            $clientIp,
            $method,
            $userId > 0 ? $userId : null,
            $email
        );
    }

    private function logFailedLogin(
        string $clientIp,
        string $identifier,
        string $reason,
        bool $trackRateLimit,
        string $method = ''
    ): void {
        if ($trackRateLimit) {
            $this->loginAttemptService->recordFailure($clientIp);
        }

        $this->appLogger->logAuthFailure($identifier, $clientIp, $reason, $method);
    }

    /**
     * @param array<string, mixed> $person
     */
    private function sessionLoginFromPersonnel(array $person): void
    {
        $this->sessionAuthService->login(
            (int) $person['id'],
            (string) ($person['role'] ?? User::ROLE_USER)
        );
    }

    private function resolveRedirectTarget(ServerRequestInterface $request): string
    {
        $queryParams = $request->getQueryParams();
        $redirect = $this->sanitizeRedirect((string) ($queryParams['redirect'] ?? ''));

        return $redirect !== '' ? $redirect : '/';
    }

    private function sanitizeRedirect(string $redirect): string
    {
        $redirect = trim($redirect);

        if ($redirect === '' || !str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            return '/';
        }

        if (str_starts_with($redirect, '/login')) {
            return '/';
        }

        return $redirect;
    }

    private function resolveErrorMessage(string $errorKey): string
    {
        return match ($errorKey) {
            'login_invalid_password' => __('login_invalid_password'),
            'login_missing_credentials' => __('login_missing_credentials'),
            'login_ldap_failed' => __('login_ldap_failed'),
            'login_provider_disabled' => __('login_provider_disabled'),
            default => '',
        };
    }

    private function redirectWithError(ResponseInterface $response, string $errorKey, string $redirectTarget = '/'): ResponseInterface
    {
        $redirectTarget = $this->sanitizeRedirect($redirectTarget);
        $query = http_build_query([
            'error' => $errorKey,
            'redirect' => $redirectTarget !== '/' ? $redirectTarget : null,
        ]);
        $location = '/login?' . $query;

        return $response->withHeader('Location', $location)->withStatus(302);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function jsonAuthSuccess(ResponseInterface $response, array $user): ResponseInterface
    {
        $payload = json_encode([
            'status' => 'success',
            'user' => [
                'id' => (int) ($user['id'] ?? 0),
                'name' => (string) ($user['name'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'role' => (string) ($user['role'] ?? User::ROLE_USER),
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $response->getBody()->write($payload);

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function jsonAuthError(ResponseInterface $response, string $errorKey, int $statusCode): ResponseInterface
    {
        $payload = json_encode([
            'status' => 'error',
            'message' => $this->resolveErrorMessage($errorKey),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($statusCode);
    }
}
