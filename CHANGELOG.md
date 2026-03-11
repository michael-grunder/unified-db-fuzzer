# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased
### Added
- `--worker-keyspace` for the normal fuzzer so each worker writes only within its own key namespace while reads can probe any worker namespace.
- Symfony Console application bootstrap in `bin/fuzz` with `work.php` kept as a compatibility wrapper.
- Modular fuzzing domain model with reusable Redis command classes, worker orchestration, and PHPUnit coverage.
- Seeded fuzz context support for reproducible command and argument selection.
- Optional `work` command timeout flags for configuring Relay connection and read timeouts.
- `bin/kill-clients` and a `kill-clients` console command for continuously killing one or more Redis clients with fixed or ranged sleep intervals.
- A dedicated `--staleness` fuzzing mode that compares Relay-cached `GET` results against authoritative PhpRedis reads using monotonic freshness envelopes, delayed rechecks, and regression detection.
- An AFL-style `--afl` status page for `bin/fuzz` that redraws a full-screen worker dashboard and, in staleness mode, highlights the worst stale keys seen so far.
### Changed
- Replaced the hand-rolled `work.php` implementation with a structured `src/` application using Composer autoloading.
- `bin/kill-clients` now defaults to killing only Relay connections detected from `CLIENT LIST`, with `--all-clients` available to target other client libraries too.
- `bin/fuzz` worker logging now uses Monolog with a compact `[microtime level] message` format and supports `--log-file` to write logs to disk instead of stderr.
- The staleness AFL view now separates keys that are still stale at the latest worker snapshot from the worst stale observations seen historically, and shows consecutive stale-read streaks in both tables.
- The staleness AFL leaderboards now show `last=` alongside the stale streak so “still stale” rows visibly age when they are not being re-observed, instead of looking frozen.
### Fixed
- Normalized negative numeric CLI option values in `bin/fuzz` so `--ops -1` is parsed and validated correctly.
- Workers now reconnect and retry once after routine Redis connection failures so killed clients do not immediately abort fuzz runs.
- Staleness-mode "still stale" tracking now drops keys from the live leaderboard as soon as the same worker mutates them, so old stale observations do not linger until a later fresh read happens to clear them.
- The staleness AFL leaderboards now use compact column headers instead of repeating `class=`, `steps=`, `streak=`, `last=`, and `age=` on every row.
