<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * Minimal in-memory PSR-16 cache for round-trip tests (no TTL expiry simulation).
 *
 * @internal
 */
final class ArrayCache implements CacheInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $store = [];

    public int $getCalls = 0;

    public int $setCalls = 0;

    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        ++$this->getCalls;

        return \array_key_exists($key, $this->store) ? $this->store[$key] : $default;
    }

    #[\Override]
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        ++$this->setCalls;
        $this->store[$key] = $value;

        return true;
    }

    #[\Override]
    public function delete(string $key): bool
    {
        unset($this->store[$key]);

        return true;
    }

    #[\Override]
    public function clear(): bool
    {
        $this->store = [];

        return true;
    }

    #[\Override]
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return [];
    }

    #[\Override]
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        return true;
    }

    #[\Override]
    public function deleteMultiple(iterable $keys): bool
    {
        return true;
    }

    #[\Override]
    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->store);
    }
}
