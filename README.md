# Fuzz

Redis fuzzing helpers built as a small Symfony Console application.

## Usage

Run the worker harness with:

```bash
php bin/fuzz work --keys=100 --mems=10 --workers=4 --ops=1000
```

Write worker logs to a file instead of stderr with:

```bash
php bin/fuzz --log-file=/tmp/fuzz.log --keys=100 --mems=10 --workers=4 --ops=1000
```

Run the client killer in a separate session with:

```bash
php bin/kill-clients --sleep=0.01-0.9 --kills=1-3
```

`work.php` remains available as a compatibility wrapper around the console app:

```bash
php work.php --keys=100 --mems=10
```

Useful options:

- `--cmd-types=string,hash,zset` filters the registered fuzz commands by Redis data type.
- `--seed=1234` makes command selection and argument generation reproducible.
- `--log-file=/tmp/fuzz.log` writes compact Monolog output like `[1710111222.123456 INFO] spawned worker pid=1234` to a file instead of stderr.
- `--timeout=1.5` sets the Relay connection timeout in seconds.
- `--read-timeout=5.0` sets the Relay read timeout in seconds.
- `--flush` clears the current database before workers start.
- `--staleness` switches to the shared-cache staleness/regression fuzzer. This mode uses Relay for cached reads and a direct PhpRedis connection as authoritative truth.
- `--stale-delays=0,100,500,1000,5000,20000` controls delayed recheck buckets in microseconds.
- `--stale-persistent-checks=3`, `--stale-hard-steps=8`, and `--stale-stuck-repeats=5` tune when stale observations become hard failures.
- Workers automatically reconnect once and retry the interrupted Redis operation when a normal connection failure closes a client unexpectedly.
- `bin/kill-clients` continuously selects one or more Relay client ids from `CLIENT LIST`, excludes its own control connection, and kills them.
- `bin/kill-clients --all-clients` disables the default Relay-only filter and targets any client type.
- `bin/kill-clients --sleep=0.05-0.25 --kills=2-5 --seed=1234` makes sleep timing and client selection reproducible.

Minimal staleness run:

```bash
php bin/fuzz work --staleness --workers=4 --ops=5000 --keys=16 --seed=1234 --flush
```

The staleness fuzzer currently targets string `SET`/`GET` plus `DEL`/recreate churn. Values are written as deterministic JSON envelopes with monotonic Redis versions so each worker can detect:

- cached reads that stay behind authoritative truth
- regressions to an older cached version
- stale values that survive deletes
- stale misses after recreate
