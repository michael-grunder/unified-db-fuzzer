<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use InvalidArgumentException;

final readonly class ClientKillOptions
{
    public function __construct(
        public string $host,
        public int $port,
        public ?float $timeout,
        public ?float $readTimeout,
        public int $minSleepMicros,
        public int $maxSleepMicros,
        public int $minKillsPerIteration,
        public int $maxKillsPerIteration,
        public ?int $seed,
    ) {
        if ($this->minSleepMicros < 0) {
            throw new InvalidArgumentException('Minimum sleep duration must be >= 0 microseconds.');
        }

        if ($this->maxSleepMicros < $this->minSleepMicros) {
            throw new InvalidArgumentException('Maximum sleep duration must be >= minimum sleep duration.');
        }

        if ($this->minKillsPerIteration < 0) {
            throw new InvalidArgumentException('Minimum kills per iteration must be >= 0.');
        }

        if ($this->maxKillsPerIteration < $this->minKillsPerIteration) {
            throw new InvalidArgumentException('Maximum kills per iteration must be >= minimum kills per iteration.');
        }
    }
}
