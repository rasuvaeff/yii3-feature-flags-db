<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\Exception\InvalidFlagRowException;
use Rasuvaeff\Yii3FeatureFlagsDb\Tests\NullPsr16Cache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;

#[CoversClass(DbFlagProvider::class)]
final class SqliteIntegrationTest extends TestCase
{
    private ConnectionInterface $db;

    #[\Override]
    protected function setUp(): void
    {
        $driver = new SqliteDriver(dsn: 'sqlite::memory:');
        $schemaCache = new SchemaCache(psrCache: new NullPsr16Cache());
        $this->db = new SqliteConnection(driver: $driver, schemaCache: $schemaCache);
        $this->db->open();

        $this->db->createCommand(sql: '
            CREATE TABLE feature_flags (
                name        VARCHAR(190) PRIMARY KEY,
                enabled     INTEGER      NOT NULL DEFAULT 1,
                salt        VARCHAR(190) NOT NULL DEFAULT \'\',
                rollout     INTEGER      NOT NULL DEFAULT 100,
                kill_switch INTEGER      NOT NULL DEFAULT 0,
                environments TEXT        NOT NULL DEFAULT \'[]\'
            )
        ')->execute();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->db->close();
    }

    #[Test]
    public function returnsEmptyArrayFromEmptyTable(): void
    {
        $provider = new DbFlagProvider(db: $this->db);

        $this->assertSame([], $provider->getFlags());
    }

    #[Test]
    public function readsSingleFlag(): void
    {
        $this->insertRow(
            name: 'new-checkout',
            enabled: true,
            salt: 'checkout-v1',
            rollout: 50,
            killSwitch: false,
            environments: '["production"]',
        );

        $provider = new DbFlagProvider(db: $this->db);
        $flags = $provider->getFlags();

        $this->assertCount(1, $flags);
        $this->assertArrayHasKey('new-checkout', $flags);

        $flag = $flags['new-checkout'];
        $this->assertSame('new-checkout', $flag->name);
        $this->assertTrue($flag->enabled);
        $this->assertSame('checkout-v1', $flag->salt);
        $this->assertSame(50, $flag->rollout);
        $this->assertFalse($flag->killSwitch);
        $this->assertSame(['production'], $flag->environments);
    }

    #[Test]
    public function readsMultipleFlags(): void
    {
        $this->insertRow(name: 'flag-a', enabled: true, salt: '', rollout: 100, killSwitch: false, environments: '[]');
        $this->insertRow(name: 'flag-b', enabled: false, salt: 'b-salt', rollout: 0, killSwitch: true, environments: '["staging"]');

        $provider = new DbFlagProvider(db: $this->db);
        $flags = $provider->getFlags();

        $this->assertCount(2, $flags);
        $this->assertTrue($flags['flag-a']->enabled);
        $this->assertFalse($flags['flag-b']->enabled);
        $this->assertTrue($flags['flag-b']->killSwitch);
        $this->assertSame(['staging'], $flags['flag-b']->environments);
    }

    #[Test]
    public function emptySaltFallsBackToName(): void
    {
        $this->insertRow(name: 'my-flag', enabled: true, salt: '', rollout: 100, killSwitch: false, environments: '[]');

        $provider = new DbFlagProvider(db: $this->db);
        $flag = $provider->getFlags()['my-flag'];

        $this->assertSame('my-flag', $flag->salt);
    }

    #[Test]
    public function readsKillSwitchFlag(): void
    {
        $this->insertRow(name: 'kill-flag', enabled: true, salt: '', rollout: 100, killSwitch: true, environments: '[]');

        $provider = new DbFlagProvider(db: $this->db);
        $flag = $provider->getFlags()['kill-flag'];

        $this->assertTrue($flag->killSwitch);
    }

    #[Test]
    public function readsDisabledFlag(): void
    {
        $this->insertRow(name: 'off-flag', enabled: false, salt: '', rollout: 100, killSwitch: false, environments: '[]');

        $provider = new DbFlagProvider(db: $this->db);
        $flag = $provider->getFlags()['off-flag'];

        $this->assertFalse($flag->enabled);
    }

    #[Test]
    public function readsEmptyEnvironments(): void
    {
        $this->insertRow(name: 'no-envs', enabled: true, salt: '', rollout: 100, killSwitch: false, environments: '[]');

        $provider = new DbFlagProvider(db: $this->db);
        $flag = $provider->getFlags()['no-envs'];

        $this->assertSame([], $flag->environments);
    }

    #[Test]
    public function readsMultipleEnvironments(): void
    {
        $this->insertRow(name: 'multi-env', enabled: true, salt: '', rollout: 100, killSwitch: false, environments: '["production","staging","development"]');

        $provider = new DbFlagProvider(db: $this->db);
        $flag = $provider->getFlags()['multi-env'];

        $this->assertSame(['production', 'staging', 'development'], $flag->environments);
    }

    #[Test]
    public function usesCustomTableName(): void
    {
        $this->db->createCommand(sql: '
            CREATE TABLE custom_flags (
                name        VARCHAR(190) PRIMARY KEY,
                enabled     INTEGER      NOT NULL DEFAULT 1,
                salt        VARCHAR(190) NOT NULL DEFAULT \'\',
                rollout     INTEGER      NOT NULL DEFAULT 100,
                kill_switch INTEGER      NOT NULL DEFAULT 0,
                environments TEXT        NOT NULL DEFAULT \'[]\'
            )
        ')->execute();

        $this->db->createCommand(sql: "
            INSERT INTO custom_flags (name, enabled, salt, rollout, kill_switch, environments)
            VALUES ('custom-flag', 1, '', 100, 0, '[]')
        ")->execute();

        $provider = new DbFlagProvider(db: $this->db, table: 'custom_flags');
        $flags = $provider->getFlags();

        $this->assertCount(1, $flags);
        $this->assertArrayHasKey('custom-flag', $flags);
    }

    #[Test]
    public function throwsOnInvalidEnvironmentsJson(): void
    {
        $this->insertRow(name: 'bad-envs', enabled: true, salt: '', rollout: 100, killSwitch: false, environments: 'not-json');

        $provider = new DbFlagProvider(db: $this->db);

        $this->expectException(InvalidFlagRowException::class);

        $provider->getFlags();
    }

    #[Test]
    public function throwsOnNonStringEnvironmentsItem(): void
    {
        $this->insertRow(name: 'bad-env-item', enabled: true, salt: '', rollout: 100, killSwitch: false, environments: '[1,2]');

        $provider = new DbFlagProvider(db: $this->db);

        $this->expectException(InvalidFlagRowException::class);
        $this->expectExceptionMessage('Invalid environments[0]: expected string');

        $provider->getFlags();
    }

    private function insertRow(
        string $name,
        bool $enabled,
        string $salt,
        int $rollout,
        bool $killSwitch,
        string $environments,
    ): void {
        $this->db->createCommand(sql: "
            INSERT INTO feature_flags (name, enabled, salt, rollout, kill_switch, environments)
            VALUES (:name, :enabled, :salt, :rollout, :kill_switch, :environments)
        ")->bindValues([
            ':name' => $name,
            ':enabled' => $enabled ? 1 : 0,
            ':salt' => $salt,
            ':rollout' => $rollout,
            ':kill_switch' => $killSwitch ? 1 : 0,
            ':environments' => $environments,
        ])->execute();
    }
}
