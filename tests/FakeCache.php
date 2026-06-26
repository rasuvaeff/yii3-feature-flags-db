<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * @internal
 */
final class FakeCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @var list<array{method: string, key: string, args: array}> */
    public array $calls = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $this->calls[] = ['method' => 'get', 'key' => $key, 'args' => [$key, $default]];

        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->calls[] = ['method' => 'set', 'key' => $key, 'args' => [$key, $value, $ttl]];
        $this->data[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        $this->calls[] = ['method' => 'delete', 'key' => $key, 'args' => [$key]];
        unset($this->data[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->calls[] = ['method' => 'clear', 'key' => '', 'args' => []];
        $this->data = [];

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
        return array_key_exists($key, $this->data);
    }
}
