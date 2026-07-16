# rasuvaeff/yii3-feature-flags-db
[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-feature-flags-db.svg?label=stable)](https://packagist.org/packages/rasuvaeff/yii3-feature-flags-db)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-feature-flags-db.svg)](https://packagist.org/packages/rasuvaeff/yii3-feature-flags-db)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-feature-flags-db/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-feature-flags-db/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-feature-flags-db/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/yii3-feature-flags-db/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-feature-flags-db/php)](https://packagist.org/packages/rasuvaeff/yii3-feature-flags-db)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-feature-flags-db.svg)](LICENSE.md)
Поставщик флагов функций на основе базы данных для приложений Yii3. Реализует интерфейс FlagProvider из rasuvaeff/yii3-feature-flags и считывает конфигурацию флагов из таблицы базы данных в одном запросе.

 > Используете помощника по программированию с искусственным интеллектом? [llms.txt](llms.txt) содержит компактную ссылку на API, которую вы можете использовать в контексте приглашения. @@ЛИНИЯ@@
## Требования
- PHP 8.3+
 - `rasuvaeff/yii3-feature-flags` ^1.0
 - `yiisoft/db` ^2.0
 - `yiisoft/db-migration` ^2.0 (отправляет миграцию таблицы)
 - `yiisoft/definitions` ^3.0 (DI `Reference` для `WritableFlagProvider`)
 — реализация кэша PSR-16 — требуется транзитивно для `yiisoft/db` 2.0
 (например, `yiisoft/cache`)

## Установка
```bash
composer require rasuvaeff/yii3-feature-flags-db
```
С помощью плагина конфигурации Yii3 этот пакет привязывает как `FlagProvider`, так и
 `WritableFlagProvider` к одному и тому же экземпляру — **не** также привязывайте ни один ключ в
 вашего приложения или другого бэкэнда, иначе `yiisoft/config` сообщит об ошибке
 `Duplate key`. @@ЛИНИЯ@@
## Схема базы данных
Создайте таблицу Feature_flags (настройте типы для вашей СУБД):

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
| Столбец | Тип | По умолчанию | Описание |
 |---|---|---|---|
 | `имя` | `ВАРЧАР(190)` ПК | — | Имя флага (основное регулярное выражение: `/^[a-z][a-z0-9._-]*$/`) |
 | `включено` | `БУЛЕВАЯ` | `правда` | Активен ли флаг |
 | `соль` | `ВАРЧАР(190)` | `''` | Пустая строка возвращается к имени флага |
 | `развертывание` | `СМАЛЛИНТ` | `100` | Процент 0..100 |
 | `kill_switch` | `БУЛЕВАЯ` | `ложь` | Аварийный выключатель |
 | `окружающая среда` | `JSON`/`ТЕКСТ` | `'[]'` | Массив строк JSON | @@ЛИНИЯ@@
### Миграция
The package ships a migration (`migrations/`) for [yiisoft/db-migration](https://github.com/yiisoft/db-migration).
Зарегистрируйте исходный путь в файле `config/params.php` вашего приложения:

```php
'yiisoft/db-migration' => [
    'sourcePaths' => [
        dirname(__DIR__) . '/vendor/rasuvaeff/yii3-feature-flags-db/migrations',
    ],
],
```
Затем примените и отмените его с помощью консоли Yii:
.
```bash
./yii migrate:up
./yii migrate:down --limit=1
```
Имя таблицы по умолчанию равно `feature_flags` и должно соответствовать аргументу `table`
 `DbFlagProvider`. Чтобы использовать собственное имя, привяжите аргумент конструктора миграции:

```php
M260605000000CreateFeatureFlagsTable::class => [
    '__construct()' => ['table' => 'my_feature_flags'],
],
```
## Использование
### Базовый поставщик БД
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
### С кэшированием PSR-16
```php
use Rasuvaeff\Yii3FeatureFlagsDb\CachedFlagProvider;

$cached = new CachedFlagProvider(
    inner: $provider,
    cache: $psr16Cache,       // PSR-16 CacheInterface
    ttl: 60,                  // seconds
);

$featureFlags = new FeatureFlags(provider: $cached);
```
### Очистить кеш
```php
$cached->clear();             // removes cached flags, next call reloads from DB
```
## Написание флагов
`DbFlagProvider` и `CachedFlagProvider` реализуют
 `WritableFlagProvider`. Используйте их для программного CRUD или пользовательского интерфейса администратора. @@ЛИНИЯ@@
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
- `save()` — это upsert с ключом `name` (вставка или замена).
 - `remove()` является идемпотентным: удаление отсутствующего имени не является операцией.
 - `CachedFlagProvider` имеет сквозную запись: после успешного `save()`/`remove()`
 он очищает свой кеш перед возвратом, поэтому следующее чтение отражает изменение.
 Когда внутренний поставщик доступен только для чтения (например, `ConfigFlagProvider`), вызовы записи
 являются молчаливыми и неактивными — безопасно оборачивают поставщика конфигурации без исключений.
 — соль нормализована: `Flag::__construct()` заменяет пустую соль именем флага
. При записи строка сохраняет `''` всякий раз, когда `salt === name`, поэтому двустороннее чтение
 сохраняет тот же инвариант (`emptySaltFallsBackToName`).
 — среды кодируются с помощью `FlagRowMapper::encodeEnvironments()`, а
 декодируются с помощью `extractEnvironments()`. Поездка туда и обратно гарантирована. @@ЛИНИЯ@@
### Записываемая привязка DI
`config/di.php` привязывает `WritableFlagProvider` к тому же экземпляру, что и
 `FlagProvider` через `Yiisoft\Definitions\Reference`:

```php
use Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider;
use Yiisoft\Definitions\Reference;

return [
    // ...FlagProvider::class closure omitted for brevity...
    WritableFlagProvider::class => Reference::to(FlagProvider::class),
];
```
Внедрите `WritableFlagProvider` в пути записи и `FlagProvider` в пути чтения;
 оба разрешаются к одному и тому же объекту. @@ЛИНИЯ@@
## Справочник по API
| Класс | Описание |
 |---|---|
 | `DbFlagProvider` | Считывает все флаги из БД за один `SELECT *`; `реализует WritableFlagProvider` |
 | `CachedFlagProvider` | Декоратор PSR-16 с кэшем сквозной записи; `реализует WritableFlagProvider` |
 | `FlagRowMapper` | строка `@internal` ↔ преобразователь флага; также предоставляет `encodeEnvironments()` |
 | `InvalidFlagRowException` | Вызывается, когда строка БД имеет недопустимую структуру | @@ЛИНИЯ@@
## Безопасность
- Переключатель аварийного отключения, хэш-логика развертывания и нацеливание на среду остаются в базовом пакете — адаптер БД является лишь источником конфигурации.
 — недопустимые данные строки (отсутствующие столбцы, неправильный формат JSON, неправильные типы, развертывание за пределами допустимого диапазона, неверное имя флага) выдает исключение InvalidFlagRowException вместо автоматического включения функций. Ошибки базовой проверки упакованы, поэтому вызывающим сторонам нужно только перехватить InvalidFlagRowException.
 - Риск SQL-инъекций отсутствует: имя таблицы цитируется через yiisoft/db quoter. @@ЛИНИЯ@@
## Примеры
См. [examples/](examples/) для работоспособных сценариев. @@ЛИНИЯ@@
## Разработка
```bash
composer build          # full gate: validate + normalize + cs + psalm + test
composer cs:fix         # auto-fix code style
composer psalm          # static analysis
composer test           # run tests
```
## Лицензия
BSD-3-пункт. См. [LICENSE.md](LICENSE.md).
