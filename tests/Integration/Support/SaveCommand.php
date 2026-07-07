<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests\Integration\Support;

use Rasuvaeff\PropertyTesting\StateMachine\Command;

/**
 * Model-based command: save (upsert) the flag at $index with the given enabled
 * state. The model is `list<?bool>` (null = absent) over the flag names.
 */
final readonly class SaveCommand implements Command
{
    public function __construct(
        private int $index,
        private bool $enabled,
    ) {}

    #[\Override]
    public function preCondition(mixed $model): bool
    {
        return true;
    }

    #[\Override]
    public function nextState(mixed $model): mixed
    {
        \assert(is_array($model));

        $model[$this->index] = $this->enabled;

        return $model;
    }

    #[\Override]
    public function run(mixed $model, mixed $system): mixed
    {
        \assert($system instanceof FlagStoreHarness && is_array($model));

        $system->save($this->index, $this->enabled);

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
        return 'Save(' . $this->index . ', ' . ($this->enabled ? 'on' : 'off') . ')';
    }
}
