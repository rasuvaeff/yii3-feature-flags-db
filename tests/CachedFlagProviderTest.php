<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Rasuvaeff\Yii3FeatureFlags\FlagConfig;
use Rasuvaeff\Yii3FeatureFlags\FlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\CachedFlagProvider;

#[CoversClass(CachedFlagProvider::class)]
final class CachedFlagProviderTest extends TestCase
{
    #[Test]
    public function loadsFromInnerOnCacheMiss(): void
    {
        $flag = (new FlagConfig(enabled: true))->toFlag(name: 'test-flag');
        $inner = $this->createStub(FlagProvider::class);
        $inner->method('getFlags')->willReturn(['test-flag' => $flag]);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())->method('set')->with(
            key: 'rasuvaeff:feature-flags:all',
            value: ['test-flag' => $flag],
            ttl: 60,
        );

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $flags = $provider->getFlags();

        $this->assertSame(['test-flag' => $flag], $flags);
    }

    #[Test]
    public function returnsCachedOnHit(): void
    {
        $flag = (new FlagConfig(enabled: true))->toFlag(name: 'cached-flag');
        $inner = $this->createStub(FlagProvider::class);
        $inner->method('getFlags')->willReturn(['cached-flag' => $flag]);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(['cached-flag' => $flag]);
        $cache->expects($this->never())->method('set');

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $flags = $provider->getFlags();

        $this->assertSame(['cached-flag' => $flag], $flags);
    }

    #[Test]
    public function callsInnerOnlyOnceOnRepeatedMiss(): void
    {
        $flag = (new FlagConfig(enabled: true))->toFlag(name: 'test-flag');
        $inner = $this->createMock(FlagProvider::class);
        $inner->expects($this->exactly(2))->method('getFlags')->willReturn(['test-flag' => $flag]);

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->method('set')->willReturn(true);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);

        $provider->getFlags();
        $provider->getFlags();
    }

    #[Test]
    public function clearsCacheKey(): void
    {
        $inner = $this->createStub(FlagProvider::class);
        $inner->method('getFlags')->willReturn([]);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('delete')->with(
            key: 'rasuvaeff:feature-flags:all',
        )->willReturn(true);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $provider->clear();
    }

    #[Test]
    public function returnsEmptyFlagsFromInner(): void
    {
        $inner = $this->createStub(FlagProvider::class);
        $inner->method('getFlags')->willReturn([]);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())->method('set')->with(
            key: 'rasuvaeff:feature-flags:all',
            value: [],
            ttl: 60,
        );

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);
        $this->assertSame([], $provider->getFlags());
    }

    #[Test]
    public function usesDefaultTtlOfSixtySeconds(): void
    {
        $inner = $this->createStub(FlagProvider::class);
        $inner->method('getFlags')->willReturn([]);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())->method('set')->with(
            key: 'rasuvaeff:feature-flags:all',
            value: [],
            ttl: 60,
        );

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache);
        $provider->getFlags();
    }

    #[Test]
    public function returnsAllCachedFlagsOnHit(): void
    {
        $flags = [
            'flag-a' => (new FlagConfig(enabled: true))->toFlag(name: 'flag-a'),
            'flag-b' => (new FlagConfig(enabled: false))->toFlag(name: 'flag-b'),
        ];
        $inner = $this->createStub(FlagProvider::class);
        $inner->method('getFlags')->willReturn([]);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn($flags);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);

        $this->assertSame($flags, $provider->getFlags());
    }

    #[Test]
    public function returnsAllFlagsFromInnerOnMiss(): void
    {
        $flags = [
            'flag-a' => (new FlagConfig(enabled: true))->toFlag(name: 'flag-a'),
            'flag-b' => (new FlagConfig(enabled: false))->toFlag(name: 'flag-b'),
        ];
        $inner = $this->createStub(FlagProvider::class);
        $inner->method('getFlags')->willReturn($flags);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);

        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);

        $this->assertSame($flags, $provider->getFlags());
    }

    #[Test]
    public function roundTripStoresThenServesFromCache(): void
    {
        $flag = (new FlagConfig(enabled: true))->toFlag(name: 'rt-flag');
        $inner = $this->createMock(FlagProvider::class);
        $inner->expects($this->once())->method('getFlags')->willReturn(['rt-flag' => $flag]);

        $cache = new ArrayCache();
        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);

        $first = $provider->getFlags();
        $second = $provider->getFlags();

        $this->assertSame(['rt-flag' => $flag], $first);
        $this->assertSame(['rt-flag' => $flag], $second);
        $this->assertSame(1, $cache->setCalls);
        $this->assertSame(2, $cache->getCalls);
    }

    #[Test]
    public function clearForcesReloadFromInner(): void
    {
        $flag = (new FlagConfig(enabled: true))->toFlag(name: 'rt-flag');
        $inner = $this->createMock(FlagProvider::class);
        $inner->expects($this->exactly(2))->method('getFlags')->willReturn(['rt-flag' => $flag]);

        $cache = new ArrayCache();
        $provider = new CachedFlagProvider(inner: $inner, cache: $cache, ttl: 60);

        $provider->getFlags();
        $provider->clear();
        $provider->getFlags();
    }

    #[Test]
    public function fallsBackToInnerWhenCacheReadAndWriteFail(): void
    {
        $flag = (new FlagConfig(enabled: true))->toFlag(name: 'rt-flag');
        $inner = $this->createStub(FlagProvider::class);
        $inner->method('getFlags')->willReturn(['rt-flag' => $flag]);

        $provider = new CachedFlagProvider(inner: $inner, cache: new ThrowingCache(), ttl: 60);

        $this->assertSame(['rt-flag' => $flag], $provider->getFlags());
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
}
