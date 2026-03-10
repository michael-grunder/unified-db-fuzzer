<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz;

enum AgeUnit: string
{
    case Microseconds = 'usec';
    case Milliseconds = 'ms';
    case Seconds = 'seconds';

    public function format(string $key, int $ageNs): string
    {
        return match ($this) {
            self::Microseconds => sprintf('%s age=%dusec', $key, (int) floor($ageNs / 1_000)),
            self::Milliseconds => sprintf('%s age=%.3fms', $key, $ageNs / 1_000_000),
            self::Seconds => sprintf('%s age=%.6fseconds', $key, $ageNs / 1_000_000_000),
        };
    }
}
