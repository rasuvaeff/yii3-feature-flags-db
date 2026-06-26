<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests;

use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlags\FlagConfig;
use Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\CachedFlagProvider;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

#[Test]
#[Covers(CachedFlagProvider::class)]
final class CachedFlagProviderTest
{
    private const string CACHE_KEY = 'rasuvaeff.feature-flags.all';

    public function loadsFromInnerOnMissAndStoresWithKeyAndDefaultTtl(): void
    {
        $flag = $this->flag('test-flag');
        $inner = new FakeFlagProvider(flags: ['test-flag' => $flag]);

        $cache = new FakeCache();
        $provider = new CachedFlagProvider(inner: $inner, cache: $cache);
        $result = $provider->getFlags();

        Assert::array($result)->hasKeys('test-flag');
        Assert::same($result['test-flag']->name, 'test-flag');

        $setCalls = array_filter($cache->calls, static fn(array $c): bool => $c['method'] === 'set');
        Assert::count($setCalls, 1);
        $setCall = array_values($setCalls)[0];
        Assert::same($setCall['key'], self::CACHE_KEY);
        Assert::true(is_array($setCall['args'][1]));
        Assert::same($setCall['args'][2], 60);
    }

    public function passesConfiguredTtlToCache(): void
    {
        $inner = new FakeFlagProvider();

        $cache = new FakeCache();
        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 120);
        $provider->getFlags();

        $setCalls = array_filter($cache->calls, static fn(array $c): bool => $c['method'] === 'set');
        Assert::count($setCalls, 1);
        $setCall = array_values($setCalls)[0];
        Assert::same($setCall['args'][2], 120);
    }

    public function returnsCachedWithoutCallingInnerOnHit(): void
    {
        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['flag-a' => $this->flag('flag-a'), 'flag-b' => $this->flag('flag-b')]);

        $inner = new FakeFlagProvider();

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $result = $provider->getFlags();

        Assert::count($result, 2);
        Assert::array($result)->hasKeys('flag-a', 'flag-b');
        Assert::count($inner->calls, 0);
    }

    public function roundTripServesSecondCallFromCache(): void
    {
        $flags = ['flag-a' => $this->flag('flag-a'), 'flag-b' => $this->flag('flag-b')];
        $inner = new FakeFlagProvider(flags: $flags);

        $provider = new CachedFlagProvider(inner: $inner, cache: new MemorySimpleCache(), ttl: 60);

        $first = $provider->getFlags();
        $second = $provider->getFlags();

        Assert::count($first, 2);
        Assert::count($second, 2);
        Assert::array($first)->hasKeys('flag-b');
        Assert::array($second)->hasKeys('flag-b');
        Assert::count($inner->calls, 1);
    }

    public function clearRemovesCachedKey(): void
    {
        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['flag-a' => $this->flag('flag-a')]);

        $inner = new FakeFlagProvider();

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $provider->clear();

        Assert::false($cache->has(self::CACHE_KEY));
    }

    public function clearForcesReloadFromInner(): void
    {
        $flag = $this->flag('rt-flag');
        $inner = new FakeFlagProvider(flags: ['rt-flag' => $flag]);

        $provider = new CachedFlagProvider(inner: $inner, cache: new MemorySimpleCache(), ttl: 60);

        $provider->getFlags();
        $provider->clear();
        $provider->getFlags();

        $getFlagsCalls = array_filter($inner->calls, static fn(array $c): bool => $c['method'] === 'getFlags');
        Assert::count($getFlagsCalls, 2);
    }

    public function fallsBackToInnerWhenCacheReadAndWriteFail(): void
    {
        $flag = $this->flag('rt-flag');
        $inner = new FakeFlagProvider(flags: ['rt-flag' => $flag]);

        $provider = new CachedFlagProvider(inner: $inner, cache: new ThrowingCache(), ttl: 60);
        $result = $provider->getFlags();

        Assert::array($result)->hasKeys('rt-flag');
        Assert::same($result['rt-flag']->name, 'rt-flag');
    }

    public function clearIsNonFatalWhenCacheThrows(): void
    {
        $inner = new FakeFlagProvider();

        $provider = new CachedFlagProvider(inner: $inner, cache: new ThrowingCache(), ttl: 60);
        $provider->clear();
    }

    public function implementsWritableFlagProvider(): void
    {
        $reflection = new \ReflectionClass(CachedFlagProvider::class);

        Assert::true($reflection->implementsInterface(WritableFlagProvider::class));
    }

    public function saveDelegatesToWritableInnerAndClearsCache(): void
    {
        $flag = $this->flag('saved-flag');

        $inner = new FakeWritableFlagProvider();

        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['old' => $this->flag('old')]);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $provider->save(flag: $flag);

        Assert::false($cache->has(self::CACHE_KEY));
        $saveCalls = array_filter($inner->calls, static fn(array $c): bool => $c['method'] === 'save');
        Assert::count($saveCalls, 1);
        Assert::same($saveCalls[0]['args'][0], $flag);
    }

    public function saveIsNoOpOnReadOnlyInner(): void
    {
        $flag = $this->flag('ignored');

        $inner = new FakeFlagProvider();

        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['kept' => $this->flag('kept')]);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);

        $provider->save(flag: $flag);

        Assert::true($cache->has(self::CACHE_KEY));
        Assert::count($inner->calls, 0);
    }

    public function removeDelegatesToWritableInnerAndClearsCache(): void
    {
        $inner = new FakeWritableFlagProvider();

        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['stale' => $this->flag('stale')]);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $provider->remove(name: 'stale');

        Assert::false($cache->has(self::CACHE_KEY));
        $removeCalls = array_filter($inner->calls, static fn(array $c): bool => $c['method'] === 'remove');
        Assert::count($removeCalls, 1);
        Assert::same($removeCalls[0]['args'][0], 'stale');
    }

    public function removeIsNoOpOnReadOnlyInner(): void
    {
        $inner = new FakeFlagProvider();

        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['kept' => $this->flag('kept')]);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);

        $provider->remove(name: 'ignored');

        Assert::true($cache->has(self::CACHE_KEY));
        Assert::count($inner->calls, 0);
    }

    private function flag(string $name): Flag
    {
        return (new FlagConfig(enabled: true))->toFlag(name: $name);
    }
}
