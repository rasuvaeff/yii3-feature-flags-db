<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb;

use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlags\FlagProvider;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Query\Query;

/**
 * @api
 */
final readonly class DbFlagProvider implements FlagProvider
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
}
