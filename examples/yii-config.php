<?php

declare(strict_types=1);

/**
 * Yii3 config-plugin integration example.
 *
 * In a real Yii3 application `config/params.php` and `config/di.php` are merged
 * automatically by yiisoft/config, and `FlagProvider` is resolved from the DI
 * container. This script shows the same wiring manually against an in-memory DB.
 *
 * The package ships:
 *   - config/params.php — defaults under the `rasuvaeff/yii3-feature-flags-db` key
 *   - config/di.php      — binds FlagProvider to DbFlagProvider (optionally cached)
 */

use Rasuvaeff\Yii3FeatureFlags\FeatureFlags;
use Rasuvaeff\Yii3FeatureFlags\FlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\CachedFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider;
use Yiisoft\Db\Connection\ConnectionInterface;

/** @var ConnectionInterface $db */
$db = require __DIR__ . '/bootstrap.php';

// Application config — override defaults in your app's config/params.php:
$params = [
    'rasuvaeff/yii3-feature-flags-db' => [
        'table' => 'feature_flags',
        'cache' => [
            'enabled' => true,
            'ttl' => 60,
        ],
    ],
];

// What config/di.php does (FlagProvider factory), expressed inline.
// $db / the PSR-16 cache come from the container; here from bootstrap.
$config = $params['rasuvaeff/yii3-feature-flags-db'];

$provider = new DbFlagProvider(db: $db, table: $config['table']);

if (($config['cache']['enabled'] ?? false) === true) {
    // In the container the PSR-16 CacheInterface is resolved lazily, only when enabled.
    $psr16 = require __DIR__ . '/psr16-null-cache.php';
    $provider = new CachedFlagProvider(inner: $provider, cache: $psr16, ttl: $config['cache']['ttl']);
}

// FlagProvider::class is now bound to $provider in the container.
$flagProvider = $provider;
assert($flagProvider instanceof FlagProvider);

$featureFlags = new FeatureFlags(provider: $flagProvider);

echo 'Provider: ' . $flagProvider::class . "\n";
echo 'new-checkout enabled: ' . ($featureFlags->isEnabled('new-checkout') ? 'true' : 'false') . "\n";
echo 'dark-mode enabled: ' . ($featureFlags->isEnabled('dark-mode') ? 'true' : 'false') . "\n";

$db->close();
