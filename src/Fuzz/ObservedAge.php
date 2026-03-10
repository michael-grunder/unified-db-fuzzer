<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz;

final readonly class ObservedAge
{
    public function __construct(
        public string $key,
        public int $ageNs,
    ) {
    }
}
