<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

final readonly class RedisClientConnection
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $libraryName,
    ) {
    }

    public function isRelayConnection(): bool
    {
        if ($this->libraryName === 'relay') {
            return true;
        }

        return $this->name !== null && str_starts_with($this->name, 'relay@');
    }
}
