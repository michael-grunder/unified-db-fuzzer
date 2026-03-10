<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Mgrunder\Fuzz\Fuzz\AgeUnit;

final class StatusFormatter
{
    public static function formatOps(int $ops): string
    {
        return $ops < 0 ? 'forever' : (string) $ops;
    }

    public static function formatAge(WorkerStatistics $statistics, AgeUnit $ageUnit): string
    {
        if ($statistics->oldestKey === null || $statistics->oldestAgeNs === null) {
            return 'oldest=none';
        }

        return $ageUnit->format($statistics->oldestKey, $statistics->oldestAgeNs);
    }

    public static function formatAgeValue(?int $ageNs, AgeUnit $ageUnit): string
    {
        if ($ageNs === null) {
            return 'n/a';
        }

        return match ($ageUnit) {
            AgeUnit::Microseconds => sprintf('%dusec', (int) floor($ageNs / 1_000)),
            AgeUnit::Milliseconds => sprintf('%.3fms', $ageNs / 1_000_000),
            AgeUnit::Seconds => sprintf('%.6fseconds', $ageNs / 1_000_000_000),
        };
    }

    public static function formatElapsed(float $seconds): string
    {
        if ($seconds < 1) {
            return sprintf('%.1fms', $seconds * 1_000);
        }

        if ($seconds < 60) {
            return sprintf('%.1fs', $seconds);
        }

        return sprintf('%dm%02ds', (int) floor($seconds / 60), (int) floor($seconds % 60));
    }
}
