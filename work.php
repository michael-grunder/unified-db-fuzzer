<?php

declare(strict_types=1);

final class Context {
    /** @var array<string, string|null> */
    private array $cmds = [
        'del'     => null,
        'get'     => 'string',
        'hgetall' => 'hash',
        'hmget'   => 'hash',
        'hmset'   => 'hash',
        'mget'    => 'string',
        'mset'    => 'string',
        'set'     => 'string',
        'zadd'    => 'zset',
        'zrange'  => 'zset',
    ];

    public function __construct(
        public readonly int $keys,
        public readonly int $mems,
    ) {}

    public function rngKey(?string $type): string {
        static $types = [
            'string' => true,
            'list'   => true,
            'hash'   => true,
            'zset'   => true,
        ];

        $type ??= array_rand($types);

        return sprintf('%s:%d', $type, rand(0, $this->keys));
    }

    /** @return list<string> */
    public function rngKeys(string $type): array {
        $res = [];
        $n = rand(1, $this->keys);

        for ($i = 0; $i < $n; $i++) {
            $res[] = sprintf('%s:%d', $type, rand(0, $this->keys));
        }

        return $res;
    }

    /** @return array<string, string> */
    public function rngKeyVals(string $type): array {
        $res = [];
        $n = rand(1, $this->keys);

        for ($i = 0; $i < $n; $i++) {
            $key = sprintf('%s:%d', $type, rand(0, $this->keys));
            $res[$key] = (string) hrtime(true);
        }

        return $res;
    }

    public function rngField(): string {
        return sprintf('field:%d', rand(0, $this->mems));
    }

    /** @return list<string> */
    public function rngFields(): array {
        $res = [];
        $n = rand(1, $this->mems);

        for ($i = 0; $i < $n; $i++) {
            $res[] = $this->rngField();
        }

        return $res;
    }

    /** @return array<string, string> */
    public function rngHash(): array {
        $res = [];
        $n = rand(1, $this->mems);

        for ($i = 0; $i < $n; $i++) {
            $field = sprintf('field:%d', rand(0, $this->mems));
            $res[$field] = (string) hrtime(true);
        }

        return $res;
    }

    public function rngZadd(Relay\Relay $relay): mixed {
        $key = $this->rngKey('zset');
        $args = [$key];
        $n = rand(1, $this->mems);

        for ($i = 0; $i < $n; $i++) {
            $args[] = hrtime(true);
            $args[] = sprintf('member:%d', rand(0, $this->mems));
        }

        return $relay->zadd(...$args);
    }

    /** @return array{cmd: string, result: mixed, key: ?string, keys: ?array<int, string>} */
    public function execRandom(Relay\Relay $relay): array {
        $cmd = array_rand($this->cmds);
        $type = $this->cmds[$cmd];

        switch ($cmd) {
            case 'del':
            case 'get':
            case 'hgetall':
                $key = $this->rngKey($type);
                return ['cmd' => $cmd, 'result' => $relay->{$cmd}($key), 'key' => $key, 'keys' => null];

            case 'hmget':
                $key = $this->rngKey($type);
                return ['cmd' => $cmd, 'result' => $relay->{$cmd}(
                    $key,
                    $this->rngFields(),
                ), 'key' => $key, 'keys' => null];

            case 'hmset':
                $key = $this->rngKey($type);
                return ['cmd' => $cmd, 'result' => $relay->{$cmd}(
                    $key,
                    $this->rngHash(),
                ), 'key' => $key, 'keys' => null];

            case 'mget':
                $keys = $this->rngKeys($type);
                return ['cmd' => $cmd, 'result' => $relay->{$cmd}($keys), 'key' => null, 'keys' => $keys];

            case 'mset':
                return ['cmd' => $cmd, 'result' => $relay->{$cmd}($this->rngKeyVals($type)), 'key' => null, 'keys' => null];

            case 'set':
                $key = $this->rngKey($type);
                return ['cmd' => $cmd, 'result' => $relay->{$cmd}(
                    $key,
                    (string) hrtime(true),
                ), 'key' => $key, 'keys' => null];

            case 'zadd':
                return ['cmd' => $cmd, 'result' => $this->rngZadd($relay), 'key' => null, 'keys' => null];
            case 'zrange':
                $key = $this->rngKey($type);
                return ['cmd' => $cmd, 'result' => $relay->{$cmd}(
                    $key,
                    '0',
                    '-1',
                    ['withscores' => true],
                ), 'key' => $key, 'keys' => null];
            default:
                throw new RuntimeException("Unhandled command: $cmd");
        } // switch
    }
}

function logmsg(string $message): void {
    fwrite(STDERR, sprintf("[%d] %s\n", getmypid(), $message));
}

function connectRelay(): Relay\Relay {
    $relay = new Relay\Relay();
    $relay->connect('localhost', 6379);

    return $relay;
}

function humanBytes(int $bytes): string {
    static $units = ['b', 'k', 'm', 'g', 't'];

    $value = (float) $bytes;
    $unit = 0;

    while ($value >= 1024.0 && $unit < count($units) - 1) {
        $value /= 1024.0;
        $unit++;
    }

    if ($unit === 0) {
        return sprintf('%db', (int) $value);
    }

    if ($value >= 10.0) {
        return sprintf('%.0f%s', $value, $units[$unit]);
    }

    return sprintf('%.1f%s', $value, $units[$unit]);
}

function relayStatsSummary(): string {
    $stats = Relay\Relay::stats();

    $memory = $stats['memory'] ?? [];
    $cstats = $stats['stats'] ?? [];
    $usage = $stats['usage'] ?? [];

    $used = (int) ($memory['used'] ?? 0);
    $total = (int) ($memory['total'] ?? 0);
    $hits = (int) ($cstats['hits'] ?? 0);
    $misses = (int) ($cstats['misses'] ?? 0);
    $oom = (int) ($cstats['oom'] ?? 0);
    $errors = (int) ($cstats['errors'] ?? 0);
    $requests = (int) ($cstats['requests'] ?? 0);
    $activeReq = (int) ($usage['active_requests'] ?? 0);
    $maxActiveReq = (int) ($usage['max_active_requests'] ?? 0);

    return sprintf(
        'cache=%s/%s hits=%d misses=%d oom=%d errs=%d req=%d act=%d max=%d',
        humanBytes($used),
        humanBytes($total),
        $hits,
        $misses,
        $oom,
        $errors,
        $requests,
        $activeReq,
        $maxActiveReq,
    );
}

function summarizeThrowable(Throwable $throwable): string {
    return sprintf(
        '%s: %s',
        $throwable::class,
        $throwable->getMessage(),
    );
}

function timestampAgeNs(mixed $value, int $nowNs): ?int {
    if (is_int($value)) {
        $timestamp = $value;
    } elseif (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
        $timestamp = (int) $value;
    } elseif (is_float($value) && is_finite($value) && $value >= 0) {
        $timestamp = (int) round($value);
    } else {
        return null;
    }

    $age = $nowNs - $timestamp;

    return $age >= 0 ? $age : null;
}

function extractKeyAgeNs(string $cmd, mixed $result): ?int {
    $nowNs = hrtime(true);

    return match ($cmd) {
        'get' => timestampAgeNs($result, $nowNs),
        'mget', 'hmget', 'hgetall', 'zrange' => (function () use ($result, $nowNs): ?int {
            if (!is_array($result)) {
                return null;
            }

            $oldestAge = null;

            foreach ($result as $value) {
                $age = timestampAgeNs($value, $nowNs);
                if ($age === null) {
                    continue;
                }

                $oldestAge = $oldestAge === null ? $age : max($oldestAge, $age);
            }

            return $oldestAge;
        })(),
        default => null,
    };
}

function formatAge(?string $oldestKey, ?int $oldestAgeNs): string {
    if ($oldestKey === null || $oldestAgeNs === null) {
        return 'oldest=none';
    }

    return sprintf('%s age=%ds', $oldestKey, (int) floor($oldestAgeNs / 1_000_000_000));
}

function work(
    Relay\Relay $relay,
    int $ops,
    int $keys,
    int $mems,
    float $reportInterval = 1.0,
): void {
    $context = new Context($keys, $mems);
    $start = microtime(true);
    $lastReport = $start;
    $done = 0;
    $exceptions = 0;
    $reconnectFailures = 0;
    $lastException = null;
    $oldestKey = null;
    $oldestAgeNs = null;
    $terminatedEarly = false;

    logmsg(
        sprintf(
            'worker started: ops=%d keys=%d mems=%d report_interval=%.1fs %s',
            $ops,
            $keys,
            $mems,
            $reportInterval,
            relayStatsSummary(),
        )
    );

    for ($i = 0; $i < $ops; $i++) {
        try {
            $op = $context->execRandom($relay);
            $cmd = $op['cmd'];
            $result = $op['result'];

            if ($op['keys'] !== null && is_array($result)) {
                $nowNs = hrtime(true);

                foreach ($op['keys'] as $index => $readKey) {
                    $age = timestampAgeNs($result[$index] ?? null, $nowNs);
                    if ($age === null || ($oldestAgeNs !== null && $age <= $oldestAgeNs)) {
                        continue;
                    }

                    $oldestAgeNs = $age;
                    $oldestKey = $readKey;
                }
            } elseif ($op['key'] !== null) {
                $age = extractKeyAgeNs($cmd, $result);
                if ($age !== null && ($oldestAgeNs === null || $age > $oldestAgeNs)) {
                    $oldestAgeNs = $age;
                    $oldestKey = $op['key'];
                }
            }

            $done++;
        } catch (Throwable $throwable) {
            $exceptions++;
            $lastException = summarizeThrowable($throwable);

            logmsg(
                sprintf(
                    'command exception after %d/%d ops: %s; reconnecting',
                    $done,
                    $ops,
                    $lastException,
                )
            );

            try {
                $relay = connectRelay();
                logmsg('reconnect succeeded');
            } catch (Throwable $reconnectThrowable) {
                $exceptions++;
                $reconnectFailures++;
                $lastException = sprintf(
                    'reconnect failed: %s',
                    summarizeThrowable($reconnectThrowable),
                );
                $terminatedEarly = true;

                logmsg(
                    sprintf(
                        'worker exiting early after reconnect failure: %s',
                        $lastException,
                    )
                );

                break;
            }
        }

        $now = microtime(true);
        if (($now - $lastReport) < $reportInterval) {
            continue;
        }

        $elapsed = $now - $start;
        $rate = $elapsed > 0 ? ($done / $elapsed) : 0.0;

        logmsg(
            sprintf(
                'progress: %d/%d ops (%.1f%%), %.0f ops/sec, exceptions=%d reconnect_failures=%d%s, %s',
                $done,
                $ops,
                ($done / $ops) * 100.0,
                $rate,
                $exceptions,
                $reconnectFailures,
                $lastException !== null ? sprintf(' last_exception="%s"', $lastException) : '',
                formatAge($oldestKey, $oldestAgeNs),
                relayStatsSummary(),
            )
        );

        $lastReport = $now;
    }

    $elapsed = microtime(true) - $start;
    $rate = $elapsed > 0 ? ($done / $elapsed) : 0.0;

    logmsg(
        sprintf(
            'worker %s: %d ops in %.3fs (%.0f ops/sec), exceptions=%d reconnect_failures=%d%s, %s',
            $terminatedEarly ? 'exited early' : 'finished',
            $done,
            $elapsed,
            $rate,
            $exceptions,
            $reconnectFailures,
            $lastException !== null ? sprintf(' last_exception="%s"', $lastException) : '',
            formatAge($oldestKey, $oldestAgeNs),
            relayStatsSummary(),
        )
    );
}

$opt = getopt(
    '',
    [
        'keys:',
        'mems:',
        'workers:',
        'ops:',
        'interval:',
    ]
);

$keys = (int) ($opt['keys'] ?? 100);
$mems = (int) ($opt['mems'] ?? 10);
$workers = (int) ($opt['workers'] ?? 4);
$ops = (int) ($opt['ops'] ?? 1000);
$interval = (float) ($opt['interval'] ?? 1.0);

if ($keys <= 0 || $mems <= 0 || $ops <= 0 || $workers < 0) {
    fwrite(
        STDERR,
        "usage: php fuzz.php [--keys N] [--mems N] [--workers N] " .
        "[--ops N] [--interval SECONDS]\n"
    );
    exit(1);
}

if ($workers === 0) {
    logmsg(
        sprintf(
            'running in non-forking mode: ops=%d keys=%d mems=%d interval=%.1fs',
            $ops,
            $keys,
            $mems,
            $interval,
        )
    );

    $relay = connectRelay();
    work($relay, $ops, $keys, $mems, $interval);
    exit(0);
}

logmsg(
    sprintf(
        'spawning %d workers: ops=%d keys=%d mems=%d interval=%.1fs',
        $workers,
        $ops,
        $keys,
        $mems,
        $interval,
    )
);

$pids = [];
$start = microtime(true);

for ($i = 0; $i < $workers; $i++) {
    $pid = pcntl_fork();

    if ($pid < 0) {
        logmsg('fork failed');
        exit(1);
    }

    if ($pid > 0) {
        $pids[] = $pid;
        logmsg(sprintf('spawned worker pid=%d', $pid));
        continue;
    }

    $relay = connectRelay();
    work($relay, $ops, $keys, $mems, $interval);
    exit(0);
}

$remaining = array_fill_keys($pids, true);

while ($remaining !== []) {
    $pid = pcntl_wait($status);

    if ($pid <= 0) {
        continue;
    }

    $exitCode = pcntl_wifexited($status)
        ? pcntl_wexitstatus($status)
        : -1;

    unset($remaining[$pid]);

    logmsg(
        sprintf(
            'worker pid=%d exited status=%d (%d/%d complete)',
            $pid,
            $exitCode,
            $workers - count($remaining),
            $workers,
        )
    );
}

$elapsed = microtime(true) - $start;

logmsg(sprintf('all workers completed in %.3fs', $elapsed));
logmsg('final ' . relayStatsSummary());
