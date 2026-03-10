<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Mgrunder\Fuzz\Fuzz\RedisOperation;
use RuntimeException;

final class PhpRedisClient implements RedisClient
{
    public function __construct(
        private readonly object $redis,
    ) {
    }

    public function execute(RedisOperation $operation): mixed
    {
        $method = $operation->name;

        return $this->redis->{$method}(...$operation->arguments);
    }

    public function flushDatabase(): void
    {
        if (!method_exists($this->redis, 'flushdb')) {
            throw new RuntimeException('PhpRedis client does not support flushdb().');
        }

        $this->redis->flushdb();
    }
}
