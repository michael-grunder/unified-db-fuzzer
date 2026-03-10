<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Fuzz;

final readonly class RedisOperation
{
    /**
     * @param list<mixed> $arguments
     * @param list<string>|null $readKeys
     */
    public function __construct(
        public string $name,
        public array $arguments,
        public ?string $primaryKey = null,
        public ?array $readKeys = null,
    ) {
    }
}
