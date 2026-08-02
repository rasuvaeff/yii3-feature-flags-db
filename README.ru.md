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

Регистрируйте поставляемую миграцию **по namespace** — без путей в `vendor/`:

```php
// config/common/di/migration.php
use Yiisoft\Db\Migration\Service\MigrationService;

return [
    MigrationService::class => [
        'setSourceNamespaces()' => [[
            'App\\Migration',
            'Rasuvaeff\\Yii3FeatureFlagsDb\\Migration',
        ]],
    ],
];
```

> **Внимание: сниппет выше пока не находит миграцию.** Это правильная
> конфигурация, и она заработает без единой правки с вашей стороны, как только
> починят описанный ниже баг апстрима — но сегодня `./yii migrate:up` печатает
> «Your system is up-to-date», возвращает 0 и не создаёт таблиц.
>
> `yiisoft/db-migration` (2.0.x) резолвит namespace в каталог так: берёт первую
> запись в `composer/autoload_psr4.php`, с которой namespace начинается,
> сравнивая с ключом без завершающего разделителя, а остаток отрезает по
> *необрезанной* длине. Обрезание разделителя стирает границу сегмента, поэтому
> `Rasuvaeff\Yii3FeatureFlags\` совпадает с `Rasuvaeff\Yii3FeatureFlagsDb\Migration`
> так, будто является его родителем — а этот пакет от него зависит, то есть
> коллизия есть всегда. Полученного каталога не существует, несуществующие
> каталоги discovery пропускает молча, и ничего не применяется.

Пока это не починено в апстриме, применяйте поставляемую миграцию сами:

```php
// src/Console/MigrateCommand.php (фрагмент)
use Rasuvaeff\Yii3FeatureFlagsDb\Migration\M260605000000CreateFeatureFlagsTable;
use Yiisoft\Db\Migration\Informer\ConsoleMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Injector\Injector;

$builder = new MigrationBuilder($db, new ConsoleMigrationInformer());
$injector = new Injector($container);

foreach ([
    M260605000000CreateFeatureFlagsTable::class,
] as $class) {
    $injector->make($class)->up($builder);
}
```

`Injector::make()` обязателен вместо `new`: он резолвит value object имени
таблицы из вашей конфигурации. Держите цикл идемпотентным (пропускать, если
таблица уже есть) — собственной истории миграций у него нет, поэтому
`./yii migrate:down` его не откатит; если нужен откат, вызывайте `down()`
явно.

#### Своё имя таблицы

Задаётся в params — то же значение получают и миграция, и `DbFlagProvider`:

```php
// config/common/params.php
'rasuvaeff/yii3-feature-flags-db' => [
    'table' => 'my_feature_flags',
    'table_prefix' => '',   // добавляется перед `table`; например 'rsv_' → rsv_my_feature_flags
],
```

> **Не настраивайте миграцию через DI-контейнер.**
> `M...::class => ['__construct()' => ['table' => ...]]` не работает: миграцию
> создаёт `Injector::make()`, который резолвит аргументы по типу и никогда не
> читает определение контейнера по имени класса самой миграции. Хуже того,
> добавление такого определения роняет контейнер на этапе сборки в **каждом**
> запросе, потому что класс не автозагружается, пока его не подключит раннер
> миграций. Этот рецепт был описан в 1.x и никогда не работал.

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
