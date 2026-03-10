<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

final readonly class RunSummary
{
    public function __construct(
        public bool $terminatedEarly,
        public WorkerStatistics $statistics,
    ) {
    }
}
