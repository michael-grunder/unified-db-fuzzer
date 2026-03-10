<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Mgrunder\Fuzz\Fuzz\RedisOperation;
use RuntimeException;

final class RelayClient implements RedisClient
{
    public function __construct(
        private readonly object $relay,
    ) {
    }

    public function execute(RedisOperation $operation): mixed
    {
        $method = $operation->name;

        return $this->relay->{$method}(...$operation->arguments);
    }

    public function flushDatabase(): void
    {
        if (!method_exists($this->relay, 'flushdb')) {
            throw new RuntimeException('Relay client does not support flushdb().');
        }

        $this->relay->flushdb();
    }
}
