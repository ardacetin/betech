<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\IpAddress;

class PingHeartbeatService
{
    private const MAX_CONCURRENCY = 20;

    public function __construct(
        private readonly IpAddress $ipAddressModel,
    ) {
    }

    /**
     * @return array{checked: int, online: int, offline: int}
     */
    public function run(): array
    {
        $addresses = $this->ipAddressModel->findPingableAddresses();
        $checked = 0;
        $online = 0;
        $offline = 0;

        foreach (array_chunk($addresses, self::MAX_CONCURRENCY) as $batch) {
            $results = $this->pingBatch($batch);

            foreach ($results as $result) {
                ++$checked;
                $isOnline = (bool) ($result['online'] ?? false);
                $this->ipAddressModel->updatePingStatus((int) $result['id'], $isOnline);

                if ($isOnline) {
                    ++$online;
                } else {
                    ++$offline;
                }
            }
        }

        return [
            'checked' => $checked,
            'online' => $online,
            'offline' => $offline,
        ];
    }

    /**
     * @param list<array<string, mixed>> $batch
     *
     * @return list<array{id: int, online: bool}>
     */
    private function pingBatch(array $batch): array
    {
        $processes = [];
        $pipes = [];
        $results = [];

        foreach ($batch as $address) {
            $id = (int) ($address['id'] ?? 0);
            $ip = trim((string) ($address['ip_address'] ?? ''));

            if ($id <= 0 || $ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
                $results[] = ['id' => $id, 'online' => false];

                continue;
            }

            $command = $this->buildPingCommand($ip);
            $descriptorSpec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptorSpec, $pipeSet);

            if (!is_resource($process)) {
                $results[] = ['id' => $id, 'online' => false];

                continue;
            }

            fclose($pipeSet[0]);
            stream_set_blocking($pipeSet[1], false);
            stream_set_blocking($pipeSet[2], false);

            $processes[] = [
                'id' => $id,
                'process' => $process,
                'stdout' => $pipeSet[1],
                'stderr' => $pipeSet[2],
            ];
        }

        $pending = $processes;

        while ($pending !== []) {
            foreach ($pending as $index => $item) {
                $status = proc_get_status($item['process']);

                if (!$status['running']) {
                    $exitCode = (int) ($status['exitcode'] ?? 1);
                    $results[] = [
                        'id' => (int) $item['id'],
                        'online' => $exitCode === 0,
                    ];

                    fclose($item['stdout']);
                    fclose($item['stderr']);
                    proc_close($item['process']);
                    unset($pending[$index]);
                }
            }

            if ($pending !== []) {
                usleep(50_000);
            }
        }

        return $results;
    }

    private function buildPingCommand(string $ip): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return sprintf('ping -n 1 -w 1000 %s', escapeshellarg($ip));
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            return sprintf('ping -c 1 -W 1000 %s', escapeshellarg($ip));
        }

        return sprintf('ping -c 1 -W 1 %s', escapeshellarg($ip));
    }
}
