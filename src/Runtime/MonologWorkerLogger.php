<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Monolog\Logger;

final class MonologWorkerLogger implements WorkerLogger
{
    public function __construct(
        private readonly Logger $logger,
    ) {
    }

    public function log(string $message): void
    {
        $this->logger->info($message);
    }

    public function updateWorkerStatus(WorkerStatusSnapshot $snapshot): void
    {
    }
}
