<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests;

use Rasuvaeff\Understudy\Understudy;
use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;
use Yiisoft\Db\Command\CommandInterface;
use Yiisoft\Db\Connection\ConnectionInterface;

use function Rasuvaeff\Understudy\verify;
use function Rasuvaeff\Understudy\when;

#[Test]
#[Covers(DbFlagProvider::class)]
final class DbFlagProviderWriteTest
{
    /**
     * @return array{CommandInterface, ConnectionInterface}
     */
    private function db(): array
    {
        $command = Understudy::for(CommandInterface::class);
        $db = Understudy::for(ConnectionInterface::class);
        when(fn() => $db->createCommand())->returns($command);

        return [$command, $db];
    }

    public function implementsWritableFlagProvider(): void
    {
        $reflection = new \ReflectionClass(DbFlagProvider::class);

        Assert::true($reflection->implementsInterface(WritableFlagProvider::class));
    }

    public function saveCallsUpsertWithSerializedRow(): void
    {
        [$command, $db] = $this->db();

        $provider = new DbFlagProvider(db: $db, table: 'feature_flags');
        $provider->save(flag: new Flag(
            name: 'new-checkout',
            enabled: true,
            salt: 'checkout-v1',
            rollout: 25,
            killSwitch: false,
            environments: ['production', 'staging'],
        ));

        verify(fn() => $command->upsert('feature_flags', [
            'name' => 'new-checkout',
            'enabled' => true,
            'salt' => 'checkout-v1',
            'rollout' => 25,
            'kill_switch' => false,
            'environments' => '["production","staging"]',
        ]), times: 1);
        verify(fn() => $command->execute(), times: 1);
    }

    public function saveWritesEmptySaltWhenItMatchesName(): void
    {
        [$command, $db] = $this->db();

        $provider = new DbFlagProvider(db: $db);
        $provider->save(flag: new Flag(name: 'my-flag'));

        verify(fn() => $command->upsert('feature_flags', [
            'name' => 'my-flag',
            'enabled' => true,
            'salt' => '',
            'rollout' => 100,
            'kill_switch' => false,
            'environments' => '[]',
        ]), times: 1);
    }

    public function saveKeepsCustomSalt(): void
    {
        [$command, $db] = $this->db();

        $provider = new DbFlagProvider(db: $db);
        $provider->save(flag: new Flag(name: 'my-flag', salt: 'custom'));

        verify(fn() => $command->upsert('feature_flags', [
            'name' => 'my-flag',
            'enabled' => true,
            'salt' => 'custom',
            'rollout' => 100,
            'kill_switch' => false,
            'environments' => '[]',
        ]), times: 1);
    }

    public function saveEncodesEmptyEnvironmentsAsJsonArray(): void
    {
        [$command, $db] = $this->db();

        $provider = new DbFlagProvider(db: $db);
        $provider->save(flag: new Flag(name: 'my-flag'));

        verify(fn() => $command->upsert('feature_flags', [
            'name' => 'my-flag',
            'enabled' => true,
            'salt' => '',
            'rollout' => 100,
            'kill_switch' => false,
            'environments' => '[]',
        ]), times: 1);
    }

    public function removeCallsDeleteWithNameCondition(): void
    {
        [$command, $db] = $this->db();

        $provider = new DbFlagProvider(db: $db, table: 'feature_flags');
        $provider->remove(name: 'stale-flag');

        verify(fn() => $command->delete('feature_flags', ['name' => 'stale-flag']), times: 1);
        verify(fn() => $command->execute(), times: 1);
    }
}
