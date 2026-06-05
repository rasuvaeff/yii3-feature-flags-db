<?php

declare(strict_types=1);

/**
 * Shared bootstrap for the examples: an in-memory SQLite connection with the
 * `feature_flags` table created and seeded. In a real Yii3 application the
 * connection comes from yiisoft/db configuration and the table from the
 * shipped migration (`./yii migrate:up`).
 */

require __DIR__ . '/../vendor/autoload.php';

use Psr\SimpleCache\CacheInterface;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;

$psr16 = new class implements CacheInterface {
    /** @var array<string, mixed> */
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store[$key] ?? $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->store[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->store = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return [];
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->store[$key]);
    }
};

$db = new Connection(new Driver('sqlite::memory:'), new SchemaCache($psr16));
$db->open();

$db->createCommand(
    "CREATE TABLE feature_flags (
        name         VARCHAR(190) PRIMARY KEY,
        enabled      INTEGER      NOT NULL DEFAULT 1,
        salt         VARCHAR(190) NOT NULL DEFAULT '',
        rollout      INTEGER      NOT NULL DEFAULT 100,
        kill_switch  INTEGER      NOT NULL DEFAULT 0,
        environments TEXT         NOT NULL DEFAULT '[]'
    )",
)->execute();

$db->createCommand(
    "INSERT INTO feature_flags (name, enabled, salt, rollout, kill_switch, environments)
     VALUES ('new-checkout', 1, 'checkout-v1', 50, 0, '[\"production\"]'),
            ('dark-mode', 1, '', 100, 0, '[]')",
)->execute();

return $db;
