<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests;

use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider;

/**
 * @internal
 */
final class FakeWritableFlagProvider implements WritableFlagProvider
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

    #[\Override]
    public function save(Flag $flag): void
    {
        $this->calls[] = ['method' => 'save', 'args' => [$flag]];
    }

    #[\Override]
    public function remove(string $name): void
    {
        $this->calls[] = ['method' => 'remove', 'args' => [$name]];
    }
}
