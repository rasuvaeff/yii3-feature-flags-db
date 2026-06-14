# AGENTS.md — yii3-feature-flags-db

Guidance for AI agents working on this package. Read before changing code.

## What this is

Database-backed feature flag provider for Yii3 applications. Implements
`WritableFlagProvider` (which `extends FlagProvider`) from
`rasuvaeff/yii3-feature-flags` core. Reads all flags from a DB table in one
query via the yiisoft/db `Query` builder (`SELECT *`), and maps each row to
`FlagConfig` → `Flag` through the `@internal FlagRowMapper`. Provides write
methods (`save()` upsert, `remove()` delete) and `CachedFlagProvider` — a
PSR-16 decorator with TTL-based caching and write-through invalidation.
A migration for `yiisoft/db-migration` ships in `migrations/`.
Namespace: `Rasuvaeff\Yii3FeatureFlagsDb`.

Public API: `DbFlagProvider`, `CachedFlagProvider`,
`Exception\InvalidFlagRowException`. `FlagRowMapper` is `@internal`
(row → `Flag` mapping, unit-tested directly; also exposes
`encodeEnvironments()` used by `DbFlagProvider::save()`).

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Invalid row = exception.** Never silently skip or default invalid DB rows.
   Throw `InvalidFlagRowException` with a descriptive message.
4. **Write-through cache.** `CachedFlagProvider::save()/remove()` must
   invalidate the cache after the inner provider succeeds. When the inner
   provider is read-only, writes are silent no-ops (never throw).
5. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make:

```bash
make build
make cs-fix
make psalm
make test
make test-coverage
make mutation
make release-check
```

`composer.lock` is gitignored (library).
`make test-coverage` and `make mutation` bootstrap `pcov` inside the
`composer:2` container because the base image has no coverage driver.

## Invariants & gotchas

- DB adapter is only a configuration source — kill switch, rollout hash,
  environment targeting remain in core.
- `getFlags()` returns the entire set eagerly; one query (`Query->from()->all()`) per call.
- Row → `Flag` mapping lives in `FlagRowMapper` (pure, unit-tested). The provider is
  covered by the SQLite integration test; the mapper by `FlagRowMapperTest`.
- `FlagRowMapper::encodeEnvironments()` is the inverse of `extractEnvironments()`.
  They round-trip: `extract(encode($x)) === $x` for `list<string>`.
- `DbFlagProvider::save()` uses `createCommand()->upsert(table, insertColumns)`.
- `DbFlagProvider::toRow()` stores `salt` as `''` when it equals the flag `name`
  to keep the round-trip invariant `emptySaltFallsBackToName`.
- `CachedFlagProvider` caches the whole set; invalidation by TTL or `clear()`.
  Write-through: `save()`/`remove()` call `clear()` after delegating to the
  inner `WritableFlagProvider`.
- Cache read/write failures are non-fatal (flags still returned).
- Empty table → `[]`.
- `WritableFlagProvider::class` is bound in `config/di.php` via
  `Reference::to(FlagProvider::class)` so write paths and read paths see the
  same instance. One key, one vendor — no `Duplicate key` conflict with core
  (core binds neither interface).
- Migrations are loaded by `yiisoft/db-migration` via `sourcePaths` (global-namespace
  classes in `migrations/`); the migration table name is a constructor argument.
- Invalid row / out-of-range rollout / invalid name → `InvalidFlagRowException`
  (core `\InvalidArgumentException` — both name and rollout flavors — wrapped).
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`,
  explicit types.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build`; if the change affects the public API or release
  process, also run `make release-check`. Paste the output.
