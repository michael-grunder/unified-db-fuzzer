## General instructions

* Prefer modern PHP syntax and idioms. We won't run this on anything < 8.4.
* Prefer modularity and generic code over duplication unless there is a good
  performance reason for it.
* Prefer defensive programming. Never ignore return values when they can fail
  unless there is a compelling reason (rarely we may not care about a failure).
* When adding features never just tack them on to existing code and pepper 
  specific handling code all around the codebase for this new feature. Instead
  we always want to maingain a clean architecture, so if the feature requires
  a sizeable redesign to do so, always prefer that.
* Wrappiing `phpredis` in a helper class is fine, but don't dispatch via 
  `__call` especially for any method that takes a reference (e.g. `scan`).
* After modifying source code make sure they compile. e.g quick-lint-js for 
  JS/TS, pylint for Python, etc.
* Run vendor/bin/phpstan analyze and fix any reported issues.
* Run vendor/bin/phpunit to make sure tests pass.
* Run npm run test:e2e for playwright tests if you changed any code that is 
  hit by those tests.
* Tests can be written that hit authenticated API endpoints as "pat.txt" is
  in the CWD so tests can be run like FFIA_PAT=$(cat pat.txt) vendor/bin/phpunit.
* Remember to update `README.md` if the changes change what is documented.
* After each change create or add `CHANGELOG.md`. As changes are added they go
  under `## Unreleased` and then at time of tag will be formalized. Within each
  version group changes into sections like `### Fixed`, `### Added`, 
  `### Changed`, `### Deprecated`, `### Removed`.
* If relevant try to verify changes to the site were successful by hitting
  http://localhost:8080/path/to/changed/endpoint. To be lear not litrally
  'path/to/changed/endpoint' but the actual path that was added/changed.
* If the site is up you can also check `site.log` in teh CWD for log messages
  and `php -S` logs.
* The project should always keep performance in mind when implementing features.
  Ideally it should be fast enough to server substantial volume on a small cloud
  instance.
* `redis.stub.php` is in the CWD which can be used to see exactly what PHPRedis
  method arguments are. Especially useful for new or uncommon methods.
