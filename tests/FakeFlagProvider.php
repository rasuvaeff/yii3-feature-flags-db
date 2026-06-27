<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests;

use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlags\FlagProvider;

/**
 * @internal
 */
final class FakeFlagProvider implements FlagProvider
{
    /** @var list<array{method: string, args: array}> */
    public array $calls = [];

    /** @var array<string, Flag> */
    private array $flags;

    /** @param array<string, Flag> $flags */
    public function __construct(array $flags = [])
    {
        $this->flags = $flags;
    }

    #[\Override]
    public function getFlags(): array
    {
        $this->calls[] = ['method' => 'getFlags', 'args' => []];

        return $this->flags;
    }
}
