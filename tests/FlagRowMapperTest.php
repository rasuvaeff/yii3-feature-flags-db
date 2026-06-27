<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests;

use Rasuvaeff\Yii3FeatureFlagsDb\Exception\InvalidFlagRowException;
use Rasuvaeff\Yii3FeatureFlagsDb\FlagRowMapper;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(FlagRowMapper::class)]
final class FlagRowMapperTest
{
    private FlagRowMapper $mapper;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->mapper = new FlagRowMapper();
    }

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

        Assert::same($flag->name, 'new-checkout');
        Assert::true($flag->enabled);
        Assert::same($flag->salt, 'checkout-v1');
        Assert::same($flag->rollout, 50);
        Assert::false($flag->killSwitch);
        Assert::same($flag->environments, ['production', 'staging']);
    }

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

        Assert::true($flag->enabled);
        Assert::same($flag->rollout, 75);
        Assert::false($flag->killSwitch);
        Assert::same($flag->salt, 'flag-a');
        Assert::same($flag->environments, ['production']);
    }

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
    public function castsEnabledColumn(bool|int|string $raw, bool $expected): void
    {
        Assert::same($this->mapper->map($this->row(enabled: $raw))->enabled, $expected);
    }

    public function readsEmptyEnvironmentsFromEmptyString(): void
    {
        Assert::same($this->mapper->map($this->row(environments: ''))->environments, []);
    }

    public function readsEnvironmentsFromNativeArray(): void
    {
        Assert::same($this->mapper->map($this->row(environments: ['a', 'b']))->environments, ['a', 'b']);
    }

    public function readsEnvironmentsFromJsonArray(): void
    {
        Assert::same($this->mapper->map($this->row(environments: '["a","b"]'))->environments, ['a', 'b']);
    }

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
        yield 'missing environments' => [self::without($base, 'environments'), 'Missing column "environments"'];
        yield 'malformed environments json' => [['environments' => 'not-json'] + $base, 'Invalid "environments" JSON'];
        yield 'environments json not array' => [['environments' => '5'] + $base, 'environments'];
        yield 'environments json non-string item' => [['environments' => '[1]'] + $base, 'environments'];
        yield 'environments native non-array' => [['environments' => 5] + $base, 'environments'];
        yield 'environments native non-string item' => [['environments' => [1]] + $base, 'environments'];
    }

    #[DataProvider('invalidRowProvider')]
    public function throwsOnInvalidRow(array $row, string $needle): void
    {
        try {
            $this->mapper->map($row);
            Assert::fail('Expected InvalidFlagRowException');
        } catch (InvalidFlagRowException $e) {
            Assert::string($e->getMessage())->contains($needle);
        }
    }

    public function wrapsCoreExceptionForRolloutOutOfRange(): void
    {
        try {
            $this->mapper->map($this->row(rollout: 150));
            Assert::fail('Expected InvalidFlagRowException');
        } catch (InvalidFlagRowException $e) {
            Assert::string($e->getMessage())->contains('Invalid flag "flag" in DB row');
        }
    }

    public static function encodeEnvironmentsProvider(): iterable
    {
        yield 'empty' => [[], '[]'];
        yield 'single' => [['production'], '["production"]'];
        yield 'multi' => [['production', 'staging', 'development'], '["production","staging","development"]'];
    }

    #[DataProvider('encodeEnvironmentsProvider')]
    public function encodeEnvironmentsRoundTrips(array $input, string $expected): void
    {
        $encoded = FlagRowMapper::encodeEnvironments(environments: $input);

        Assert::same($encoded, $expected);

        $decoded = $this->mapper->map(row: $this->row(environments: $encoded))->environments;

        Assert::same($decoded, $input);
    }

    public function encodeEnvironmentsIsEmptyArrayStringForEmptyInput(): void
    {
        Assert::same(FlagRowMapper::encodeEnvironments(environments: []), '[]');
    }

    public function wrapsCoreExceptionForInvalidName(): void
    {
        try {
            $this->mapper->map($this->row(name: 'Bad-Name'));
            Assert::fail('Expected InvalidFlagRowException');
        } catch (InvalidFlagRowException $e) {
            Assert::string($e->getMessage())->contains('Invalid flag "Bad-Name" in DB row');
        }
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
