<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Tests\Runtime;

use Mgrunder\Fuzz\Runtime\ClientKillConsoleLogger;
use Mgrunder\Fuzz\Runtime\ClientKillProgress;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class ClientKillConsoleLoggerTest extends TestCase
{
    #[Test]
    public function it_formats_progress_output(): void
    {
        $output = new BufferedOutput();
        $logger = new ClientKillConsoleLogger($output);

        $logger->logProgress(new ClientKillProgress(
            iteration: 42,
            totalKilledClients: 9,
            lastIterationKilledClients: 3,
        ));

        self::assertStringContainsString('[42] Killed 9 clients so far (3 last iteration).', $output->fetch());
    }
}
