<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests;

use Psr\SimpleCache\CacheInterface;
use Rasuvaeff\Understudy\Arg;
use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlags\FlagConfig;
use Rasuvaeff\Yii3FeatureFlags\FlagProvider;
use Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\CachedFlagProvider;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

use function Rasuvaeff\Understudy\verify;
use function Rasuvaeff\Understudy\when;

#[Test]
#[Covers(CachedFlagProvider::class)]
final class CachedFlagProviderTest
{
    private const string CACHE_KEY = 'rasuvaeff.feature-flags.all';

    public function loadsFromInnerOnMissAndStoresWithKeyAndDefaultTtl(): void
    {
        $flags = ['test-flag' => $this->flag('test-flag')];
        $inner = Understudy::for(FlagProvider::class);
        when(fn() => $inner->getFlags())->returns($flags);

        $cache = Understudy::for(CacheInterface::class);
        $provider = new CachedFlagProvider(inner: $inner, cache: $cache);
        $result = $provider->getFlags();

        Assert::array($result)->hasKeys('test-flag');
        Assert::same($result['test-flag']->name, 'test-flag');

        verify(fn() => $cache->get(self::CACHE_KEY), times: 1);
        verify(fn() => $cache->set(self::CACHE_KEY, $flags, 60), times: 1);
    }

    public function passesConfiguredTtlToCache(): void
    {
        $inner = Understudy::for(FlagProvider::class);
        when(fn() => $inner->getFlags())->returns([]);

        $cache = Understudy::for(CacheInterface::class);
        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 120);
        $provider->getFlags();

        verify(fn() => $cache->set(self::CACHE_KEY, [], 120), times: 1);
    }

    public function returnsCachedWithoutCallingInnerOnHit(): void
    {
        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['flag-a' => $this->flag('flag-a'), 'flag-b' => $this->flag('flag-b')]);

        $inner = Understudy::for(FlagProvider::class);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $result = $provider->getFlags();

        Assert::count($result, 2);
        Assert::array($result)->hasKeys('flag-a', 'flag-b');
        Understudy::unused($inner);
    }

    public function roundTripServesSecondCallFromCache(): void
    {
        $flags = ['flag-a' => $this->flag('flag-a'), 'flag-b' => $this->flag('flag-b')];
        $inner = Understudy::for(FlagProvider::class);
        when(fn() => $inner->getFlags())->returns($flags);

        $provider = new CachedFlagProvider(inner: $inner, cache: new MemorySimpleCache(), ttl: 60);

        $first = $provider->getFlags();
        $second = $provider->getFlags();

        Assert::count($first, 2);
        Assert::count($second, 2);
        Assert::array($first)->hasKeys('flag-b');
        Assert::array($second)->hasKeys('flag-b');
        verify(fn() => $inner->getFlags(), times: 1);
    }

    public function clearRemovesCachedKey(): void
    {
        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['flag-a' => $this->flag('flag-a')]);

        $inner = Understudy::for(FlagProvider::class);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $provider->clear();

        Assert::false($cache->has(self::CACHE_KEY));
        Understudy::unused($inner);
    }

    public function clearForcesReloadFromInner(): void
    {
        $inner = Understudy::for(FlagProvider::class);
        when(fn() => $inner->getFlags())->returns(['rt-flag' => $this->flag('rt-flag')]);

        $provider = new CachedFlagProvider(inner: $inner, cache: new MemorySimpleCache(), ttl: 60);

        $provider->getFlags();
        $provider->clear();
        $provider->getFlags();

        verify(fn() => $inner->getFlags(), times: 2);
    }

    public function fallsBackToInnerWhenCacheReadAndWriteFail(): void
    {
        $flags = ['rt-flag' => $this->flag('rt-flag')];
        $inner = Understudy::for(FlagProvider::class);
        when(fn() => $inner->getFlags())->returns($flags);

        $cache = Understudy::for(CacheInterface::class);
        when(fn() => $cache->get(Arg::any()))->throws(new InvalidCacheKeyException('boom'));
        when(fn() => $cache->set(Arg::any(), Arg::any(), Arg::any()))->throws(new InvalidCacheKeyException('boom'));

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $result = $provider->getFlags();

        Assert::array($result)->hasKeys('rt-flag');
        Assert::same($result['rt-flag']->name, 'rt-flag');
        verify(fn() => $cache->set(self::CACHE_KEY, $flags, 60), times: 1);
    }

    public function clearIsNonFatalWhenCacheThrows(): void
    {
        $inner = Understudy::for(FlagProvider::class);

        $cache = Understudy::for(CacheInterface::class);
        when(fn() => $cache->delete(self::CACHE_KEY))->throws(new InvalidCacheKeyException('boom'));

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $provider->clear();

        verify(fn() => $cache->delete(self::CACHE_KEY), times: 1);
        Understudy::unused($inner);
    }

    public function implementsWritableFlagProvider(): void
    {
        $reflection = new \ReflectionClass(CachedFlagProvider::class);

        Assert::true($reflection->implementsInterface(WritableFlagProvider::class));
    }

    public function saveDelegatesToWritableInnerAndClearsCache(): void
    {
        $flag = $this->flag('saved-flag');

        $inner = Understudy::for(WritableFlagProvider::class);

        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['old' => $this->flag('old')]);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $provider->save(flag: $flag);

        Assert::false($cache->has(self::CACHE_KEY));
        verify(fn() => $inner->save($flag), times: 1);
    }

    public function saveIsNoOpOnReadOnlyInner(): void
    {
        $inner = Understudy::for(FlagProvider::class);

        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['kept' => $this->flag('kept')]);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);

        $provider->save(flag: $this->flag('ignored'));

        Assert::true($cache->has(self::CACHE_KEY));
        Understudy::unused($inner);
    }

    public function removeDelegatesToWritableInnerAndClearsCache(): void
    {
        $inner = Understudy::for(WritableFlagProvider::class);

        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['stale' => $this->flag('stale')]);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $provider->remove(name: 'stale');

        Assert::false($cache->has(self::CACHE_KEY));
        verify(fn() => $inner->remove('stale'), times: 1);
    }

    public function removeIsNoOpOnReadOnlyInner(): void
    {
        $inner = Understudy::for(FlagProvider::class);

        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['kept' => $this->flag('kept')]);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);

        $provider->remove(name: 'ignored');

        Assert::true($cache->has(self::CACHE_KEY));
        Understudy::unused($inner);
    }

    private function flag(string $name): Flag
    {
        return (new FlagConfig(enabled: true))->toFlag(name: $name);
    }
}
