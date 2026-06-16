<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Personnel;
use App\Models\Setting;
use App\Models\User;
use App\Services\Auth\LdapAuthenticator;
use App\Services\Auth\OAuthService;
use App\Services\Auth\SessionAuthService;
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
        private readonly Setting $settingModel,
        private readonly User $userModel,
        private readonly Personnel $personnelModel,
        private readonly SessionAuthService $sessionAuthService,
        private readonly LoginAttemptService $loginAttemptService,
        private readonly ClientIpResolver $clientIpResolver,
        private readonly LdapAuthenticator $ldapAuthenticator,
        private readonly OAuthService $oauthService,
        private readonly ViewRenderer $viewRenderer
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

        $loginConfig = $this->settingModel->getLoginConfigForLoginPage();
        $queryParams = $request->getQueryParams();
        $errorKey = trim((string) ($queryParams['error'] ?? ''));

        $html = $this->viewRenderer->render('login', [
            'pageTitle' => __('login_page_title'),
            'appName' => __('app_name'),
            'locale' => 'tr',
            'loginConfig' => $loginConfig,
            'errorMessage' => $this->resolveErrorMessage($errorKey),
            'redirectTarget' => $this->resolveRedirectTarget($request),
            'csrfToken' => $this->sessionAuthService->getOrCreateCsrfToken(),
        ], null);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        Translator::instance()->setLocale('tr');

        $clientIp = $this->clientIpResolver->resolveFromRequest($request);
        // Temporary: unblock IPs locked out during auth hardening rollout.
        $this->loginAttemptService->clearFailures($clientIp);
        $parsedBody = $request->getParsedBody();
        $payload = is_array($parsedBody) ? $parsedBody : [];
        $mode = strtolower(trim((string) ($payload['mode'] ?? 'local')));
        $identifier = trim((string) ($payload['identifier'] ?? $payload['email'] ?? $payload['username'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $providers = $this->settingModel->getLoginProviders();
        $redirectTarget = $this->sanitizeRedirect((string) ($payload['redirect'] ?? ''));

        if ($mode === 'local') {
            if (!$providers['local']) {
                return $this->redirectWithError($response, 'login_provider_disabled', $redirectTarget);
            }

            if ($identifier === '' || $password === '') {
                return $this->redirectWithError($response, 'login_missing_credentials', $redirectTarget);
            }

            $userRecord = $this->userModel->findByEmail($identifier);

            if ($userRecord === null) {
                $this->loginAttemptService->recordFailure($clientIp);

                return $this->redirectWithError($response, 'login_invalid_password', $redirectTarget);
            }

            $passwordHash = (string) ($userRecord['password_hash'] ?? '');

            if ($passwordHash === '' || !password_verify($password, $passwordHash)) {
                $this->loginAttemptService->recordFailure($clientIp);

                return $this->redirectWithError($response, 'login_invalid_password', $redirectTarget);
            }

            $userId = (int) $userRecord['id'];

            if (password_needs_rehash($passwordHash, PASSWORD_DEFAULT)) {
                $this->userModel->updatePasswordHash($userId, $password);
            }

            $user = $this->userModel->findById($userId);

            if ($user === null) {
                $this->loginAttemptService->recordFailure($clientIp);

                return $this->redirectWithError($response, 'login_invalid_password', $redirectTarget);
            }

            $this->loginAttemptService->clearFailures($clientIp);
            $this->sessionLoginFromUser($user);

            return $response->withHeader('Location', $redirectTarget)->withStatus(302);
        }

        if ($mode === 'ldap') {
            if (!$providers['ldap']) {
                return $this->redirectWithError($response, 'login_provider_disabled', $redirectTarget);
            }

            if ($identifier === '' || $password === '') {
                return $this->redirectWithError($response, 'login_missing_credentials', $redirectTarget);
            }

            $ldapProfile = $this->ldapAuthenticator->authenticate($identifier, $password);

            if ($ldapProfile === null) {
                $this->loginAttemptService->recordFailure($clientIp);

                return $this->redirectWithError($response, 'login_ldap_failed', $redirectTarget);
            }

            $systemUser = $this->userModel->findByEmail($ldapProfile['email']);

            if ($systemUser !== null) {
                $this->loginAttemptService->clearFailures($clientIp);
                $this->sessionLoginFromUser($systemUser);

                return $response->withHeader('Location', $redirectTarget)->withStatus(302);
            }

            $person = $this->personnelModel->provisionFromAuth([
                ...$ldapProfile,
                'auth_provider' => Personnel::PROVIDER_LDAP,
                'provider_subject' => $ldapProfile['external_id'],
            ]);

            $this->loginAttemptService->clearFailures($clientIp);
            $this->sessionLoginFromPersonnel($person);

            return $response->withHeader('Location', $redirectTarget)->withStatus(302);
        }

        return $this->redirectWithError($response, 'login_provider_disabled', $redirectTarget);
    }

    public function apiLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        Translator::instance()->setLocale('tr');

        $clientIp = $this->clientIpResolver->resolveFromRequest($request);
        // Temporary: unblock IPs locked out during auth hardening rollout.
        $this->loginAttemptService->clearFailures($clientIp);
        $parsedBody = $request->getParsedBody();
        $payload = is_array($parsedBody) ? $parsedBody : [];
        $mode = strtolower(trim((string) ($payload['mode'] ?? 'local')));
        $identifier = trim((string) ($payload['identifier'] ?? $payload['email'] ?? $payload['username'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $providers = $this->settingModel->getLoginProviders();

        if ($mode === 'local') {
            if (!$providers['local']) {
                return $this->jsonAuthError($response, 'login_provider_disabled', 403);
            }

            if ($identifier === '' || $password === '') {
                return $this->jsonAuthError($response, 'login_missing_credentials', 422);
            }

            $userRecord = $this->userModel->findByEmail($identifier);

            if ($userRecord === null) {
                $this->loginAttemptService->recordFailure($clientIp);

                return $this->jsonAuthError($response, 'login_invalid_password', 401);
            }

            $passwordHash = (string) ($userRecord['password_hash'] ?? '');

            if ($passwordHash === '' || !password_verify($password, $passwordHash)) {
                $this->loginAttemptService->recordFailure($clientIp);

                return $this->jsonAuthError($response, 'login_invalid_password', 401);
            }

            $userId = (int) $userRecord['id'];

            if (password_needs_rehash($passwordHash, PASSWORD_DEFAULT)) {
                $this->userModel->updatePasswordHash($userId, $password);
            }

            $user = $this->userModel->findById($userId);

            if ($user === null) {
                $this->loginAttemptService->recordFailure($clientIp);

                return $this->jsonAuthError($response, 'login_invalid_password', 401);
            }

            $this->loginAttemptService->clearFailures($clientIp);
            $this->sessionLoginFromUser($user);

            return $this->jsonAuthSuccess($response, $user);
        }

        if ($mode === 'ldap') {
            if (!$providers['ldap']) {
                return $this->jsonAuthError($response, 'login_provider_disabled', 403);
            }

            if ($identifier === '' || $password === '') {
                return $this->jsonAuthError($response, 'login_missing_credentials', 422);
            }

            $ldapProfile = $this->ldapAuthenticator->authenticate($identifier, $password);

            if ($ldapProfile === null) {
                $this->loginAttemptService->recordFailure($clientIp);

                return $this->jsonAuthError($response, 'login_ldap_failed', 401);
            }

            $systemUser = $this->userModel->findByEmail($ldapProfile['email']);

            if ($systemUser !== null) {
                $this->loginAttemptService->clearFailures($clientIp);
                $this->sessionLoginFromUser($systemUser);

                return $this->jsonAuthSuccess($response, $systemUser);
            }

            $person = $this->personnelModel->provisionFromAuth([
                ...$ldapProfile,
                'auth_provider' => Personnel::PROVIDER_LDAP,
                'provider_subject' => $ldapProfile['external_id'],
            ]);

            $this->loginAttemptService->clearFailures($clientIp);
            $this->sessionLoginFromPersonnel($person);

            return $this->jsonAuthSuccess($response, [
                'id' => $person['id'],
                'name' => $person['name'],
                'email' => $person['email'],
                'role' => User::ROLE_END_USER,
            ]);
        }

        return $this->jsonAuthError($response, 'login_provider_disabled', 403);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->sessionAuthService->logout();

        return $response
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }

    public function handleOAuthCallback(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        Translator::instance()->setLocale('tr');

        $provider = strtolower(trim((string) ($args['provider'] ?? '')));
        $queryParams = $request->getQueryParams();
        $code = trim((string) ($queryParams['code'] ?? ''));
        $state = trim((string) ($queryParams['state'] ?? ''));
        $providers = $this->settingModel->getLoginProviders();

        if ($provider === 'google' && !$providers['google']) {
            return $this->redirectWithError($response, 'login_provider_disabled');
        }

        if (in_array($provider, ['microsoft', 'azure'], true) && !$providers['microsoft']) {
            return $this->redirectWithError($response, 'login_provider_disabled');
        }

        if ($code === '') {
            return $this->redirectWithError($response, 'login_oauth_denied');
        }

        $normalizedProvider = $provider === 'azure' ? 'microsoft' : $provider;
        $profile = $this->oauthService->handleCallback($normalizedProvider, $code, $state);

        if ($profile === null) {
            return $this->redirectWithError($response, 'login_corporate_account_not_found');
        }

        $systemUser = $this->userModel->findByEmail($profile['email'] ?? '');

        if ($systemUser !== null) {
            $this->sessionLoginFromUser($systemUser);

            return $response
                ->withHeader('Location', '/')
                ->withStatus(302);
        }

        $person = $this->personnelModel->provisionFromAuth($profile);
        $this->sessionLoginFromPersonnel($person);

        return $response
            ->withHeader('Location', '/')
            ->withStatus(302);
    }

    public function startOAuth(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $provider = strtolower(trim((string) ($args['provider'] ?? '')));
        $authorizationUrl = $this->oauthService->buildAuthorizationUrl($provider);

        if ($authorizationUrl === null) {
            return $this->redirectWithError($response, 'login_oauth_not_configured');
        }

        return $response
            ->withHeader('Location', $authorizationUrl)
            ->withStatus(302);
    }

    private function sessionLoginFromPersonnel(array $person): void
    {
        $this->sessionAuthService->login(
            (int) $person['id'],
            User::ROLE_END_USER
        );
    }

    /**
     * @param array<string, mixed> $user
     */
    private function sessionLoginFromUser(array $user): void
    {
        $this->sessionAuthService->login(
            (int) $user['id'],
            (string) ($user['role'] ?? User::ROLE_END_USER)
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
            'login_oauth_denied' => __('login_oauth_denied'),
            'login_oauth_not_configured' => __('login_oauth_not_configured'),
            'login_corporate_account_not_found' => __('login_corporate_account_not_found'),
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
                'role' => (string) ($user['role'] ?? User::ROLE_END_USER),
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
