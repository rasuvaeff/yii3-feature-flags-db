<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlags\FlagConfig;
use Rasuvaeff\Yii3FeatureFlags\FlagProvider;
use Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\CachedFlagProvider;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

#[CoversClass(CachedFlagProvider::class)]
final class CachedFlagProviderTest extends TestCase
{
    private const string CACHE_KEY = 'rasuvaeff.feature-flags.all';

    #[Test]
    public function loadsFromInnerOnMissAndStoresWithKeyAndDefaultTtl(): void
    {
        $flag = $this->flag('test-flag');
        $inner = $this->createStub(FlagProvider::class);
        $inner->method('getFlags')->willReturn(['test-flag' => $flag]);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())->method('set')->with(
            key: self::CACHE_KEY,
            value: ['test-flag' => $flag],
            ttl: 60,
        );

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache);
        $result = $provider->getFlags();

        $this->assertArrayHasKey('test-flag', $result);
        $this->assertSame('test-flag', $result['test-flag']->name);
    }

    #[Test]
    public function passesConfiguredTtlToCache(): void
    {
        $inner = $this->createStub(FlagProvider::class);
        $inner->method('getFlags')->willReturn([]);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())->method('set')->with(
            key: self::CACHE_KEY,
            value: [],
            ttl: 120,
        );

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 120);
        $provider->getFlags();
    }

    #[Test]
    public function returnsCachedWithoutCallingInnerOnHit(): void
    {
        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['flag-a' => $this->flag('flag-a'), 'flag-b' => $this->flag('flag-b')]);

        $inner = $this->createMock(FlagProvider::class);
        $inner->expects($this->never())->method('getFlags');

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $result = $provider->getFlags();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('flag-a', $result);
        $this->assertArrayHasKey('flag-b', $result);
    }

    #[Test]
    public function roundTripServesSecondCallFromCache(): void
    {
        $flags = ['flag-a' => $this->flag('flag-a'), 'flag-b' => $this->flag('flag-b')];
        $inner = $this->createMock(FlagProvider::class);
        $inner->expects($this->once())->method('getFlags')->willReturn($flags);

        $provider = new CachedFlagProvider(inner: $inner, cache: new MemorySimpleCache(), ttl: 60);

        $first = $provider->getFlags();
        $second = $provider->getFlags();

        $this->assertCount(2, $first);
        $this->assertCount(2, $second);
        $this->assertArrayHasKey('flag-b', $first);
        $this->assertArrayHasKey('flag-b', $second);
    }

    #[Test]
    public function clearRemovesCachedKey(): void
    {
        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['flag-a' => $this->flag('flag-a')]);

        $inner = $this->createStub(FlagProvider::class);
        $inner->method('getFlags')->willReturn([]);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $provider->clear();

        $this->assertFalse($cache->has(self::CACHE_KEY));
    }

    #[Test]
    public function clearForcesReloadFromInner(): void
    {
        $flag = $this->flag('rt-flag');
        $inner = $this->createMock(FlagProvider::class);
        $inner->expects($this->exactly(2))->method('getFlags')->willReturn(['rt-flag' => $flag]);

        $provider = new CachedFlagProvider(inner: $inner, cache: new MemorySimpleCache(), ttl: 60);

        $provider->getFlags();
        $provider->clear();
        $provider->getFlags();
    }

    #[Test]
    public function fallsBackToInnerWhenCacheReadAndWriteFail(): void
    {
        $flag = $this->flag('rt-flag');
        $inner = $this->createStub(FlagProvider::class);
        $inner->method('getFlags')->willReturn(['rt-flag' => $flag]);

        $provider = new CachedFlagProvider(inner: $inner, cache: new ThrowingCache(), ttl: 60);
        $result = $provider->getFlags();

        $this->assertArrayHasKey('rt-flag', $result);
        $this->assertSame('rt-flag', $result['rt-flag']->name);
    }

    #[Test]
    public function clearIsNonFatalWhenCacheThrows(): void
    {
        $inner = $this->createStub(FlagProvider::class);
        $inner->method('getFlags')->willReturn([]);

        $provider = new CachedFlagProvider(inner: $inner, cache: new ThrowingCache(), ttl: 60);

        $this->expectNotToPerformAssertions();

        $provider->clear();
    }

    #[Test]
    public function implementsWritableFlagProvider(): void
    {
        $reflection = new \ReflectionClass(CachedFlagProvider::class);

        $this->assertTrue($reflection->implementsInterface(WritableFlagProvider::class));
    }

    #[Test]
    public function saveDelegatesToWritableInnerAndClearsCache(): void
    {
        $flag = $this->flag('saved-flag');

        $inner = $this->createMock(WritableFlagProvider::class);
        $inner->expects($this->once())->method('save')->with($flag);
        $inner->method('getFlags')->willReturn([]);

        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['old' => $this->flag('old')]);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $provider->save(flag: $flag);

        $this->assertFalse($cache->has(self::CACHE_KEY));
    }

    #[Test]
    public function saveIsNoOpOnReadOnlyInner(): void
    {
        $flag = $this->flag('ignored');

        $inner = $this->createMock(FlagProvider::class);
        $inner->expects($this->never())->method($this->anything());

        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['kept' => $this->flag('kept')]);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);

        $provider->save(flag: $flag);

        $this->assertTrue($cache->has(self::CACHE_KEY));
    }

    #[Test]
    public function removeDelegatesToWritableInnerAndClearsCache(): void
    {
        $inner = $this->createMock(WritableFlagProvider::class);
        $inner->expects($this->once())->method('remove')->with('stale');
        $inner->method('getFlags')->willReturn([]);

        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['stale' => $this->flag('stale')]);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $provider->remove(name: 'stale');

        $this->assertFalse($cache->has(self::CACHE_KEY));
    }

    #[Test]
    public function removeIsNoOpOnReadOnlyInner(): void
    {
        $inner = $this->createMock(FlagProvider::class);
        $inner->expects($this->never())->method($this->anything());

        $cache = new MemorySimpleCache();
        $cache->set(self::CACHE_KEY, ['kept' => $this->flag('kept')]);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);

        $provider->remove(name: 'ignored');

        $this->assertTrue($cache->has(self::CACHE_KEY));
    }

    private function flag(string $name): Flag
    {
        return (new FlagConfig(enabled: true))->toFlag(name: $name);
    }
}
