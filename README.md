# Fuzz

Redis fuzzing helpers built as a small Symfony Console application.

## Usage

Run the worker harness with:

```bash
php bin/fuzz work --keys=100 --mems=10 --workers=4 --ops=1000
```

`work.php` remains available as a compatibility wrapper around the console app:

```bash
php work.php --keys=100 --mems=10
```

Useful options:

- `--cmd-types=string,hash,zset` filters the registered fuzz commands by Redis data type.
- `--seed=1234` makes command selection and argument generation reproducible.
- `--flush` clears the current database before workers start.

`kill.php` is unchanged and remains a separate helper script.
