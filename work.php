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
            $res[$key] = uniqid('', true);
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
            $res[$field] = uniqid('', true);
        }

        return $res;
    }

    public function rngZadd(Relay\Relay $relay): mixed {
        $key = $this->rngKey('zset');
        $score = mt_rand() / mt_getrandmax();
        $member = uniqid('', true);

        return $relay->zadd($key, $score, $member);
    }

    public function rngCmd(Relay\Relay $relay): mixed {
        $cmd = array_rand($this->cmds);
        $type = $this->cmds[$cmd];

        switch ($cmd) {
            case 'del':
            case 'get':
            case 'hgetall':
                return $relay->{$cmd}($this->rngKey($type));

            case 'hmget':
                return $relay->{$cmd}(
                    $this->rngKey($type),
                    $this->rngFields(),
                );

            case 'hmset':
                return $relay->{$cmd}(
                    $this->rngKey($type),
                    $this->rngHash(),
                );

            case 'mget':
                return $relay->{$cmd}($this->rngKeys($type));

            case 'mset':
                return $relay->{$cmd}($this->rngKeyVals($type));

            case 'set':
                return $relay->{$cmd}(
                    $this->rngKey($type),
                    uniqid('', true),
                );

            case 'zadd':
                return $this->rngZadd($relay);
        }

        throw new RuntimeException("Unhandled command: $cmd");
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

    logmsg(
        sprintf(
            'worker started: ops=%d keys=%d mems=%d report_interval=%.1fs',
            $ops,
            $keys,
            $mems,
            $reportInterval,
        )
    );

    for ($i = 0; $i < $ops; $i++) {
        $context->rngCmd($relay);
        $done++;

        $now = microtime(true);
        if (($now - $lastReport) < $reportInterval) {
            continue;
        }

        $elapsed = $now - $start;
        $rate = $elapsed > 0 ? ($done / $elapsed) : 0.0;

        logmsg(
            sprintf(
                'progress: %d/%d ops (%.1f%%), %.0f ops/sec',
                $done,
                $ops,
                ($done / $ops) * 100.0,
                $rate,
            )
        );

        $lastReport = $now;
    }

    $elapsed = microtime(true) - $start;
    $rate = $elapsed > 0 ? ($done / $elapsed) : 0.0;

    logmsg(
        sprintf(
            'worker finished: %d ops in %.3fs (%.0f ops/sec)',
            $done,
            $elapsed,
            $rate,
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
            'running in non-forking mode: ops=%d keys=%d mems=%d',
            $ops,
            $keys,
            $mems,
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

$stats = Relay\Relay::stats();
[$used, $total] = [$stats['memory']['used'], $stats['memory']['total']];
[$hits, $misses, $oom] = [
    $stats['stats']['hits'], $stats['stats']['misses'],
    $stats['stats']['oom'],
];

logmsg(sprintf("relay stats: memory=%s/%s hits=%d misses=%d oom=%d\n",
    number_format($used), number_format($total), $hits, $misses, $oom));

//❯ php -r 'var_dump(\Relay\Relay::stats());'
//array(5) {
//  ["usage"]=>
//  array(5) {
//    ["total_requests"]=>
//    int(1)
//    ["active_requests"]=>
//    int(1)
//    ["max_active_requests"]=>
//    int(1)
//    ["free_epoch_records"]=>
//    int(128)
//    ["free_leases"]=>
//    int(32)
//  }
//  ["stats"]=>
//  array(13) {
//    ["requests"]=>
//    int(0)
//    ["misses"]=>
//    int(0)
//    ["hits"]=>
//    int(0)
//    ["errors"]=>
//    int(0)
//    ["oom"]=>
//    int(0)
//    ["filtered"]=>
//    int(0)
//    ["ops_per_sec"]=>
//    int(0)
//    ["bytes_sent"]=>
//    int(0)
//    ["bytes_received"]=>
//    int(0)
//    ["command_usec"]=>
//    int(0)
//    ["rinit_usec"]=>
//    int(23)
//    ["rshutdown_usec"]=>
//    int(0)
//    ["sigio_usec"]=>
//    int(0)
//  }
//  ["memory"]=>
//  array(4) {
//    ["total"]=>
//    int(16777216)
//    ["limit"]=>
//    int(16777216)
//    ["active"]=>
//    int(55008)
//    ["used"]=>
//    int(55008)
//  }
//  ["endpoints"]=>
//  array(0) {
//  }
//  ["hashes"]=>
//  array(2) {
//    ["pid"]=>
//    int(2388327)
//    ["runid"]=>
//    string(36) "019cd3f0-5832-7832-8215-cc669a9f5d25"
//  }
//}

