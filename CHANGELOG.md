# Changelog

## 1.0.1 — 2026-06-30

- Add `/benchmarks` and `/Makefile` to `.gitattributes` export-ignore.

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.1 — 2026-06-27

- Migrate test suite from PHPUnit to Testo. Internal change, no public API impact.

## 1.0.0 — 2026-06-14

- Initial stable release.
- `DbFlagProvider` and `CachedFlagProvider` now implement
  `Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider` (new in core 1.0.0):
  - `save(Flag)` — upsert keyed by flag `name`.
  - `remove(string)` — idempotent delete.
  - `CachedFlagProvider` is write-through: after delegating to a writable inner
    provider it clears its cache; on a read-only inner it is a silent no-op.
- `FlagRowMapper::encodeEnvironments()` (new public static method) is the
  inverse of `extractEnvironments()` and is used by `DbFlagProvider::save()`.
  The two are round-trip compatible.
- `DbFlagProvider::toRow()` normalises `salt` to `''` when it equals the flag
  `name` to preserve the `emptySaltFallsBackToName` invariant on read-back.
- `config/di.php` now binds `WritableFlagProvider::class` to the same instance
  as `FlagProvider::class` via `Yiisoft\Definitions\Reference`. One key, one
  vendor — no `Duplicate key` conflict with the core.
- Requires `rasuvaeff/yii3-feature-flags` ^1.0 and adds `yiisoft/definitions` ^3.0.
- `FlagRowMapper` now wraps `\InvalidArgumentException` from core (both
  `InvalidFlagNameException` for name and plain `InvalidArgumentException`
  for rollout range) into `InvalidFlagRowException`.

