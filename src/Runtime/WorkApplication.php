<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

interface WorkApplication
{
    public function run(WorkOptions $options, WorkerLogger $logger): int;
}
