<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use InvalidArgumentException;

final readonly class StalenessThresholds
{
    /**
     * @param list<int> $delayBucketsUs
     */
    public function __construct(
        public int $persistentChecks = 3,
        public int $severeSteps = 3,
        public int $hardFailureSteps = 8,
        public int $stuckRepeats = 5,
        public int $topN = 10,
        public array $delayBucketsUs = [0, 100, 500, 1_000, 5_000, 20_000],
    ) {
        if ($this->persistentChecks <= 0) {
            throw new InvalidArgumentException('--stale-persistent-checks must be > 0.');
        }

        if ($this->severeSteps <= 0) {
            throw new InvalidArgumentException('--stale-severe-steps must be > 0.');
        }

        if ($this->hardFailureSteps <= 0) {
            throw new InvalidArgumentException('--stale-hard-steps must be > 0.');
        }

        if ($this->stuckRepeats <= 0) {
            throw new InvalidArgumentException('--stale-stuck-repeats must be > 0.');
        }

        if ($this->topN <= 0) {
            throw new InvalidArgumentException('--stale-top must be > 0.');
        }

        if ($this->delayBucketsUs === []) {
            throw new InvalidArgumentException('--stale-delays must not be empty.');
        }

        foreach ($this->delayBucketsUs as $delayUs) {
            if ($delayUs < 0) {
                throw new InvalidArgumentException('--stale-delays values must be >= 0.');
            }
        }
    }
}
