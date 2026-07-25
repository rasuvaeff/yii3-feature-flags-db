<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Rasuvaeff\Yii3FeatureFlags\FlagProvider;
use Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\CachedFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\FeatureFlagsTableName;
use Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Definitions\Reference;

/** @var array $params */

return [
    // the migration resolves this by type through Injector::make(), so the
    // provider and the migration can never disagree about the table
    FeatureFlagsTableName::class => static function () use ($params): FeatureFlagsTableName {
        $config = $params['rasuvaeff/yii3-feature-flags-db'] ?? [];

        return new FeatureFlagsTableName(
            ((string) ($config['table_prefix'] ?? '')) . ((string) ($config['table'] ?? 'feature_flags')),
        );
    },
    FlagProvider::class => static function (
        ConnectionInterface $db,
        ContainerInterface $container,
        FeatureFlagsTableName $table,
    ) use ($params): FlagProvider {
        $config = $params['rasuvaeff/yii3-feature-flags-db'] ?? [];

        $provider = new DbFlagProvider(db: $db, table: $table->value);

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
