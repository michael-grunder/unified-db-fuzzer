<?php

class Context {
    private array $cmds = [
        'del' => null,
        'get' => 'string',
        'hgetall' => 'hash',
        'hmget' => 'hash',
        'hmset' => 'hash',
        'mget' => 'string',
        'mset' => 'string',
        'set' => 'string',
        'zadd' => 'zset',
    ];

    public function __construct(
        public readonly int $keys,
        public readonly int $mems,
    ) {}

    public function rngKey(?string $type): string {
        static $types = array_flip(['string', 'liist', 'hash', 'zset']);

        $type ??= array_rand($types);

        return sprintf("%s:%d", $type, rand(0, $this->keys));
    }

    function rngKeys(string $type): array {
        $res = [];

        $n = rand(1, $this->keys);
        for ($i = 0; $i < $n; $i++)
            $res[] = sprintf("%s:%d", $type, rand(0, $this->keys));

        return $res;
    }

    function rngKeyVals(string $type): array {
        $res = [];

        $n = rand(1, $this->keys);
        for ($i = 0; $i < $n; $i++) {
            $key = sprintf("%s:%d", $type, rand(0, $this->keys));
            $val = uniqid();
            $res[$key] = $val;
        }

        return $res;
    }

    public function rngField(): string {
        return sprintf("field:%d", rand(0, $this->mems));
    }

    public function rngFields(): array {
        $res = [];

        $n = rand(1, $this->mems);
        for ($i = 0; $i < $n; $i++)
            $res[] = $this->rngField();

        return $res;
    }

    public function rngHash(): array {
        $res = [];

        $n = rand(1, $this->mems);
        for ($i = 0; $i < $n; $i++) {
            $field = sprintf("field:%d", rand(0, $this->mems));
            $value = uniqid();
            $res[$field] = $value;
        }

        return $res;
    }

    public function rngZadd(Relay\Relay $relay) {
       $args = [$this->rngKey('zset')];

        $n = rand(1, $this->mems);
        for ($i = 0; $i < $n; $i++) {
            $args[] = mt_rand() / mt_getrandmax();
            $args[] = $this->rngField();
        }

        $key = $this->rngKey('zset');
        $score = rand(0, 100);
        $member = uniqid();

        return $relay->zadd($key, $score, $member);
    }

    public function rngCmd(Relay\Relay $relay) {
        $cmd  = array_rand($this->cmds);
        $type = $this->cmds[$cmd];

        switch ($cmd) {
            case 'del':
            case 'hgetall':
            case 'get':
                return $relay->{$cmd}($this->rngKey($type));
            case 'hmget':
                return $relay->{$cmd}($this->rngKey($type),
                                      $this->rngFields());
            case 'hmset':
                return $relay->{$cmd}($this->rngKey($type),
                                       $this->rngHash());
            case 'mget':
                return $relay->{$cmd}($this->rngKeys($type));
            case 'mset':
                return $relay->{$cmd}($this->rngKeyVals($type));
            case 'set':
                return $relay->{$cmd}($this->rngKey($type), uniqid());
            case 'zadd':
                return $this->rngZadd($relay);
        }
    }
}

function rngKey(?string $type, int $keys): string {
    static $types = array_flip(['string', 'liist', 'hash', 'zset']);

    $type ??= array_rand($types);

    return sprintf("%s:%d", $type, rand(0, $keys));
}

function work(Relay\Relay $redis, int $ops, int $keys, int $mems) {
    $context = new Context($keys, $mems);

    for ($i = 0; $i < $ops; $i++) {
        $context->rngCmd($redis);
    }
}

$opt = getopt('', ['keys:', 'mems:', 'workers:', 'ops:']);
$keys = (int)($opt['keys'] ?? 100);
$mems = (int)($opt['mems'] ?? 10);
$workers = (int)($opt['workers'] ?? 4);
$ops = (int)($opt['ops'] ?? 1000);

$pids = [];

if ($workers > 0) {
    for ($i = 0; $i < $workers; $i++) {
        $pid = pcntl_fork();
        if ($pid < 0) {
            die("fork failed");
            exit(1);
        } else if ($pid) {
            $pids[] = $pid;
        } else {
            $redis = new Relay\Relay();
            $redis->connect('localhost', 6379);
            work($redis, $ops, $keys, $mems);
            exit(0);
        }
    }

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }
} else {
    $redis = new Relay\Relay();
    $redis->connect('localhost', 6379);
    work($redis, $ops, $keys, $mems);
}
