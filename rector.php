<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\CodeQuality\Rector\Catch_\ThrowWithPreviousExceptionRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php83: true)
    ->withPreparedSets(deadCode: true, codeQuality: true)
    ->withSkip([
        // Contract violations carry no meaningful error code; rule forces
        // `code: $e->getCode()` which is always 0 for InvalidArgumentException,
        // and breaks stylistic consistency with the other throws in FlagRowMapper.
        ThrowWithPreviousExceptionRector::class,
    ]);
