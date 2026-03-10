<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Symfony\Component\Console\Output\OutputInterface;

final class ClientKillConsoleLogger implements ClientKillLogger
{
    public function __construct(
        private readonly OutputInterface $output,
    ) {
    }

    public function logProgress(ClientKillProgress $progress): void
    {
        $this->output->writeln(sprintf(
            '[%d] Killed %d clients so far (%d last iteration).',
            $progress->iteration,
            $progress->totalKilledClients,
            $progress->lastIterationKilledClients,
        ));
    }
}
