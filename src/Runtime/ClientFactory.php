<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

interface ClientFactory
{
    public function connect(string $host, int $port, ?float $timeout = null, ?float $readTimeout = null): RedisClient;
}
