# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased
### Added
- Symfony Console application bootstrap in `bin/fuzz` with `work.php` kept as a compatibility wrapper.
- Modular fuzzing domain model with reusable Redis command classes, worker orchestration, and PHPUnit coverage.
- Seeded fuzz context support for reproducible command and argument selection.
- Optional `work` command timeout flags for configuring Relay connection and read timeouts.
### Changed
- Replaced the hand-rolled `work.php` implementation with a structured `src/` application using Composer autoloading.
### Fixed
- Normalized negative numeric CLI option values in `bin/fuzz` so `--ops -1` is parsed and validated correctly.
### Removed
