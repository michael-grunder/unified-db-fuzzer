## General Instructions

* Prefer modern PHP syntax and idioms. The codebase targets PHP 8.4+, so take
  advantage of modern language features (readonly properties, enums, first-class
  callables, typed constants, etc).

* Prefer modularity and reusable abstractions over duplication. If multiple
  fuzzing components share behavior (e.g. corpus handling, Redis interaction,
  process orchestration), extract shared logic into dedicated classes rather
  than copying code.

* Prefer defensive programming. Never ignore return values when they can fail.
  Handle exceptions explicitly and validate assumptions, especially around:
  - Redis responses
  - process execution
  - filesystem operations
  - IPC/shared state between workers

* Maintain a clean architecture when adding features. Never scatter feature-
  specific logic throughout the codebase. If a new fuzzing mode or feature
  requires structural changes (e.g. new execution strategies, corpus
  management, or worker coordination), refactor the relevant subsystems
  rather than bolting logic onto existing code.

* Symfony Console should be used for all CLI interfaces. Commands should:
  - live in dedicated classes
  - follow Symfony's command structure
  - avoid monolithic command handlers
  - keep argument/option parsing separate from execution logic

* Avoid magic dispatch mechanisms (such as `__call`) in helper classes unless
  there is a very strong reason. Explicit APIs are preferred for clarity,
  tooling support, and static analysis.

* The fuzzer should remain deterministic and reproducible whenever possible.
  When randomness is required:
  - allow seeds to be specified
  - make RNG behavior reproducible
  - ensure failures can be replayed reliably.

* Performance matters. The tool may run many concurrent workers and execute
  large numbers of iterations. Avoid unnecessary allocations, expensive
  abstractions in hot paths, and excessive logging in tight loops.

* Favor clear separation between components such as:
  - command layer (Symfony Console)
  - fuzz execution engine
  - corpus and input generation
  - result collection and crash handling
  - Redis or external system interaction
  - reporting/statistics

* Never mix CLI presentation logic with fuzzing logic. Commands should
  orchestrate the system, not implement core fuzzing behavior.

---

## Code Quality and Validation

After modifying source code:

* Ensure the code parses correctly.

* Run static analysis: vendor/bin/phpstan analyze


Resolve all reported issues unless there is a strong justification not to.

* Run the test suite: vendor/bin/phpunit


All tests must pass.

* When adding new features, ensure unit tests cover:
- deterministic fuzz input generation
- command behavior
- failure/crash handling
- statistics collection

* Favor unit tests for logic and integration tests for worker/process behavior.

---

## Failure Handling and Reproducibility

* Fuzzer failures (crashes, protocol errors, assertion failures, etc) should
always produce reproducible artifacts.

* A failure should record enough information to reproduce the issue later,
such as:
- seed used
- input payload
- command sequence
- Redis/server state if applicable

* Reproduction artifacts should be written to disk in a structured and
machine-readable format when possible.

---

## Logging and Diagnostics

* Logging should be structured and useful for diagnosing failures without
overwhelming the output.

* Worker status updates and progress information should be aggregated and
presented clearly by the CLI interface.

* Avoid excessive output inside tight fuzz loops. Logging should be minimal
during high-volume execution.

---

## Documentation

* Update `README.md` if the CLI interface, configuration, or usage changes.

* Add/update `CHANGELOG.md` with what was done.

## General Philosophy

The tool should remain:

* deterministic and reproducible
* fast enough to run large fuzz workloads
* easy to extend with new fuzzing strategies
* easy to debug when failures occur
* well structured rather than expediently hacked together

