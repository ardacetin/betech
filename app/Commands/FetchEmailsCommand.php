<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Mail\InboundEmailTicketService;

class FetchEmailsCommand
{
    public function __construct(
        private readonly InboundEmailTicketService $inboundEmailTicketService
    ) {
    }

    public function run(): int
    {
        $result = $this->inboundEmailTicketService->run();

        echo $result['message'] . "\n";

        if ($result['skipped']) {
            return 0;
        }

        return $result['success'] ? 0 : 1;
    }
}
