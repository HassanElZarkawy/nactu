# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-14

### Added

- `CacheInvalidationService` with `invalidateByTags()`, `invalidateKeys()`, `clearAll()`, and `warm()` — previously documented, now implemented and bound in the container
- Config-driven non-retryable exception list (`retry.except`, defaulting to `ValidationException` and `ModelNotFoundException`)
- Middleware may now pass a modified command to `$next(...)`
- `make:handler` / `make:query-handler` append the `Handler` suffix automatically, matching `make:command` / `make:query`
- Feature (integration) test suite using Orchestra Testbench covering the service provider, auto-discovery, middleware wiring, cache configuration, and `make:*` generators
- `composer test`, `composer analyse`, `composer bench`, and `composer test-coverage` scripts

### Changed

- Dispatch performance overhaul (see `benchmarks/` for the harness): handlers are no longer constructed on cache hits or short-circuited dispatches (handler resolution is lazy — construction happens only if the pipeline reaches the handler); cache keys for public-property queries are built with a single native `get_object_vars()` call instead of per-property reflection; reflection metadata is cached statically instead of per-request; the redundant key sort was removed; the retry middleware no longer serializes a stack trace on every failure and instead uses an injected logger (`Psr\Log\LoggerInterface`)
- New opt-in `handlers.singleton` config flag reuses one handler instance per handler class across dispatches, skipping per-dispatch container construction entirely (stateful handlers will keep state between dispatches — off by default)
- Generators overhauled into two commands: `nactu:command` and `nactu:query`. Each generates the message class together with its handler; a `--handler-only` flag covers standalone handler generation. `make:command` no longer shadows Laravel's core console-command generator
- Generators now create a folder per use case: the last name segment is the use case, preceding segments are grouping namespaces — `nactu:query User/GetUserByCriteria` generates `app/Queries/User/GetUserByCriteria/{GetUserByCriteriaQuery, GetUserByCriteriaHandler}.php`. Handlers are co-located with their messages, so generated handlers no longer need `use` imports
- Handler discovery defaults to the same namespace as the message (`handlers_namespace` now defaults to `App\Commands`, `query_handlers_namespace` to `App\Queries`); both keys remain available to point discovery at separate handler directories
- Handler imports for sub-namespaced messages are now derived from the real message namespace (`App\Commands\Admin\CreateUserCommand`) instead of assuming `App\Commands`
- Cache keys now include the fully-qualified class name and use XXH128 hashing instead of short names + CRC32, preventing cross-class collisions
- Config `cache.store` and `cache.prefix` are now used: cached queries resolve the configured store and are keyed under the configured prefix
- Middleware is attached inside the container bindings, so the stack survives container flushes (Laravel Octane)
- Auto-discovery requires an exact namespace boundary (`App\Commands\` prefix) and escapes the class suffix when building handler names
- Cache key generation ignores uninitialized typed and static properties, guards nested object hashing against cyclic references, falls back to `serialize()` for non-JSON-encodable array values, and keeps zero/falsy key parts
- Handlers without `handle()` must now define `__invoke()`, with a clear exception message when neither exists
- `make:command` / `make:query` no longer generate a handler when the command/query class already exists
- Package requires only the `illuminate/*` components it uses (`cache`, `console`, `container`, `support`) instead of the whole `laravel/framework`
- PHPStan analysis raised from level 1 to level 8; PHPUnit notices eliminated

### Removed

- `make:handler` and `make:query-handler` (hidden) commands — superseded by the `--handler-only` flag on `nactu:command` / `nactu:query`
- Previous `make:command` / `make:query` names — use `nactu:command` / `nactu:query` instead

### Fixed

- Docs: retry attempt semantics (`max_attempts` is total attempts, not retries), cache-tag driver support (`array` is taggable; `file`, `dynamodb`, `octane` are not), `forList()` page segment naming, Laravel version requirement (13+)
- Added missing `LICENSE` file and this changelog
