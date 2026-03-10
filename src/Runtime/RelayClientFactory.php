<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use RuntimeException;

final class RelayClientFactory implements ClientFactory
{
    public function connect(string $host, int $port): RedisClient
    {
        $relayClass = 'Relay\\Relay';

        if (!class_exists($relayClass)) {
            throw new RuntimeException('Relay extension is not available.');
        }

        $relay = new $relayClass();

        $relay->connect($host, $port);

        return new RelayClient($relay);
    }
}
