<?php

declare(strict_types=1);

use Rasuvaeff\Yii3FeatureFlags\FeatureFlags;
use Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider;
use Yiisoft\Db\Connection\ConnectionInterface;

/** @var ConnectionInterface $db */
$db = require __DIR__ . '/bootstrap.php';

$provider = new DbFlagProvider(db: $db);
$featureFlags = new FeatureFlags(provider: $provider);

echo 'new-checkout enabled: ' . ($featureFlags->isEnabled('new-checkout') ? 'true' : 'false') . "\n";
echo 'dark-mode enabled: ' . ($featureFlags->isEnabled('dark-mode') ? 'true' : 'false') . "\n";
echo 'unknown-flag enabled: ' . ($featureFlags->isEnabled('unknown-flag') ? 'true' : 'false') . "\n";

$db->close();
