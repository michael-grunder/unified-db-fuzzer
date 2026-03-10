<?php

declare(strict_types=1);

namespace Mgrunder\Fuzz\Runtime;

use Throwable;

final class RelayStatsProvider implements StatsProvider
{
    public function summary(): string
    {
        $relayClass = 'Relay\\Relay';

        if (!class_exists($relayClass)) {
            return 'cache=unknown';
        }

        try {
            /** @var array<string, mixed> $stats */
            $stats = $relayClass::stats();
        } catch (Throwable) {
            return 'cache=unknown';
        }

        $memory = is_array($stats['memory'] ?? null) ? $stats['memory'] : [];
        $cstats = is_array($stats['stats'] ?? null) ? $stats['stats'] : [];
        $usage = is_array($stats['usage'] ?? null) ? $stats['usage'] : [];

        return sprintf(
            'cache=%s/%s hits=%d misses=%d oom=%d errs=%d req=%d act=%d max=%d',
            HumanBytes::format((int) ($memory['used'] ?? 0)),
            HumanBytes::format((int) ($memory['total'] ?? 0)),
            (int) ($cstats['hits'] ?? 0),
            (int) ($cstats['misses'] ?? 0),
            (int) ($cstats['oom'] ?? 0),
            (int) ($cstats['errors'] ?? 0),
            (int) ($cstats['requests'] ?? 0),
            (int) ($usage['active_requests'] ?? 0),
            (int) ($usage['max_active_requests'] ?? 0),
        );
    }
}
