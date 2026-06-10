# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 2.1.1 — 2026-06-10

- `CachedFlagProvider` cache key is now dot-separated (`rasuvaeff.feature-flags.all`)
  instead of colon-separated. PSR-16 reserves `{}()/\@:`, and some PSR-16 caches
  (e.g. `yiisoft/test-support`'s `MemorySimpleCache`) reject the old key. On
  upgrade the previous cache entry is ignored once (a single cold read), then
  repopulated — no action required.
- Tests use `yiisoft/test-support` doubles (`MemorySimpleCache`) instead of
  hand-rolled cache fakes.

## 2.1.0 — 2026-06-09

- Require `rasuvaeff/yii3-feature-flags` ^2.0. The core no longer binds
  `FlagProvider`; this package remains the sole binder, so installing it next to
  the core no longer triggers the `Duplicate key "...\FlagProvider"` config error.

## 2.0.0 — 2026-06-07

- Require `yiisoft/db` ^2.0 and `yiisoft/db-migration` ^2.0; drop yiisoft/db 1.x
  support. Consumers must provide a PSR-16 cache implementation (a transitive
  requirement of yiisoft/db 2.0).

## 1.0.0 — 2026-06-05

- Initial release.
