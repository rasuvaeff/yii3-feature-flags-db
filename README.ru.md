# rasuvaeff/yii3-feature-flags-db

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-feature-flags-db.svg?label=stable)](https://packagist.org/packages/rasuvaeff/yii3-feature-flags-db)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-feature-flags-db.svg)](https://packagist.org/packages/rasuvaeff/yii3-feature-flags-db)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-feature-flags-db/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-feature-flags-db/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-feature-flags-db/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/yii3-feature-flags-db/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-feature-flags-db/php)](https://packagist.org/packages/rasuvaeff/yii3-feature-flags-db)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-feature-flags-db.svg)](LICENSE.md)
[English version](README.md)

Database-backed провайдер feature-флагов для приложений Yii3. Реализует интерфейс
`FlagProvider` из `rasuvaeff/yii3-feature-flags` и читает конфигурацию флагов из
таблицы БД одним запросом.

> Используете AI-ассистента для написания кода? В [llms.txt](llms.txt) — компактный
> API-справочник, который можно встроить в промпт-контекст.

## Требования

- PHP 8.3+
- `rasuvaeff/yii3-feature-flags` ^1.0
- `yiisoft/db` ^2.0
- `yiisoft/db-migration` ^2.0 (поставляет миграцию таблицы)
- `yiisoft/definitions` ^3.0 (DI `Reference` для `WritableFlagProvider`)
- реализация PSR-16 cache — транзитивно требуется `yiisoft/db` 2.0
  (например `yiisoft/cache`)

## Установка

```bash
composer require rasuvaeff/yii3-feature-flags-db
```

С Yii3 config-plugin этот пакет биндит и `FlagProvider`, и `WritableFlagProvider`
на один и тот же экземпляр — **не** биндите ни один из этих ключей в приложении
или другом бэкенде, иначе `yiisoft/config` сообщит об ошибке `Duplicate key`.

## Схема БД

Создайте таблицу `feature_flags` (адаптируйте типы под вашу СУБД):

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

| Колонка | Тип | По умолчанию | Описание |
|---|---|---|---|
| `name` | `VARCHAR(190)` PK | — | Имя флага (regex ядра: `/^[a-z][a-z0-9._-]*$/`) |
| `enabled` | `BOOLEAN` | `true` | Активен ли флаг |
| `salt` | `VARCHAR(190)` | `''` | Пустая строка означает fallback на имя флага |
| `rollout` | `SMALLINT` | `100` | Процент 0..100 |
| `kill_switch` | `BOOLEAN` | `false` | Аварийный выключатель |
| `environments` | `JSON`/`TEXT` | `'[]'` | JSON-массив строк |

### Миграция

Пакет поставляет миграцию (`migrations/`) для [yiisoft/db-migration](https://github.com/yiisoft/db-migration).
Зарегистрируйте исходный путь в `config/params.php` вашего приложения:

```php
'yiisoft/db-migration' => [
    'sourcePaths' => [
        dirname(__DIR__) . '/vendor/rasuvaeff/yii3-feature-flags-db/migrations',
    ],
],
```

Затем примените и откатите её через Yii Console:

```bash
./yii migrate:up
./yii migrate:down --limit=1
```

Имя таблицы по умолчанию — `feature_flags`, и должно совпадать с аргументом
`table` у `DbFlagProvider`. Чтобы использовать кастомное имя, забиндите аргумент
конструктора миграции:

```php
M260605000000CreateFeatureFlagsTable::class => [
    '__construct()' => ['table' => 'my_feature_flags'],
],
```

## Использование

### Базовый DB-провайдер

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

### С PSR-16 кэшем

```php
use Rasuvaeff\Yii3FeatureFlagsDb\CachedFlagProvider;

$cached = new CachedFlagProvider(
    inner: $provider,
    cache: $psr16Cache,       // PSR-16 CacheInterface
    ttl: 60,                  // seconds
);

$featureFlags = new FeatureFlags(provider: $cached);
```

### Сброс кэша

```php
$cached->clear();             // removes cached flags, next call reloads from DB
```

## Запись флагов

`DbFlagProvider` и `CachedFlagProvider` оба реализуют `WritableFlagProvider`.
Используйте их для программного CRUD или admin UI.

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

- `save()` — это upsert с ключом `name` (insert or replace).
- `remove()` идемпотентен: удаление отсутствующего имени — no-op.
- `CachedFlagProvider` работает на запись write-through: после успешного
  `save()`/`remove()` он сбрасывает свой кэш перед возвратом, поэтому следующее
  чтение видит изменение. Когда внутренний провайдер read-only (например
  `ConfigFlagProvider`), вызовы записи — молчаливые no-op; можно безопасно
  оборачивать config-провайдер без исключений.
- Salt нормализуется: `Flag::__construct()` заменяет пустой salt на имя флага.
  При записи в строку сохраняется `''`, когда `salt === name`, поэтому round-trip
  чтение сохраняет тот же инвариант (`emptySaltFallsBackToName`).
- Окружения кодируются через `FlagRowMapper::encodeEnvironments()` и
  декодируются через `extractEnvironments()`. Round-trip гарантируется.

### Writable DI-binding

`config/di.php` биндит `WritableFlagProvider` на тот же экземпляр, что и
`FlagProvider`, через `Yiisoft\Definitions\Reference`:

```php
use Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider;
use Yiisoft\Definitions\Reference;

return [
    // ...FlagProvider::class closure omitted for brevity...
    WritableFlagProvider::class => Reference::to(FlagProvider::class),
];
```

Инжектируйте `WritableFlagProvider` в write-path'ах и `FlagProvider` в
read-path'ах; оба разрешаются в один и тот же объект.

## Справочник API

| Класс | Описание |
|---|---|
| `DbFlagProvider` | Читает все флаги из БД одним `SELECT *`; `implements WritableFlagProvider` |
| `CachedFlagProvider` | PSR-16 декоратор с write-through кэшем; `implements WritableFlagProvider` |
| `FlagRowMapper` | `@internal` маппер строка ↔ `Flag`; также экспонирует `encodeEnvironments()` |
| `InvalidFlagRowException` | Бросается, когда строка БД имеет невалидную структуру |

## Безопасность

- Kill switch, логика rollout-hash и environment-таргетинг остаются в ядре —
  DB-адаптер является лишь источником конфигурации.
- Невалидные данные строки (отсутствующие колонки, некорректный JSON, неверные
  типы, rollout вне диапазона, неверное имя флага) бросают
  `InvalidFlagRowException` вместо молчаливого включения фичи. Ошибки валидации
  ядра обёрнуты, поэтому вызывающему достаточно ловить только
  `InvalidFlagRowException`.
- Риск SQL-инъекций отсутствует: имя таблицы квотируется через yiisoft/db quoter.

## Примеры

См. [examples/](examples/) — запускаемые скрипты.

## Разработка

```bash
composer build          # полный gate: validate + normalize + cs + psalm + test
composer cs:fix         # авто-фикс стиля кода
composer psalm          # статический анализ
composer test           # запуск тестов
```

## Лицензия

BSD-3-Clause. См. [LICENSE.md](LICENSE.md).
