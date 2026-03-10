<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use RuntimeException;

final class RelayAdminClientFactory implements AdminClientFactory
{
    public function connect(string $host, int $port, ?float $timeout = null, ?float $readTimeout = null): AdminClient
    {
        $relayClass = 'Relay\\Relay';

        if (!class_exists($relayClass)) {
            throw new RuntimeException('Relay extension is not available.');
        }

        $relay = new $relayClass();
        $connectArguments = [
            'host' => $host,
            'port' => $port,
        ];

        if ($timeout !== null) {
            $connectArguments['timeout'] = $timeout;
        }

        if ($readTimeout !== null) {
            $connectArguments['read_timeout'] = $readTimeout;
        }

        $relay->connect(...$connectArguments);

        return new RelayAdminClient($relay);
    }
}
