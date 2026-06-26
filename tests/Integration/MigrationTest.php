<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests\Integration;

use M260605000000CreateFeatureFlagsTable;
use Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

#[Test]
#[CoversNothing]
final class MigrationTest
{
    private ConnectionInterface $db;

    private MigrationBuilder $builder;

    #[BeforeTest]
    public function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/migrations/M260605000000CreateFeatureFlagsTable.php';

        $driver = new SqliteDriver(dsn: 'sqlite::memory:');
        $schemaCache = new SchemaCache(psrCache: new MemorySimpleCache());
        $this->db = new SqliteConnection(driver: $driver, schemaCache: $schemaCache);
        $this->db->open();

        $this->builder = new MigrationBuilder(db: $this->db, informer: new NullMigrationInformer());
    }

    #[AfterTest]
    public function tearDown(): void
    {
        $this->db->close();
    }

    public function createsAndDropsFeatureFlagsTable(): void
    {
        $migration = new M260605000000CreateFeatureFlagsTable();

        $migration->up($this->builder);

        $schema = $this->db->getTableSchema('feature_flags', true);
        Assert::notNull($schema);
        Assert::notNull($schema->getColumn('name'));
        Assert::notNull($schema->getColumn('enabled'));
        Assert::notNull($schema->getColumn('salt'));
        Assert::notNull($schema->getColumn('rollout'));
        Assert::notNull($schema->getColumn('kill_switch'));
        Assert::notNull($schema->getColumn('environments'));
        Assert::same($schema->getPrimaryKey(), ['name']);

        $migration->down($this->builder);

        Assert::null($this->db->getTableSchema('feature_flags', true));
    }

    public function createsTableWithCustomName(): void
    {
        (new M260605000000CreateFeatureFlagsTable(table: 'custom_flags'))->up($this->builder);

        Assert::notNull($this->db->getTableSchema('custom_flags', true));
        Assert::null($this->db->getTableSchema('feature_flags', true));
    }

    public function migratedTableIsReadableByProvider(): void
    {
        (new M260605000000CreateFeatureFlagsTable())->up($this->builder);

        $this->db->createCommand(
            sql: "INSERT INTO feature_flags (name, enabled, salt, rollout, kill_switch, environments)
                  VALUES ('new-checkout', 1, '', 50, 0, '[\"production\"]')",
        )->execute();

        $flags = (new DbFlagProvider(db: $this->db))->getFlags();

        Assert::array($flags)->hasKeys('new-checkout');
        Assert::same($flags['new-checkout']->rollout, 50);
        Assert::same($flags['new-checkout']->environments, ['production']);
    }
}
