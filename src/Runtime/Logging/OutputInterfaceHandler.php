<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Symfony\Component\Console\Output\OutputInterface;

final class OutputInterfaceHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly OutputInterface $output,
    ) {
        parent::__construct();
    }

    protected function write(LogRecord $record): void
    {
        $this->output->write((string) $record->formatted, false, OutputInterface::OUTPUT_RAW);
    }
}
