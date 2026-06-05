<?php

declare(strict_types=1);

return [
    'rasuvaeff/yii3-feature-flags-db' => [
        'table' => 'feature_flags',
        'cache' => [
            'enabled' => false,
            'ttl' => 60,
        ],
    ],
];
