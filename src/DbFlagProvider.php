<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb;

use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlags\WritableFlagProvider;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Query\Query;

/**
 * @api
 */
final readonly class DbFlagProvider implements WritableFlagProvider
{
    /**
     * @param non-empty-string $table
     */
    public function __construct(
        private ConnectionInterface $db,
        private string $table = 'feature_flags',
    ) {}

    /**
     * @return array<string, Flag>
     */
    #[\Override]
    public function getFlags(): array
    {
        $rows = (new Query($this->db))
            ->from($this->table)
            ->all();

        $mapper = new FlagRowMapper();
        $flags = [];

        foreach ($rows as $row) {
            /** @var array<array-key, mixed> $row */
            $flag = $mapper->map(row: $row);
            $flags[$flag->name] = $flag;
        }

        return $flags;
    }

    #[\Override]
    public function save(Flag $flag): void
    {
        $this->db->createCommand()
            ->upsert(table: $this->table, insertColumns: $this->toRow(flag: $flag))
            ->execute();
    }

    #[\Override]
    public function remove(string $name): void
    {
        $this->db->createCommand()
            ->delete(table: $this->table, condition: ['name' => $name])
            ->execute();
    }

    /**
     * @return array<string, scalar|null>
     */
    private function toRow(Flag $flag): array
    {
        return [
            'name' => $flag->name,
            'enabled' => $flag->enabled,
            'salt' => $flag->salt === $flag->name ? '' : $flag->salt,
            'rollout' => $flag->rollout,
            'kill_switch' => $flag->killSwitch,
            'environments' => FlagRowMapper::encodeEnvironments(environments: $flag->environments),
        ];
    }
}
