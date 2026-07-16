<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\ServerRequestInterface;

class ClientIpResolver
{
    /**
     * @param list<string> $trustedProxies IP addresses, CIDR ranges, or "*" to trust all proxies
     */
    public function __construct(
        private readonly array $trustedProxies = ['*']
    ) {
    }

    public function resolveFromRequest(ServerRequestInterface $request): string
    {
        return $this->resolve($request->getServerParams());
    }

    /**
     * @param array<string, mixed> $serverParams
     */
    public function resolve(array $serverParams): string
    {
        $remoteAddr = $this->normalizeIpAddress((string) ($serverParams['REMOTE_ADDR'] ?? ''));

        if (!$this->shouldTrustForwardedHeaders($remoteAddr)) {
            return $remoteAddr;
        }

        $cloudflareIp = $this->normalizeIpAddress((string) ($serverParams['HTTP_CF_CONNECTING_IP'] ?? ''));

        if ($cloudflareIp !== '') {
            return $cloudflareIp;
        }

        $forwardedFor = trim((string) ($serverParams['HTTP_X_FORWARDED_FOR'] ?? ''));

        if ($forwardedFor !== '') {
            $firstHop = trim(explode(',', $forwardedFor)[0]);
            $forwardedIp = $this->normalizeIpAddress($firstHop);

            if ($forwardedIp !== '') {
                return $forwardedIp;
            }
        }

        return $remoteAddr;
    }

    private function shouldTrustForwardedHeaders(string $remoteAddr): bool
    {
        if ($this->trustedProxies === []) {
            return false;
        }

        if (in_array('*', $this->trustedProxies, true)) {
            return true;
        }

        if ($remoteAddr === '') {
            return false;
        }

        foreach ($this->trustedProxies as $trustedProxy) {
            if ($this->ipMatchesTrustedProxy($remoteAddr, $trustedProxy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $cidrs
     */
    public function ipMatchesAnyCidr(string $ipAddress, array $cidrs): bool
    {
        $ipAddress = $this->normalizeIpAddress($ipAddress);

        if ($ipAddress === '' || $cidrs === []) {
            return false;
        }

        foreach ($cidrs as $cidr) {
            if ($this->ipMatchesTrustedProxy($ipAddress, (string) $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function ipMatchesTrustedProxy(string $remoteAddr, string $trustedEntry): bool
    {
        $trustedEntry = trim($trustedEntry);

        if ($trustedEntry === '*') {
            return true;
        }

        if ($trustedEntry === $remoteAddr) {
            return true;
        }

        if (!str_contains($trustedEntry, '/')) {
            return false;
        }

        if (filter_var($remoteAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            [$subnet, $maskBitsRaw] = explode('/', $trustedEntry, 2);
            $maskBits = (int) $maskBitsRaw;

            if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false || $maskBits < 0 || $maskBits > 32) {
                return false;
            }

            $ipLong = ip2long($remoteAddr);
            $subnetLong = ip2long($subnet);

            if ($ipLong === false || $subnetLong === false) {
                return false;
            }

            $mask = $maskBits === 0 ? 0 : (-1 << (32 - $maskBits));

            return ($ipLong & $mask) === ($subnetLong & $mask);
        }

        if (filter_var($remoteAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return false;
        }

        [$subnet, $maskBitsRaw] = explode('/', $trustedEntry, 2);
        $maskBits = (int) $maskBitsRaw;

        if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false || $maskBits < 0 || $maskBits > 128) {
            return false;
        }

        $ipBinary = inet_pton($remoteAddr);
        $subnetBinary = inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false) {
            return false;
        }

        $bytes = intdiv($maskBits, 8);
        $remainingBits = $maskBits % 8;

        if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($subnetBinary, 0, $bytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (~((1 << (8 - $remainingBits)) - 1)) & 0xFF;

        return (ord($ipBinary[$bytes]) & $mask) === (ord($subnetBinary[$bytes]) & $mask);
    }

    private function normalizeIpAddress(string $ipAddress): string
    {
        $ipAddress = trim($ipAddress);

        if ($ipAddress === '') {
            return '';
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return '';
        }

        return $ipAddress;
    }
}
