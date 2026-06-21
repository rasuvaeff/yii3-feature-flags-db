<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Benchmarks;

use Rasuvaeff\Yii3FeatureFlagsDb\FlagRowMapper;
use Testo\Bench;

final class AdapterBench
{
    #[Bench(
        callables: [
            'with-environments' => [self::class, 'mapWithEnvironments'],
        ],
        calls: 1_000,
        iterations: 10,
    )]
    public static function mapSimple(): mixed
    {
        return (new FlagRowMapper())->map([
            'name' => 'dark-mode',
            'enabled' => 1,
            'salt' => 'dark-mode',
            'rollout' => 100,
            'kill_switch' => 0,
            'environments' => '[]',
        ]);
    }

    public static function mapWithEnvironments(): mixed
    {
        return (new FlagRowMapper())->map([
            'name' => 'dark-mode',
            'enabled' => 1,
            'salt' => 'dark-mode',
            'rollout' => 50,
            'kill_switch' => 0,
            'environments' => '["production","staging","preview"]',
        ]);
    }
}
