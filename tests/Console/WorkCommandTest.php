<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Tests\Console;

use Mgrunder\Fuzz\Console\Command\WorkCommand;
use Mgrunder\Fuzz\Fuzz\AgeUnit;
use Mgrunder\Fuzz\Runtime\WorkApplication;
use Mgrunder\Fuzz\Runtime\WorkOptions;
use Mgrunder\Fuzz\Runtime\WorkerLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class WorkCommandTest extends TestCase
{
    #[Test]
    public function it_builds_work_options_from_console_input(): void
    {
        $application = new class() implements WorkApplication {
            public ?WorkOptions $options = null;

            public function run(WorkOptions $options, WorkerLogger $logger): int
            {
                $this->options = $options;

                return 0;
            }
        };

        $tester = new CommandTester(new WorkCommand($application));
        $exitCode = $tester->execute([
            '--host' => 'redis.internal',
            '--port' => '6380',
            '--timeout' => '1.25',
            '--read-timeout' => '3.5',
            '--keys' => '25',
            '--mems' => '8',
            '--workers' => '0',
            '--ops' => '50',
            '--interval' => '2.5',
            '--age-unit' => 'ms',
            '--cmd-types' => ['hash,string'],
            '--seed' => '99',
            '--flush' => true,
            '--staleness' => true,
            '--stale-persistent-checks' => '4',
            '--stale-severe-steps' => '5',
            '--stale-hard-steps' => '9',
            '--stale-stuck-repeats' => '6',
            '--stale-top' => '7',
            '--stale-delays' => '0,250,1000',
        ]);

        self::assertSame(0, $exitCode);
        self::assertNotNull($application->options);
        self::assertSame('redis.internal', $application->options->host);
        self::assertSame(6380, $application->options->port);
        self::assertSame(1.25, $application->options->timeout);
        self::assertSame(3.5, $application->options->readTimeout);
        self::assertSame(25, $application->options->keys);
        self::assertSame(8, $application->options->members);
        self::assertSame(0, $application->options->workers);
        self::assertSame(50, $application->options->ops);
        self::assertSame(2.5, $application->options->reportInterval);
        self::assertSame(AgeUnit::Milliseconds, $application->options->ageUnit);
        self::assertTrue($application->options->flush);
        self::assertSame(99, $application->options->seed);
        self::assertTrue($application->options->staleness);
        self::assertSame(4, $application->options->stalenessThresholds->persistentChecks);
        self::assertSame(5, $application->options->stalenessThresholds->severeSteps);
        self::assertSame(9, $application->options->stalenessThresholds->hardFailureSteps);
        self::assertSame(6, $application->options->stalenessThresholds->stuckRepeats);
        self::assertSame(7, $application->options->stalenessThresholds->topN);
        self::assertSame([0, 250, 1000], $application->options->stalenessThresholds->delayBucketsUs);
        self::assertSame(['hash', 'string'], array_map(
            static fn ($type): string => $type->value,
            $application->options->commandTypes,
        ));
    }
}
