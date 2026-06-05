<?php

declare(strict_types=1);

use Psr\SimpleCache\CacheInterface;
use Rasuvaeff\Yii3FeatureFlags\FeatureFlags;
use Rasuvaeff\Yii3FeatureFlagsDb\CachedFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider;
use Yiisoft\Db\Connection\ConnectionInterface;

/** @var ConnectionInterface $db */
$db = require __DIR__ . '/bootstrap.php';

// Any PSR-16 cache works (yiisoft/cache, symfony/cache, ...). In-memory here for the demo.
$cache = new class implements CacheInterface {
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

$cached = new CachedFlagProvider(
    inner: new DbFlagProvider(db: $db),
    cache: $cache,
    ttl: 60,
);

$featureFlags = new FeatureFlags(provider: $cached);

echo 'First call (cache miss, loads from DB): ' . ($featureFlags->isEnabled('new-checkout') ? 'enabled' : 'disabled') . "\n";
echo 'Second call (served from cache): ' . ($featureFlags->isEnabled('new-checkout') ? 'enabled' : 'disabled') . "\n";

$cached->clear();
echo "Cache cleared — next call reloads from DB.\n";

$db->close();
