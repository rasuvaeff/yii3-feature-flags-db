<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests\Integration;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\PropertyTesting\StateMachine\CommandSequence;
use Rasuvaeff\PropertyTesting\StateMachine\StateMachine;
use Rasuvaeff\Yii3FeatureFlagsDb\Tests\Integration\Support\FlagStoreHarness;
use Rasuvaeff\Yii3FeatureFlagsDb\Tests\Integration\Support\RemoveCommand;
use Rasuvaeff\Yii3FeatureFlagsDb\Tests\Integration\Support\SaveCommand;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;

/**
 * Model-based test over a real in-memory SQLite database: under any interleaving
 * of save (upsert) and remove, {@see \Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider::getFlags()}
 * reflects a simple model (the enabled state of each flag name, or absent),
 * exercising upsert-vs-insert, remove-then-save and remove-missing interactions
 * the isolated integration cases do not combine.
 */
#[Test]
#[CoversNothing]
final class DbFlagProviderStatefulTest
{
    #[Property(runs: 100)]
    public function saveAndRemoveTrackTheModel(CommandSequence $sequence): void
    {
        $harness = new FlagStoreHarness(3);

        StateMachine::check($sequence, static fn(): FlagStoreHarness => $harness);

        // getFlags() returns exactly the present flags — no phantom rows.
        $present = count(array_filter($harness->snapshot(3), static fn(?bool $enabled): bool => $enabled !== null));
        Assert::same($harness->totalFlags(), $present);
    }

    /** @return array<string, ArbitraryInterface> */
    private function saveAndRemoveTrackTheModelGenerators(): array
    {
        return ['sequence' => Gen::commands([null, null, null], [
            Gen::map(
                Gen::tuple(Gen::intBetween(0, 2), Gen::bool()),
                static fn(array $pair): SaveCommand => new SaveCommand($pair[0], $pair[1]),
            ),
            Gen::map(Gen::intBetween(0, 2), static fn(int $index): RemoveCommand => new RemoveCommand($index)),
        ])];
    }
}
