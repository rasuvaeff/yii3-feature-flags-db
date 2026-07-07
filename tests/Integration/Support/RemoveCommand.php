<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests\Integration\Support;

use Rasuvaeff\PropertyTesting\StateMachine\Command;

/**
 * Model-based command: remove the flag at $index (a no-op when absent). The
 * model is `list<?bool>` (null = absent) over the flag names.
 */
final readonly class RemoveCommand implements Command
{
    public function __construct(private int $index) {}

    #[\Override]
    public function preCondition(mixed $model): bool
    {
        return true;
    }

    #[\Override]
    public function nextState(mixed $model): mixed
    {
        \assert(is_array($model));

        $model[$this->index] = null;

        return $model;
    }

    #[\Override]
    public function run(mixed $model, mixed $system): mixed
    {
        \assert($system instanceof FlagStoreHarness && is_array($model));

        $system->remove($this->index);

        return $system->snapshot(count($model));
    }

    #[\Override]
    public function postCondition(mixed $model, mixed $result): bool
    {
        return $result === $this->nextState($model);
    }

    #[\Override]
    public function __toString(): string
    {
        return 'Remove(' . $this->index . ')';
    }
}
