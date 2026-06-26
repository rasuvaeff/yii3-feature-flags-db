<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests;

use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(DbFlagProvider::class)]
final class DbFlagProviderWriteTest
{
    public function implementsWritableFlagProvider(): void
    {
        $reflection = new \ReflectionClass(DbFlagProvider::class);

        Assert::true($reflection->implementsInterface(WritableFlagProvider::class));
    }

    public function saveCallsUpsertWithSerializedRow(): void
    {
        $command = new FakeCommand();
        $db = new FakeConnection(command: $command);

        $provider = new DbFlagProvider(db: $db, table: 'feature_flags');
        $provider->save(flag: new Flag(
            name: 'new-checkout',
            enabled: true,
            salt: 'checkout-v1',
            rollout: 25,
            killSwitch: false,
            environments: ['production', 'staging'],
        ));

        Assert::notNull($command->upsertCapture);
        Assert::same($command->upsertCapture['table'], 'feature_flags');
        Assert::same(
            $command->upsertCapture['columns'],
            [
                'name' => 'new-checkout',
                'enabled' => true,
                'salt' => 'checkout-v1',
                'rollout' => 25,
                'kill_switch' => false,
                'environments' => '["production","staging"]',
            ],
        );
    }

    public function saveWritesEmptySaltWhenItMatchesName(): void
    {
        $command = new FakeCommand();
        $db = new FakeConnection(command: $command);

        $provider = new DbFlagProvider(db: $db);
        $provider->save(flag: new Flag(name: 'my-flag'));

        Assert::notNull($command->upsertCapture);
        Assert::same($command->upsertCapture['columns']['salt'], '');
    }

    public function saveKeepsCustomSalt(): void
    {
        $command = new FakeCommand();
        $db = new FakeConnection(command: $command);

        $provider = new DbFlagProvider(db: $db);
        $provider->save(flag: new Flag(name: 'my-flag', salt: 'custom'));

        Assert::notNull($command->upsertCapture);
        Assert::same($command->upsertCapture['columns']['salt'], 'custom');
    }

    public function saveEncodesEmptyEnvironmentsAsJsonArray(): void
    {
        $command = new FakeCommand();
        $db = new FakeConnection(command: $command);

        $provider = new DbFlagProvider(db: $db);
        $provider->save(flag: new Flag(name: 'my-flag'));

        Assert::notNull($command->upsertCapture);
        Assert::same($command->upsertCapture['columns']['environments'], '[]');
    }

    public function removeCallsDeleteWithNameCondition(): void
    {
        $command = new FakeCommand();
        $db = new FakeConnection(command: $command);

        $provider = new DbFlagProvider(db: $db, table: 'feature_flags');
        $provider->remove(name: 'stale-flag');

        Assert::notNull($command->deleteCapture);
        Assert::same($command->deleteCapture['table'], 'feature_flags');
        Assert::same($command->deleteCapture['condition'], ['name' => 'stale-flag']);
    }
}
