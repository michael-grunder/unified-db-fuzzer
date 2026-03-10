<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Throwable;

final class RedisConnectionFailureDetector
{
    public static function isRetryable(Throwable $throwable): bool
    {
        if ($throwable::class === 'RedisException' || is_a($throwable, 'RedisException', true)) {
            return true;
        }

        $message = strtolower($throwable->getMessage());

        foreach ([
            'read error on connection',
            'connection lost',
            'connection refused',
            'connection reset',
            'socket error',
            'server went away',
            'went away',
            'closed connection',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
