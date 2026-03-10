<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Tests\Console;

use Mgrunder\Fuzz\Console\Command\WorkCommand;
use Mgrunder\Fuzz\Console\Input\NegativeNumberOptionNormalizer;
use Mgrunder\Fuzz\Runtime\WorkApplication;
use Mgrunder\Fuzz\Runtime\WorkOptions;
use Mgrunder\Fuzz\Runtime\WorkerLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NegativeNumberOptionNormalizerTest extends TestCase
{
    #[Test]
    public function it_normalizes_negative_required_option_values(): void
    {
        $command = new WorkCommand(new class() implements WorkApplication {
            public function run(WorkOptions $options, WorkerLogger $logger): int
            {
                return 0;
            }
        });

        $normalized = (new NegativeNumberOptionNormalizer())->normalize(
            ['bin/fuzz', '--ops', '-1', '--seed', '-99', '--workers', '0'],
            $command->getDefinition(),
        );

        self::assertSame(
            ['bin/fuzz', '--ops=-1', '--seed=-99', '--workers', '0'],
            $normalized,
        );
    }

    #[Test]
    public function it_leaves_unknown_options_and_double_dash_segments_unchanged(): void
    {
        $command = new WorkCommand(new class() implements WorkApplication {
            public function run(WorkOptions $options, WorkerLogger $logger): int
            {
                return 0;
            }
        });

        $normalized = (new NegativeNumberOptionNormalizer())->normalize(
            ['bin/fuzz', '--unknown', '-1', '--', '--ops', '-1'],
            $command->getDefinition(),
        );

        self::assertSame(
            ['bin/fuzz', '--unknown', '-1', '--', '--ops', '-1'],
            $normalized,
        );
    }
}
