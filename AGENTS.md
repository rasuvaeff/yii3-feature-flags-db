# AGENTS.md — yii3-feature-flags-db

Guidance for AI agents working on this package. Read before changing code.

## What this is

Database-backed feature flag provider for Yii3 applications. Implements
`FlagProvider` from `rasuvaeff/yii3-feature-flags` core. Reads all flags from
a DB table in one query via the yiisoft/db `Query` builder (`SELECT *`), and maps
each row to `FlagConfig` → `Flag` through the `@internal FlagRowMapper`.
Also provides `CachedFlagProvider` — a PSR-16 decorator with TTL-based caching.
A migration for `yiisoft/db-migration` ships in `migrations/`.
Namespace: `Rasuvaeff\Yii3FeatureFlagsDb`.

Public API: `DbFlagProvider`, `CachedFlagProvider`, `Exception\InvalidFlagRowException`.
`FlagRowMapper` is `@internal` (row → `Flag` mapping, unit-tested directly).

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Invalid row = exception.** Never silently skip or default invalid DB rows.
   Throw `InvalidFlagRowException` with a descriptive message.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
```

Or with Make:

```bash
make build
make cs-fix
make psalm
make test
```

`composer.lock` is gitignored (library).

## Invariants & gotchas

- DB adapter is only a configuration source — kill switch, rollout hash,
  environment targeting remain in core.
- `getFlags()` returns the entire set eagerly; one query (`Query->from()->all()`) per call.
- Row → `Flag` mapping lives in `FlagRowMapper` (pure, unit-tested). The provider is
  covered by the SQLite integration test; the mapper by `FlagRowMapperTest`.
- Migrations are loaded by `yiisoft/db-migration` via `sourcePaths` (global-namespace
  classes in `migrations/`); the migration table name is a constructor argument.
- Invalid row / out-of-range rollout / invalid name → `InvalidFlagRowException`
  (core validation errors are wrapped).
- `CachedFlagProvider` caches the whole set; invalidation by TTL or `clear()`.
- Cache read/write failures are non-fatal (flags still returned).
- Empty table → `[]`.
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`,
  explicit types.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build` and paste the output.
