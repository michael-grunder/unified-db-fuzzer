<?php

declare(strict_types=1);

function fail(string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

/**
 * @return array{0: int, 1: int}
 */
function parseSleepSpec(string $spec): array {
    $parts = explode('-', $spec, 2);

    if (count($parts) === 1) {
        $micros = secondsToMicros($parts[0]);

        return [$micros, $micros];
    }

    $min = secondsToMicros($parts[0]);
    $max = secondsToMicros($parts[1]);

    if ($max < $min) {
        fail(sprintf('Invalid --sleep range "%s": max must be >= min', $spec));
    }

    return [$min, $max];
}

function secondsToMicros(string $seconds): int {
    $seconds = trim($seconds);

    if ($seconds === '' || !is_numeric($seconds)) {
        fail(sprintf('Invalid --sleep value "%s"', $seconds));
    }

    $value = (float) $seconds;

    if ($value < 0) {
        fail(sprintf('Invalid --sleep value "%s": must be >= 0', $seconds));
    }

    return (int) round($value * 1_000_000);
}

/**
 * @return list<array<string, string>>
 */
function clientList(Redis $redis): array {
    $clients = $redis->client('list');

    if (is_array($clients)) {
        return array_values(
            array_filter(
                $clients,
                static fn(mixed $client): bool => is_array($client) && isset($client['id']),
            )
        );
    }

    if (!is_string($clients) || $clients === '') {
        return [];
    }

    $res = [];

    foreach (preg_split('/\r?\n/', trim($clients)) as $line) {
        if ($line === '') {
            continue;
        }

        $client = [];
        foreach (preg_split('/\s+/', trim($line)) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $client[$key] = $value;
        }

        if (isset($client['id'])) {
            $res[] = $client;
        }
    }

    return $res;
}

function reportStats(int $iteration, int $killCommands, int $killedClients): void {
    fwrite(
        STDERR,
        sprintf(
            "iteration=%d kill_commands=%d killed=%d\n",
            $iteration,
            $killCommands,
            $killedClients,
        )
    );
}

$options = getopt('', ['sleep:']);
$sleepSpec = $options['sleep'] ?? '1.0';
[$minSleepMicros, $maxSleepMicros] = parseSleepSpec($sleepSpec);

$redis = new Redis();
$redis->connect('localhost', 6379);

$iteration = 0;
$killCommands = 0;
$killedClients = 0;
$lastReportAt = microtime(true);

while (true) {
    $iteration++;
    $clients = clientList($redis);

    if ($clients !== []) {
        $client = $clients[random_int(0, count($clients) - 1)];
        $id = (string) $client['id'];
        $result = $redis->rawCommand('CLIENT', 'KILL', 'ID', $id);

        $killCommands++;
        $killedClients += (int) $result;
    }

    $now = microtime(true);
    if (($now - $lastReportAt) >= 1.0) {
        reportStats($iteration, $killCommands, $killedClients);
        $lastReportAt = $now;
    }

    if ($maxSleepMicros > 0) {
        $sleepMicros = $minSleepMicros === $maxSleepMicros
            ? $minSleepMicros
            : random_int($minSleepMicros, $maxSleepMicros);
        usleep($sleepMicros);
    }
}
