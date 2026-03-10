<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use RuntimeException;

final class PhpRedisClientFactory implements ClientFactory
{
    public function connect(string $host, int $port, ?float $timeout = null, ?float $readTimeout = null): RedisClient
    {
        $redisClass = 'Redis';

        if (!class_exists($redisClass)) {
            throw new RuntimeException('PhpRedis extension is not available.');
        }

        $redis = new $redisClass();

        if ($timeout === null) {
            $redis->connect($host, $port);
        } elseif ($readTimeout === null) {
            $redis->connect($host, $port, $timeout);
        } else {
            $redis->connect($host, $port, $timeout, null, 0, 0, ['read_timeout' => $readTimeout]);
        }

        return new PhpRedisClient($redis);
    }
}
