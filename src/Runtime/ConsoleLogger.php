<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Symfony\Component\Console\Output\OutputInterface;

final class ConsoleLogger implements WorkerLogger
{
    public function __construct(
        private readonly OutputInterface $output,
    ) {
    }

    public function log(string $message): void
    {
        $this->output->writeln(sprintf('[%d] %s', getmypid(), $message));
    }
}
