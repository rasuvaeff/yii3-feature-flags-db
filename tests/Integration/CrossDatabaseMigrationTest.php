<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests\Integration;

use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider;
use Rasuvaeff\Yii3FeatureFlagsDb\Migration\M260605000000CreateFeatureFlagsTable;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Mysql\Connection as MysqlConnection;
use Yiisoft\Db\Mysql\Driver as MysqlDriver;
use Yiisoft\Db\Pgsql\Connection as PgsqlConnection;
use Yiisoft\Db\Pgsql\Driver as PgsqlDriver;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

/**
 * The bundled migration is otherwise only ever applied to SQLite, which accepts
 * DDL the other engines reject — a literal `DEFAULT` on a TEXT column aborts
 * `migrate:up` on MySQL with error 1101 and creates nothing. Runs only when
 * `FEATURE_FLAGS_TEST_DB` names a live server; CI supplies both.
 */
#[Test]
#[CoversNothing]
final class CrossDatabaseMigrationTest
{
    public function migrationAppliesOnConfiguredDatabase(): void
    {
        $database = getenv('FEATURE_FLAGS_TEST_DB');

        if ($database !== 'mysql' && $database !== 'pgsql') {
            Assert::true($database === false || $database === '');

            return;
        }

        $db = $this->connection($database);
        $db->open();

        try {
            $db->createCommand('DROP TABLE IF EXISTS feature_flags')->execute();

            $migration = new M260605000000CreateFeatureFlagsTable();
            $builder = new MigrationBuilder(db: $db, informer: new NullMigrationInformer());

            $migration->up($builder);

            $provider = new DbFlagProvider(db: $db);
            $provider->save(new Flag(name: 'new-checkout', rollout: 50, environments: ['production']));
            $provider->save(new Flag(name: 'dark-mode', enabled: false));

            $flags = $provider->getFlags();

            Assert::same($flags['new-checkout']->rollout, 50);
            Assert::same($flags['new-checkout']->environments, ['production']);
            Assert::false($flags['dark-mode']->enabled);
            // the empty array round-trips through a column that now has no
            // database default to fall back on
            Assert::same($flags['dark-mode']->environments, []);

            $migration->down($builder);

            Assert::null($db->getTableSchema('feature_flags', true));
        } finally {
            $db->createCommand('DROP TABLE IF EXISTS feature_flags')->execute();
            $db->close();
        }
    }

    private function connection(string $database): ConnectionInterface
    {
        $cache = new SchemaCache(psrCache: new MemorySimpleCache());
        $mysqlPort = getenv('FEATURE_FLAGS_TEST_MYSQL_PORT') ?: '3306';
        $pgsqlPort = getenv('FEATURE_FLAGS_TEST_PGSQL_PORT') ?: '5432';

        return $database === 'mysql'
            ? new MysqlConnection(
                driver: new MysqlDriver(
                    dsn: sprintf('mysql:host=127.0.0.1;port=%s;dbname=feature_flags;charset=utf8mb4', $mysqlPort),
                    username: 'root',
                    password: 'feature_flags',
                ),
                schemaCache: $cache,
            )
            : new PgsqlConnection(
                driver: new PgsqlDriver(
                    dsn: sprintf('pgsql:host=127.0.0.1;port=%s;dbname=feature_flags', $pgsqlPort),
                    username: 'postgres',
                    password: 'feature_flags',
                ),
                schemaCache: $cache,
            );
    }
}
