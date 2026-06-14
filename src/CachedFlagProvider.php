<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as CacheInvalidArgumentException;
use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlags\FlagProvider;
use Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider;

/**
 * @api
 */
final readonly class CachedFlagProvider implements WritableFlagProvider
{
    private const string CACHE_KEY = 'rasuvaeff.feature-flags.all';

    /**
     * @param int<0, max> $ttl TTL in seconds
     */
    public function __construct(
        private FlagProvider $inner,
        private CacheInterface $cache,
        private int $ttl = 60,
    ) {}

    /**
     * @return array<string, Flag>
     */
    #[\Override]
    public function getFlags(): array
    {
        try {
            /** @var array<string, Flag>|null $cached */
            $cached = $this->cache->get(key: self::CACHE_KEY);
        } catch (CacheInvalidArgumentException) {
            $cached = null;
        }

        if ($cached !== null) {
            return $cached;
        }

        $flags = $this->inner->getFlags();

        try {
            $this->cache->set(key: self::CACHE_KEY, value: $flags, ttl: $this->ttl);
        } catch (CacheInvalidArgumentException) {
            // Cache write failure is non-fatal; flags are still returned.
        }

        return $flags;
    }

    #[\Override]
    public function save(Flag $flag): void
    {
        if ($this->inner instanceof WritableFlagProvider) {
            $this->inner->save($flag);
            $this->clear();
        }
    }

    #[\Override]
    public function remove(string $name): void
    {
        if ($this->inner instanceof WritableFlagProvider) {
            $this->inner->remove($name);
            $this->clear();
        }
    }

    public function clear(): void
    {
        try {
            $this->cache->delete(key: self::CACHE_KEY);
        } catch (CacheInvalidArgumentException) {
            // Cache clear failure is non-fatal.
        }
    }
}
