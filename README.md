# Fuzz

Redis fuzzing helpers built as a small Symfony Console application.

## Usage

Run the worker harness with:

```bash
php bin/fuzz work --keys=100 --mems=10 --workers=4 --ops=1000
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
- `--timeout=1.5` sets the Relay connection timeout in seconds.
- `--read-timeout=5.0` sets the Relay read timeout in seconds.
- `--flush` clears the current database before workers start.
- `bin/kill-clients` continuously selects one or more client ids from `CLIENT LIST`, excludes its own control connection, and kills them.
- `bin/kill-clients --sleep=0.05-0.25 --kills=2-5 --seed=1234` makes sleep timing and client selection reproducible.
