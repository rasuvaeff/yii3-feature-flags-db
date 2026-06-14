<?php

declare(strict_types=1);

use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider;
use Yiisoft\Db\Connection\ConnectionInterface;

/** @var ConnectionInterface $db */
$db = require __DIR__ . '/bootstrap.php';

/** @var WritableFlagProvider $provider */
$provider = new DbFlagProvider(db: $db);

$provider->save(flag: new Flag(
    name: 'admin-panel',
    enabled: true,
    rollout: 25,
    environments: ['production'],
));

echo "After save():\n";
foreach ($provider->getFlags() as $flag) {
    echo "  - {$flag->name}: enabled=" . ($flag->enabled ? 'true' : 'false') . ", rollout={$flag->rollout}\n";
}

$provider->save(flag: new Flag(name: 'admin-panel', enabled: false));
echo "\nAfter update (disabled):\n";
echo '  admin-panel enabled: ' . ($provider->getFlags()['admin-panel']->enabled ? 'true' : 'false') . "\n";

$provider->remove(name: 'admin-panel');
echo "\nAfter remove():\n";
echo '  has admin-panel: ' . (isset($provider->getFlags()['admin-panel']) ? 'true' : 'false') . "\n";

$db->close();
