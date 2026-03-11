<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Tests\Runtime;

use Mgrunder\Fuzz\Fuzz\AgeUnit;
use Mgrunder\Fuzz\Fuzz\ObservedAge;
use Mgrunder\Fuzz\Runtime\StatusFormatter;
use Mgrunder\Fuzz\Runtime\WorkerStatistics;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StatusFormatterTest extends TestCase
{
    #[Test]
    public function it_formats_recorded_age_information(): void
    {
        $statistics = new WorkerStatistics();
        $statistics->observeAge(new ObservedAge('string:3', 1_500_000));

        self::assertSame('string:3 age=1.500ms', StatusFormatter::formatAge($statistics, AgeUnit::Milliseconds));
    }

    #[Test]
    public function it_formats_staleness_ages_in_milliseconds(): void
    {
        self::assertSame('12.45ms', StatusFormatter::formatStalenessAgeValue(12_450_000));
    }
}
