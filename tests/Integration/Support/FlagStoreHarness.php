<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests\Integration\Support;

use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

/**
 * Stateful-test harness around a {@see DbFlagProvider} backed by a fresh
 * in-memory SQLite database. Flags are addressed by index.
 *
 * The system under test for the model-based property in
 * {@see \Rasuvaeff\Yii3FeatureFlagsDb\Tests\Integration\DbFlagProviderStatefulTest}.
 */
final class FlagStoreHarness
{
    private readonly ConnectionInterface $db;
    private readonly DbFlagProvider $provider;

    /** @var list<string> */
    private readonly array $names;

    public function __construct(int $flagCount)
    {
        $driver = new SqliteDriver(dsn: 'sqlite::memory:');
        $this->db = new SqliteConnection(driver: $driver, schemaCache: new SchemaCache(psrCache: new MemorySimpleCache()));
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

        $this->provider = new DbFlagProvider(db: $this->db);
        $this->names = array_map(
            static fn(int $i): string => 'flag-' . $i,
            range(0, $flagCount - 1),
        );
    }

    public function save(int $index, bool $enabled): void
    {
        $this->provider->save(new Flag(name: $this->names[$index], enabled: $enabled, rollout: 100));
    }

    public function remove(int $index): void
    {
        $this->provider->remove($this->names[$index]);
    }

    /**
     * The enabled flag of each of the first $count names, or null when absent —
     * mirrors the model.
     *
     * @return list<?bool>
     */
    public function snapshot(int $count): array
    {
        $flags = $this->provider->getFlags();

        return array_map(
            fn(int $i): ?bool => $flags[$this->names[$i]]->enabled ?? null,
            range(0, $count - 1),
        );
    }

    public function totalFlags(): int
    {
        return count($this->provider->getFlags());
    }
}
