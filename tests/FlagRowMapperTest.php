<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3FeatureFlagsDb\Exception\InvalidFlagRowException;
use Rasuvaeff\Yii3FeatureFlagsDb\FlagRowMapper;

#[CoversClass(FlagRowMapper::class)]
final class FlagRowMapperTest extends TestCase
{
    private FlagRowMapper $mapper;

    #[\Override]
    protected function setUp(): void
    {
        $this->mapper = new FlagRowMapper();
    }

    #[Test]
    public function mapsRowWithNativeTypes(): void
    {
        $flag = $this->mapper->map([
            'name' => 'new-checkout',
            'enabled' => true,
            'salt' => 'checkout-v1',
            'rollout' => 50,
            'kill_switch' => false,
            'environments' => ['production', 'staging'],
        ]);

        $this->assertSame('new-checkout', $flag->name);
        $this->assertTrue($flag->enabled);
        $this->assertSame('checkout-v1', $flag->salt);
        $this->assertSame(50, $flag->rollout);
        $this->assertFalse($flag->killSwitch);
        $this->assertSame(['production', 'staging'], $flag->environments);
    }

    #[Test]
    public function mapsRowWithStringScalars(): void
    {
        $flag = $this->mapper->map([
            'name' => 'flag-a',
            'enabled' => '1',
            'salt' => '',
            'rollout' => '75',
            'kill_switch' => '0',
            'environments' => '["production"]',
        ]);

        $this->assertTrue($flag->enabled);
        $this->assertSame(75, $flag->rollout);
        $this->assertFalse($flag->killSwitch);
        $this->assertSame('flag-a', $flag->salt);
        $this->assertSame(['production'], $flag->environments);
    }

    /**
     * @return iterable<string, array{0: bool|int|string, 1: bool}>
     */
    public static function boolCastProvider(): iterable
    {
        yield 'bool true' => [true, true];
        yield 'bool false' => [false, false];
        yield 'int 1' => [1, true];
        yield 'int 0' => [0, false];
        yield 'string 1' => ['1', true];
        yield 'string 0' => ['0', false];
        yield 'string empty' => ['', false];
        yield 'string other' => ['yes', true];
    }

    #[DataProvider('boolCastProvider')]
    #[Test]
    public function castsEnabledColumn(bool|int|string $raw, bool $expected): void
    {
        $this->assertSame($expected, $this->mapper->map($this->row(enabled: $raw))->enabled);
    }

    #[Test]
    public function readsEmptyEnvironmentsFromEmptyString(): void
    {
        $this->assertSame([], $this->mapper->map($this->row(environments: ''))->environments);
    }

    #[Test]
    public function readsEnvironmentsFromNativeArray(): void
    {
        $this->assertSame(['a', 'b'], $this->mapper->map($this->row(environments: ['a', 'b']))->environments);
    }

    #[Test]
    public function readsEnvironmentsFromJsonArray(): void
    {
        $this->assertSame(['a', 'b'], $this->mapper->map($this->row(environments: '["a","b"]'))->environments);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidRowProvider(): iterable
    {
        $base = [
            'name' => 'flag',
            'enabled' => true,
            'salt' => '',
            'rollout' => 100,
            'kill_switch' => false,
            'environments' => '[]',
        ];

        yield 'missing name' => [self::without($base, 'name'), 'name'];
        yield 'non-string name' => [['name' => 5] + $base, 'name'];
        yield 'missing salt' => [self::without($base, 'salt'), 'salt'];
        yield 'non-string salt' => [['salt' => 5] + $base, 'salt'];
        yield 'missing enabled' => [self::without($base, 'enabled'), 'enabled'];
        yield 'null enabled' => [['enabled' => null] + $base, 'enabled'];
        yield 'missing rollout' => [self::without($base, 'rollout'), 'rollout'];
        yield 'non-numeric rollout' => [['rollout' => 'abc'] + $base, 'rollout'];
        yield 'rollout with leading garbage' => [['rollout' => 'a12'] + $base, 'rollout'];
        yield 'rollout with trailing garbage' => [['rollout' => '12a'] + $base, 'rollout'];
        yield 'missing kill_switch' => [self::without($base, 'kill_switch'), 'kill_switch'];
        yield 'missing environments' => [self::without($base, 'environments'), 'environments'];
        yield 'malformed environments json' => [['environments' => 'not-json'] + $base, 'Invalid "environments" JSON'];
        yield 'environments json not array' => [['environments' => '5'] + $base, 'environments'];
        yield 'environments json non-string item' => [['environments' => '[1]'] + $base, 'environments'];
        yield 'environments native non-array' => [['environments' => 5] + $base, 'environments'];
        yield 'environments native non-string item' => [['environments' => [1]] + $base, 'environments'];
    }

    /**
     * @param array<string, mixed> $row
     */
    #[DataProvider('invalidRowProvider')]
    #[Test]
    public function throwsOnInvalidRow(array $row, string $needle): void
    {
        $this->expectException(InvalidFlagRowException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($needle, '/') . '/');

        $this->mapper->map($row);
    }

    #[Test]
    public function wrapsCoreExceptionForRolloutOutOfRange(): void
    {
        $this->expectException(InvalidFlagRowException::class);
        $this->expectExceptionMessage('Invalid flag "flag" in DB row');

        $this->mapper->map($this->row(rollout: 150));
    }

    #[Test]
    public function wrapsCoreExceptionForInvalidName(): void
    {
        $this->expectException(InvalidFlagRowException::class);
        $this->expectExceptionMessage('Invalid flag "Bad-Name" in DB row');

        $this->mapper->map($this->row(name: 'Bad-Name'));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        string $name = 'flag',
        bool|int|string $enabled = true,
        string $salt = '',
        int|string $rollout = 100,
        bool|int|string $killSwitch = false,
        array|string $environments = '[]',
    ): array {
        return [
            'name' => $name,
            'enabled' => $enabled,
            'salt' => $salt,
            'rollout' => $rollout,
            'kill_switch' => $killSwitch,
            'environments' => $environments,
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function without(array $row, string $key): array
    {
        unset($row[$key]);

        return $row;
    }
}
