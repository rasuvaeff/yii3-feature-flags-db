# rasuvaeff/yii3-feature-flags-db

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-feature-flags-db.svg?label=stable)](https://packagist.org/packages/rasuvaeff/yii3-feature-flags-db)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-feature-flags-db.svg)](https://packagist.org/packages/rasuvaeff/yii3-feature-flags-db)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-feature-flags-db/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-feature-flags-db/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-feature-flags-db/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/yii3-feature-flags-db/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-feature-flags-db/php)](https://packagist.org/packages/rasuvaeff/yii3-feature-flags-db)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-feature-flags-db.svg)](LICENSE.md)
[Русская версия](README.ru.md)

Database-backed feature flag provider for Yii3 applications. Implements the `FlagProvider` interface from `rasuvaeff/yii3-feature-flags` and reads flag configuration from a database table in a single query.

> Using an AI coding assistant? [llms.txt](llms.txt) contains a compact API reference you can ingest in your prompt context.

## Requirements

- PHP 8.3+
- `rasuvaeff/yii3-feature-flags` ^1.0
- `yiisoft/db` ^2.0
- `yiisoft/db-migration` ^2.0 (ships the table migration)
- `yiisoft/definitions` ^3.0 (DI `Reference` for `WritableFlagProvider`)
- a PSR-16 cache implementation — required transitively by `yiisoft/db` 2.0
  (e.g. `yiisoft/cache`)

## Installation

```bash
composer require rasuvaeff/yii3-feature-flags-db
```

With Yii3 config-plugin this package binds both `FlagProvider` and
`WritableFlagProvider` to the same instance — do **not** also bind either key in
your application or another backend, or `yiisoft/config` reports a
`Duplicate key` error.

## Database schema

Create the `feature_flags` table (adjust types for your RDBMS):

```sql
CREATE TABLE feature_flags (
    name        VARCHAR(190) PRIMARY KEY,
    enabled     BOOLEAN      NOT NULL DEFAULT TRUE,
    salt        VARCHAR(190) NOT NULL DEFAULT '',
    rollout     SMALLINT     NOT NULL DEFAULT 100,
    kill_switch BOOLEAN      NOT NULL DEFAULT FALSE,
    environments TEXT        NOT NULL DEFAULT '[]'
);
```

| Column | Type | Default | Description |
|---|---|---|---|
| `name` | `VARCHAR(190)` PK | — | Flag name (core regex: `/^[a-z][a-z0-9._-]*$/`) |
| `enabled` | `BOOLEAN` | `true` | Whether the flag is active |
| `salt` | `VARCHAR(190)` | `''` | Empty string falls back to flag name |
| `rollout` | `SMALLINT` | `100` | Percentage 0..100 |
| `kill_switch` | `BOOLEAN` | `false` | Emergency off switch |
| `environments` | `JSON`/`TEXT` | `'[]'` | JSON array of strings |

### Migration

The package ships a migration (`migrations/`) for [yiisoft/db-migration](https://github.com/yiisoft/db-migration).
Register the source path in your app's `config/params.php`:

```php
'yiisoft/db-migration' => [
    'sourcePaths' => [
        dirname(__DIR__) . '/vendor/rasuvaeff/yii3-feature-flags-db/migrations',
    ],
],
```

Then apply and revert it with Yii Console:

```bash
./yii migrate:up
./yii migrate:down --limit=1
```

The table name defaults to `feature_flags` and must match the `table` argument of
`DbFlagProvider`. To use a custom name, bind the migration constructor argument:

```php
M260605000000CreateFeatureFlagsTable::class => [
    '__construct()' => ['table' => 'my_feature_flags'],
],
```

## Usage

### Basic DB provider

```php
use Rasuvaeff\Yii3FeatureFlags\FeatureFlags;
use Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider;

$provider = new DbFlagProvider(
    db: $connection,          // yiisoft/db ConnectionInterface
    table: 'feature_flags',   // optional, default is 'feature_flags'
);

$featureFlags = new FeatureFlags(provider: $provider);

if ($featureFlags->isEnabled('new-checkout')) {
    // new checkout flow
}
```

### With PSR-16 caching

```php
use Rasuvaeff\Yii3FeatureFlagsDb\CachedFlagProvider;

$cached = new CachedFlagProvider(
    inner: $provider,
    cache: $psr16Cache,       // PSR-16 CacheInterface
    ttl: 60,                  // seconds
);

$featureFlags = new FeatureFlags(provider: $cached);
```

### Clear cache

```php
$cached->clear();             // removes cached flags, next call reloads from DB
```

## Writing flags

`DbFlagProvider` and `CachedFlagProvider` both implement
`WritableFlagProvider`. Use them for programmatic CRUD or an admin UI.

```php
use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider;

/** @var WritableFlagProvider $provider */
$provider->save(flag: new Flag(
    name: 'new-checkout',
    enabled: true,
    rollout: 25,
    environments: ['production'],
));

$provider->remove(name: 'old-checkout');
```

- `save()` is an upsert keyed by `name` (insert or replace).
- `remove()` is idempotent: deleting a missing name is a no-op.
- `CachedFlagProvider` is write-through: after a successful `save()`/`remove()`
  it clears its cache before returning, so the next read reflects the change.
  When the inner provider is read-only (e.g. `ConfigFlagProvider`), write calls
  are silent no-ops — wrap a config provider safely without exceptions.
- Salt is normalized: `Flag::__construct()` replaces an empty salt with the
  flag name. On write the row stores `''` whenever `salt === name` so the
  round-trip read keeps the same invariant (`emptySaltFallsBackToName`).
- Environments are encoded through `FlagRowMapper::encodeEnvironments()` and
  decoded through `extractEnvironments()`. Round-trip is guaranteed.

### Writable DI binding

`config/di.php` binds `WritableFlagProvider` to the same instance as
`FlagProvider` via `Yiisoft\Definitions\Reference`:

```php
use Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider;
use Yiisoft\Definitions\Reference;

return [
    // ...FlagProvider::class closure omitted for brevity...
    WritableFlagProvider::class => Reference::to(FlagProvider::class),
];
```

Inject `WritableFlagProvider` in write paths and `FlagProvider` in read paths;
both resolve to the same object.

## API reference

| Class | Description |
|---|---|
| `DbFlagProvider` | Reads all flags from DB in one `SELECT *`; `implements WritableFlagProvider` |
| `CachedFlagProvider` | PSR-16 decorator with write-through cache; `implements WritableFlagProvider` |
| `FlagRowMapper` | `@internal` row ↔ `Flag` mapper; also exposes `encodeEnvironments()` |
| `InvalidFlagRowException` | Thrown when a DB row has invalid structure |

## Security

- Kill switch, rollout hash logic, and environment targeting remain in the core package — the DB adapter is only a configuration source.
- Invalid row data (missing columns, malformed JSON, wrong types, out-of-range rollout, invalid flag name) throws `InvalidFlagRowException` instead of silently enabling features. Core validation errors are wrapped, so callers only need to catch `InvalidFlagRowException`.
- No SQL injection risk: table name is quoted via yiisoft/db quoter.

## Examples

See [examples/](examples/) for runnable scripts.

## Development

```bash
composer build          # full gate: validate + normalize + cs + psalm + test
composer cs:fix         # auto-fix code style
composer psalm          # static analysis
composer test           # run tests
```

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
