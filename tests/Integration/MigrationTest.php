<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests\Integration;

use M260605000000CreateFeatureFlagsTable;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\Tests\NullPsr16Cache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;

#[CoversNothing]
final class MigrationTest extends TestCase
{
    private ConnectionInterface $db;

    private MigrationBuilder $builder;

    #[\Override]
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/migrations/M260605000000CreateFeatureFlagsTable.php';

        $driver = new SqliteDriver(dsn: 'sqlite::memory:');
        $schemaCache = new SchemaCache(psrCache: new NullPsr16Cache());
        $this->db = new SqliteConnection(driver: $driver, schemaCache: $schemaCache);
        $this->db->open();

        $this->builder = new MigrationBuilder(db: $this->db, informer: new NullMigrationInformer());
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->db->close();
    }

    #[Test]
    public function createsAndDropsFeatureFlagsTable(): void
    {
        $migration = new M260605000000CreateFeatureFlagsTable();

        $migration->up($this->builder);

        $schema = $this->db->getTableSchema('feature_flags', true);
        $this->assertNotNull($schema);
        $this->assertNotNull($schema->getColumn('name'));
        $this->assertNotNull($schema->getColumn('enabled'));
        $this->assertNotNull($schema->getColumn('salt'));
        $this->assertNotNull($schema->getColumn('rollout'));
        $this->assertNotNull($schema->getColumn('kill_switch'));
        $this->assertNotNull($schema->getColumn('environments'));
        $this->assertSame(['name'], $schema->getPrimaryKey());

        $migration->down($this->builder);

        $this->assertNull($this->db->getTableSchema('feature_flags', true));
    }

    #[Test]
    public function createsTableWithCustomName(): void
    {
        (new M260605000000CreateFeatureFlagsTable(table: 'custom_flags'))->up($this->builder);

        $this->assertNotNull($this->db->getTableSchema('custom_flags', true));
        $this->assertNull($this->db->getTableSchema('feature_flags', true));
    }

    #[Test]
    public function migratedTableIsReadableByProvider(): void
    {
        (new M260605000000CreateFeatureFlagsTable())->up($this->builder);

        $this->db->createCommand(
            sql: "INSERT INTO feature_flags (name, enabled, salt, rollout, kill_switch, environments)
                  VALUES ('new-checkout', 1, '', 50, 0, '[\"production\"]')",
        )->execute();

        $flags = (new DbFlagProvider(db: $this->db))->getFlags();

        $this->assertArrayHasKey('new-checkout', $flags);
        $this->assertSame(50, $flags['new-checkout']->rollout);
        $this->assertSame(['production'], $flags['new-checkout']->environments);
    }
}
