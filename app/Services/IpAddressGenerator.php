<?php

declare(strict_types=1);

namespace App\Services;

class IpAddressGenerator
{
    public const MAX_AUTO_GENERATE_USABLE = 4096;

    /**
     * @return list<string>
     */
    public function generateUsableAddresses(string $networkAddress, int $cidr): array
    {
        if ($cidr < 0 || $cidr > 32) {
            throw new \InvalidArgumentException(__('ipam_invalid_cidr'));
        }

        if (!filter_var($networkAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new \InvalidArgumentException(__('ipam_invalid_network_address'));
        }

        $networkLong = ip2long($networkAddress);

        if ($networkLong === false) {
            throw new \InvalidArgumentException(__('ipam_invalid_network_address'));
        }

        $networkLong = $this->normalizeNetworkLong($networkLong, $cidr);
        $total = 2 ** (32 - $cidr);

        if ($cidr === 32) {
            return [long2ip($networkLong)];
        }

        if ($cidr === 31) {
            return [long2ip($networkLong), long2ip($networkLong + 1)];
        }

        $usable = [];

        for ($offset = 1; $offset < $total - 1; $offset++) {
            $usable[] = long2ip($networkLong + $offset);
        }

        if (count($usable) > self::MAX_AUTO_GENERATE_USABLE) {
            throw new \InvalidArgumentException(__('ipam_subnet_too_large_for_auto_generate'));
        }

        return $usable;
    }

    public function cidrNotation(string $networkAddress, int $cidr): string
    {
        return $networkAddress . '/' . $cidr;
    }

    public function normalizeNetworkLong(int $networkLong, int $cidr): int
    {
        $mask = $cidr === 0 ? 0 : (-1 << (32 - $cidr));

        return $networkLong & $mask;
    }

    public function isIpInNetwork(string $ipAddress, string $networkAddress, int $cidr): bool
    {
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $ipLong = ip2long($ipAddress);
        $networkLong = ip2long($networkAddress);

        if ($ipLong === false || $networkLong === false) {
            return false;
        }

        $networkLong = $this->normalizeNetworkLong($networkLong, $cidr);
        $mask = $cidr === 0 ? 0 : (-1 << (32 - $cidr));

        return ($ipLong & $mask) === $networkLong;
    }
}
