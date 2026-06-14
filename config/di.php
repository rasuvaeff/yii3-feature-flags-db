<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Rasuvaeff\Yii3FeatureFlags\FlagProvider;
use Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\CachedFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Definitions\Reference;

/** @var array $params */

return [
    FlagProvider::class => static function (
        ConnectionInterface $db,
        ContainerInterface $container,
    ) use ($params): FlagProvider {
        $config = $params['rasuvaeff/yii3-feature-flags-db'] ?? [];

        $provider = new DbFlagProvider(
            db: $db,
            table: $config['table'] ?? 'feature_flags',
        );

        $cacheConfig = $config['cache'] ?? [];

        if (($cacheConfig['enabled'] ?? false) === true) {
            return new CachedFlagProvider(
                inner: $provider,
                cache: $container->get(CacheInterface::class),
                ttl: $cacheConfig['ttl'] ?? 60,
            );
        }

        return $provider;
    },
    WritableFlagProvider::class => Reference::to(FlagProvider::class),
];
