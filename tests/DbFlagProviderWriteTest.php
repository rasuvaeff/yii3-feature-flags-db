<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider;
use Yiisoft\Db\Command\CommandInterface;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * @phpstan-type UpsertCapture array{db: ConnectionInterface, table: ?string, columns: array<string, scalar|null>}
 */
#[CoversClass(DbFlagProvider::class)]
final class DbFlagProviderWriteTest extends TestCase
{
    #[Test]
    public function implementsWritableFlagProvider(): void
    {
        $reflection = new \ReflectionClass(DbFlagProvider::class);

        $this->assertTrue($reflection->implementsInterface(WritableFlagProvider::class));
    }

    #[Test]
    public function saveCallsUpsertWithSerializedRow(): void
    {
        $captured = ['table' => null, 'columns' => []];
        $db = $this->mockDbWithUpsertCapture($captured);

        $provider = new DbFlagProvider(db: $db, table: 'feature_flags');
        $provider->save(flag: new Flag(
            name: 'new-checkout',
            enabled: true,
            salt: 'checkout-v1',
            rollout: 25,
            killSwitch: false,
            environments: ['production', 'staging'],
        ));

        $this->assertSame('feature_flags', $captured['table']);
        $this->assertSame(
            [
                'name' => 'new-checkout',
                'enabled' => true,
                'salt' => 'checkout-v1',
                'rollout' => 25,
                'kill_switch' => false,
                'environments' => '["production","staging"]',
            ],
            $captured['columns'],
        );
    }

    #[Test]
    public function saveWritesEmptySaltWhenItMatchesName(): void
    {
        $captured = ['table' => null, 'columns' => []];
        $db = $this->mockDbWithUpsertCapture($captured);

        $provider = new DbFlagProvider(db: $db);
        $provider->save(flag: new Flag(name: 'my-flag'));

        $this->assertSame('', $captured['columns']['salt']);
    }

    #[Test]
    public function saveKeepsCustomSalt(): void
    {
        $captured = ['table' => null, 'columns' => []];
        $db = $this->mockDbWithUpsertCapture($captured);

        $provider = new DbFlagProvider(db: $db);
        $provider->save(flag: new Flag(name: 'my-flag', salt: 'custom'));

        $this->assertSame('custom', $captured['columns']['salt']);
    }

    #[Test]
    public function saveEncodesEmptyEnvironmentsAsJsonArray(): void
    {
        $captured = ['table' => null, 'columns' => []];
        $db = $this->mockDbWithUpsertCapture($captured);

        $provider = new DbFlagProvider(db: $db);
        $provider->save(flag: new Flag(name: 'my-flag'));

        $this->assertSame('[]', $captured['columns']['environments']);
    }

    #[Test]
    public function removeCallsDeleteWithNameCondition(): void
    {
        $captured = ['table' => null, 'condition' => []];

        $command = $this->createMock(CommandInterface::class);
        $command->expects($this->once())
            ->method('delete')
            ->willReturnCallback(function (string $table, array $condition) use (&$captured, $command) {
                $captured['table'] = $table;
                $captured['condition'] = $condition;

                return $command;
            });
        $command->expects($this->once())->method('execute');

        $db = $this->createMock(ConnectionInterface::class);
        $db->expects($this->once())->method('createCommand')->willReturn($command);

        $provider = new DbFlagProvider(db: $db, table: 'feature_flags');
        $provider->remove(name: 'stale-flag');

        $this->assertSame('feature_flags', $captured['table']);
        $this->assertSame(['name' => 'stale-flag'], $captured['condition']);
    }

    /**
     * @param array{table: ?string, columns: array<string, scalar|null>} $captured
     */
    private function mockDbWithUpsertCapture(array &$captured): ConnectionInterface
    {
        $command = $this->createMock(CommandInterface::class);
        $command->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(function (string $table, array $insertColumns) use (&$captured, $command) {
                $captured['table'] = $table;
                $captured['columns'] = $insertColumns;

                return $command;
            });
        $command->expects($this->once())->method('execute');

        $db = $this->createMock(ConnectionInterface::class);
        $db->expects($this->once())->method('createCommand')->willReturn($command);

        return $db;
    }
}
