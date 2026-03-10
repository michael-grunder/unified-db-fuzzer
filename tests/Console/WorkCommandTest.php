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
            '--keys' => '25',
            '--mems' => '8',
            '--workers' => '0',
            '--ops' => '50',
            '--interval' => '2.5',
            '--age-unit' => 'ms',
            '--cmd-types' => ['hash,string'],
            '--seed' => '99',
            '--flush' => true,
        ]);

        self::assertSame(0, $exitCode);
        self::assertNotNull($application->options);
        self::assertSame('redis.internal', $application->options->host);
        self::assertSame(6380, $application->options->port);
        self::assertSame(25, $application->options->keys);
        self::assertSame(8, $application->options->members);
        self::assertSame(0, $application->options->workers);
        self::assertSame(50, $application->options->ops);
        self::assertSame(2.5, $application->options->reportInterval);
        self::assertSame(AgeUnit::Milliseconds, $application->options->ageUnit);
        self::assertTrue($application->options->flush);
        self::assertSame(99, $application->options->seed);
        self::assertSame(['hash', 'string'], array_map(
            static fn ($type): string => $type->value,
            $application->options->commandTypes,
        ));
    }
}
