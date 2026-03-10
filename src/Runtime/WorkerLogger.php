<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

interface WorkerLogger
{
    public function log(string $message): void;

    public function updateWorkerStatus(WorkerStatusSnapshot $snapshot): void;
}
