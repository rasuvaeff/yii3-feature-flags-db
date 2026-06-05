<?php

declare(strict_types=1);

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Creates the feature-flags table read by {@see \Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider}.
 *
 * The table name defaults to `feature_flags` and must match the `table` argument
 * of {@see \Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider}. To use a custom name,
 * bind the constructor argument in your DI configuration:
 *
 * ```php
 * M260605000000CreateFeatureFlagsTable::class => [
 *     '__construct()' => ['table' => 'my_feature_flags'],
 * ],
 * ```
 */
final class M260605000000CreateFeatureFlagsTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    /**
     * @param non-empty-string $table
     */
    public function __construct(
        private readonly string $table = 'feature_flags',
    ) {}

    #[\Override]
    public function up(MigrationBuilder $b): void
    {
        $b->createTable(
            $this->table,
            [
                'name' => 'string(190) NOT NULL PRIMARY KEY',
                'enabled' => 'boolean NOT NULL DEFAULT TRUE',
                'salt' => "string(190) NOT NULL DEFAULT ''",
                'rollout' => 'smallint NOT NULL DEFAULT 100',
                'kill_switch' => 'boolean NOT NULL DEFAULT FALSE',
                'environments' => "text NOT NULL DEFAULT '[]'",
            ],
        );
    }

    #[\Override]
    public function down(MigrationBuilder $b): void
    {
        $b->dropTable($this->table);
    }
}
