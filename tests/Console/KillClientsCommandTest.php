<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Tests\Console;

use Mgrunder\Fuzz\Console\Command\KillClientsCommand;
use Mgrunder\Fuzz\Runtime\ClientKillApplication;
use Mgrunder\Fuzz\Runtime\ClientKillLogger;
use Mgrunder\Fuzz\Runtime\ClientKillOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class KillClientsCommandTest extends TestCase
{
    #[Test]
    public function it_builds_client_kill_options_from_console_input(): void
    {
        $application = new class() implements ClientKillApplication {
            public ?ClientKillOptions $options = null;

            public function run(ClientKillOptions $options, ClientKillLogger $logger): int
            {
                $this->options = $options;

                return 0;
            }
        };

        $tester = new CommandTester(new KillClientsCommand($application));
        $exitCode = $tester->execute([
            '--host' => 'redis.internal',
            '--port' => '6380',
            '--timeout' => '1.25',
            '--read-timeout' => '3.5',
            '--sleep' => '0.01-0.9',
            '--kills' => '2-5',
            '--seed' => '99',
        ]);

        self::assertSame(0, $exitCode);
        self::assertNotNull($application->options);
        self::assertSame('redis.internal', $application->options->host);
        self::assertSame(6380, $application->options->port);
        self::assertSame(1.25, $application->options->timeout);
        self::assertSame(3.5, $application->options->readTimeout);
        self::assertSame(10_000, $application->options->minSleepMicros);
        self::assertSame(900_000, $application->options->maxSleepMicros);
        self::assertSame(2, $application->options->minKillsPerIteration);
        self::assertSame(5, $application->options->maxKillsPerIteration);
        self::assertSame(99, $application->options->seed);
    }

    #[Test]
    public function it_rejects_an_invalid_sleep_range(): void
    {
        $tester = new CommandTester(new KillClientsCommand(
            new class() implements ClientKillApplication {
                public function run(ClientKillOptions $options, ClientKillLogger $logger): int
                {
                    return 0;
                }
            },
        ));

        $exitCode = $tester->execute([
            '--sleep' => '0.9-0.01',
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Invalid --sleep value "0.9-0.01": max must be >= min.', $tester->getDisplay(true));
    }
}
