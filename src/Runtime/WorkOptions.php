<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use InvalidArgumentException;
use Mgrunder\Fuzz\Fuzz\AgeUnit;
use Mgrunder\Fuzz\Fuzz\RedisDataType;

final readonly class WorkOptions
{
    /**
     * @param list<RedisDataType> $commandTypes
     */
    public function __construct(
        public string $host,
        public int $port,
        public int $keys,
        public int $members,
        public int $workers,
        public int $ops,
        public float $reportInterval,
        public AgeUnit $ageUnit,
        public array $commandTypes = [],
        public bool $flush = false,
        public ?int $seed = null,
    ) {
        if ($this->keys <= 0) {
            throw new InvalidArgumentException('--keys must be > 0.');
        }

        if ($this->members <= 0) {
            throw new InvalidArgumentException('--mems must be > 0.');
        }

        if ($this->ops === 0) {
            throw new InvalidArgumentException('--ops must be non-zero.');
        }

        if ($this->workers < 0) {
            throw new InvalidArgumentException('--workers must be >= 0.');
        }

        if ($this->port <= 0) {
            throw new InvalidArgumentException('--port must be > 0.');
        }

        if ($this->reportInterval <= 0) {
            throw new InvalidArgumentException('--interval must be > 0.');
        }
    }
}
