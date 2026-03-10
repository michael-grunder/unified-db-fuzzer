<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

final readonly class ClientKillProgress
{
    public function __construct(
        public int $iteration,
        public int $totalKilledClients,
        public int $lastIterationKilledClients,
    ) {
    }
}
