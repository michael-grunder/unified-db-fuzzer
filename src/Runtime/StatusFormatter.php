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
}
