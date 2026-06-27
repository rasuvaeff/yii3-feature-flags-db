<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests\Integration;

use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlagsDb\CachedFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\Exception\InvalidFlagRowException;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

#[Test]
#[CoversNothing]
final class SqliteIntegrationTest
{
    private ConnectionInterface $db;

    #[BeforeTest]
    public function setUp(): void
    {
        $driver = new SqliteDriver(dsn: 'sqlite::memory:');
        $schemaCache = new SchemaCache(psrCache: new MemorySimpleCache());
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

    #[AfterTest]
    public function tearDown(): void
    {
        $this->db->close();
    }

    public function returnsEmptyArrayFromEmptyTable(): void
    {
        $provider = new DbFlagProvider(db: $this->db);

        Assert::same($provider->getFlags(), []);
    }

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

        Assert::count($flags, 1);
        Assert::array($flags)->hasKeys('new-checkout');

        $flag = $flags['new-checkout'];
        Assert::same($flag->name, 'new-checkout');
        Assert::true($flag->enabled);
        Assert::same($flag->salt, 'checkout-v1');
        Assert::same($flag->rollout, 50);
        Assert::false($flag->killSwitch);
        Assert::same($flag->environments, ['production']);
    }

    public function readsMultipleFlags(): void
    {
        $this->insertRow(name: 'flag-a', enabled: true, salt: '', rollout: 100, killSwitch: false, environments: '[]');
        $this->insertRow(name: 'flag-b', enabled: false, salt: 'b-salt', rollout: 0, killSwitch: true, environments: '["staging"]');

        $provider = new DbFlagProvider(db: $this->db);
        $flags = $provider->getFlags();

        Assert::count($flags, 2);
        Assert::true($flags['flag-a']->enabled);
        Assert::false($flags['flag-b']->enabled);
        Assert::true($flags['flag-b']->killSwitch);
        Assert::same($flags['flag-b']->environments, ['staging']);
    }

    public function emptySaltFallsBackToName(): void
    {
        $this->insertRow(name: 'my-flag', enabled: true, salt: '', rollout: 100, killSwitch: false, environments: '[]');

        $provider = new DbFlagProvider(db: $this->db);
        $flag = $provider->getFlags()['my-flag'];

        Assert::same($flag->salt, 'my-flag');
    }

    public function readsKillSwitchFlag(): void
    {
        $this->insertRow(name: 'kill-flag', enabled: true, salt: '', rollout: 100, killSwitch: true, environments: '[]');

        $provider = new DbFlagProvider(db: $this->db);
        $flag = $provider->getFlags()['kill-flag'];

        Assert::true($flag->killSwitch);
    }

    public function readsDisabledFlag(): void
    {
        $this->insertRow(name: 'off-flag', enabled: false, salt: '', rollout: 100, killSwitch: false, environments: '[]');

        $provider = new DbFlagProvider(db: $this->db);
        $flag = $provider->getFlags()['off-flag'];

        Assert::false($flag->enabled);
    }

    public function readsEmptyEnvironments(): void
    {
        $this->insertRow(name: 'no-envs', enabled: true, salt: '', rollout: 100, killSwitch: false, environments: '[]');

        $provider = new DbFlagProvider(db: $this->db);
        $flag = $provider->getFlags()['no-envs'];

        Assert::same($flag->environments, []);
    }

    public function readsMultipleEnvironments(): void
    {
        $this->insertRow(name: 'multi-env', enabled: true, salt: '', rollout: 100, killSwitch: false, environments: '["production","staging","development"]');

        $provider = new DbFlagProvider(db: $this->db);
        $flag = $provider->getFlags()['multi-env'];

        Assert::same($flag->environments, ['production', 'staging', 'development']);
    }

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

        Assert::count($flags, 1);
        Assert::array($flags)->hasKeys('custom-flag');
    }

    public function throwsOnInvalidEnvironmentsJson(): void
    {
        $this->insertRow(name: 'bad-envs', enabled: true, salt: '', rollout: 100, killSwitch: false, environments: 'not-json');

        $provider = new DbFlagProvider(db: $this->db);

        Expect::exception(InvalidFlagRowException::class);

        $provider->getFlags();
    }

    public function throwsOnNonStringEnvironmentsItem(): void
    {
        $this->insertRow(name: 'bad-env-item', enabled: true, salt: '', rollout: 100, killSwitch: false, environments: '[1,2]');

        $provider = new DbFlagProvider(db: $this->db);

        try {
            $provider->getFlags();
            Assert::fail('Expected InvalidFlagRowException');
        } catch (InvalidFlagRowException $e) {
            Assert::string($e->getMessage())->contains('Invalid environments[0]: expected string');
        }
    }

    public function saveInsertsNewFlagVisibleOnNextRead(): void
    {
        $provider = new DbFlagProvider(db: $this->db);

        $provider->save(flag: new Flag(
            name: 'saved-flag',
            enabled: true,
            salt: 'salt-v1',
            rollout: 25,
            killSwitch: false,
            environments: ['production', 'staging'],
        ));

        $flags = $provider->getFlags();

        Assert::array($flags)->hasKeys('saved-flag');

        $flag = $flags['saved-flag'];
        Assert::true($flag->enabled);
        Assert::same($flag->salt, 'salt-v1');
        Assert::same($flag->rollout, 25);
        Assert::false($flag->killSwitch);
        Assert::same($flag->environments, ['production', 'staging']);
    }

    public function saveUpdatesExistingFlagByName(): void
    {
        $provider = new DbFlagProvider(db: $this->db);

        $provider->save(flag: new Flag(name: 'existing', enabled: true, rollout: 100));

        $provider->save(flag: new Flag(
            name: 'existing',
            enabled: false,
            rollout: 0,
            killSwitch: true,
            environments: ['staging'],
        ));

        $flag = $provider->getFlags()['existing'];

        Assert::false($flag->enabled);
        Assert::same($flag->rollout, 0);
        Assert::true($flag->killSwitch);
        Assert::same($flag->environments, ['staging']);
    }

    public function savePreservesEmptySaltRoundTrip(): void
    {
        $provider = new DbFlagProvider(db: $this->db);

        $provider->save(flag: new Flag(name: 'no-salt'));

        $flag = $provider->getFlags()['no-salt'];

        Assert::same($flag->salt, 'no-salt');
    }

    public function removeDeletesFlagByName(): void
    {
        $provider = new DbFlagProvider(db: $this->db);

        $provider->save(flag: new Flag(name: 'to-remove'));

        Assert::array($provider->getFlags())->hasKeys('to-remove');

        $provider->remove(name: 'to-remove');

        Assert::array($provider->getFlags())->doesNotHaveKeys('to-remove');
    }

    public function removeOnMissingNameIsNoOp(): void
    {
        $provider = new DbFlagProvider(db: $this->db);

        Assert::same($provider->getFlags(), []);

        $provider->remove(name: 'does-not-exist');

        Assert::same($provider->getFlags(), []);
    }

    public function cachedProviderWriteThroughInvalidatesCache(): void
    {
        $db = new DbFlagProvider(db: $this->db);
        $cached = new CachedFlagProvider(inner: $db, cache: new MemorySimpleCache(), ttl: 60);

        $cached->save(flag: new Flag(name: 'cached-flag'));

        $flags = $cached->getFlags();

        Assert::array($flags)->hasKeys('cached-flag');

        $cached->save(flag: new Flag(name: 'cached-flag', enabled: false));

        Assert::false($cached->getFlags()['cached-flag']->enabled);

        $cached->remove(name: 'cached-flag');

        Assert::array($cached->getFlags())->doesNotHaveKeys('cached-flag');
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
