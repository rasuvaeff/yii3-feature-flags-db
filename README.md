# rasuvaeff/yii3-feature-flags-db

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-feature-flags-db.svg?label=stable)](https://packagist.org/packages/rasuvaeff/yii3-feature-flags-db)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-feature-flags-db.svg)](https://packagist.org/packages/rasuvaeff/yii3-feature-flags-db)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-feature-flags-db/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-feature-flags-db/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-feature-flags-db/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/yii3-feature-flags-db/actions)
[![Coverage](https://codecov.io/gh/rasuvaeff/yii3-feature-flags-db/branch/master/graph/badge.svg)](https://codecov.io/gh/rasuvaeff/yii3-feature-flags-db)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-feature-flags-db/php)](https://packagist.org/packages/rasuvaeff/yii3-feature-flags-db)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-feature-flags-db.svg)](LICENSE.md)

Database-backed feature flag provider for Yii3 applications. Implements the `FlagProvider` interface from `rasuvaeff/yii3-feature-flags` and reads flag configuration from a database table in a single query.

> Using an AI coding assistant? [llms.txt](llms.txt) contains a compact API reference you can ingest in your prompt context.

## Requirements

- PHP 8.3+
- `rasuvaeff/yii3-feature-flags` ^1.0
- `yiisoft/db` ^2.0
- `yiisoft/db-migration` ^2.0 (ships the table migration)
- a PSR-16 cache implementation — required transitively by `yiisoft/db` 2.0
  (e.g. `yiisoft/cache`)

## Installation

```bash
composer require rasuvaeff/yii3-feature-flags-db
```

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

## API reference

| Class | Description |
|---|---|
| `DbFlagProvider` | Reads all flags from DB in one `SELECT *` |
| `CachedFlagProvider` | PSR-16 decorator, caches entire flag set with TTL |
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
