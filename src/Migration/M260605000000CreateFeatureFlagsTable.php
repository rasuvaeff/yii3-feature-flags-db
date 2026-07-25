<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Migration;

use Rasuvaeff\Yii3FeatureFlagsDb\FeatureFlagsTableName;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Creates the feature-flags table read by {@see \Rasuvaeff\Yii3FeatureFlagsDb\DbFlagProvider}.
 *
 * The table name comes from {@see FeatureFlagsTableName}, which `config/di.php`
 * builds from the `rasuvaeff/yii3-feature-flags-db` params — one source of
 * truth for the migration and the provider alike. Register the migration by
 * namespace:
 *
 * ```php
 * MigrationService::class => [
 *     'setSourceNamespaces()' => [['Rasuvaeff\\Yii3FeatureFlagsDb\\Migration']],
 * ],
 * ```
 *
 * @api
 */
final class M260605000000CreateFeatureFlagsTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function __construct(
        private readonly FeatureFlagsTableName $table = new FeatureFlagsTableName(),
    ) {}

    #[\Override]
    public function up(MigrationBuilder $b): void
    {
        $b->createTable(
            $this->table->value,
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
        $b->dropTable($this->table->value);
    }
}
