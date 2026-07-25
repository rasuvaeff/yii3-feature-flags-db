<?php

declare(strict_types=1);

return [
    'rasuvaeff/yii3-feature-flags-db' => [
        // one source of truth: both DbFlagProvider and the bundled migration
        // read the resulting name through FeatureFlagsTableName
        'table' => 'feature_flags',
        // prepended to `table`; set it once to keep every rasuvaeff table out
        // of the way of your application's own
        'table_prefix' => '',
        'cache' => [
            'enabled' => false,
            'ttl' => 60,
        ],
    ],
];
