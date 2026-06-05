<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb;

use Rasuvaeff\Yii3FeatureFlags\Exception\InvalidFlagNameException;
use Rasuvaeff\Yii3FeatureFlags\Flag;
use Rasuvaeff\Yii3FeatureFlags\FlagConfig;

/**
 * Maps a raw database row into a validated {@see Flag}.
 *
 * @internal
 */
final class FlagRowMapper
{
    /**
     * @param array<array-key, mixed> $row
     */
    public function map(array $row): Flag
    {
        $name = $this->extractString(row: $row, column: 'name');
        $config = new FlagConfig(
            enabled: $this->extractBool(row: $row, column: 'enabled'),
            salt: $this->extractString(row: $row, column: 'salt'),
            rollout: $this->extractInt(row: $row, column: 'rollout'),
            killSwitch: $this->extractBool(row: $row, column: 'kill_switch'),
            environments: $this->extractEnvironments(row: $row),
        );

        try {
            return $config->toFlag(name: $name);
        } catch (InvalidFlagNameException $e) {
            throw new Exception\InvalidFlagRowException(
                message: sprintf('Invalid flag "%s" in DB row: %s', $name, $e->getMessage()),
                previous: $e,
            );
        }
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function extractString(array $row, string $column): string
    {
        if (!isset($row[$column]) || !\is_string($row[$column])) {
            throw new Exception\InvalidFlagRowException(
                message: sprintf('Missing or invalid column "%s" in flag row', $column),
            );
        }

        return $row[$column];
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function extractInt(array $row, string $column): int
    {
        if (!isset($row[$column])) {
            throw new Exception\InvalidFlagRowException(
                message: sprintf('Missing or invalid column "%s" in flag row', $column),
            );
        }

        if (\is_int($row[$column])) {
            return $row[$column];
        }

        if (\is_string($row[$column]) && preg_match('/^-?\d+$/', $row[$column]) === 1) {
            return (int) $row[$column];
        }

        throw new Exception\InvalidFlagRowException(
            message: sprintf('Missing or invalid column "%s" in flag row', $column),
        );
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function extractBool(array $row, string $column): bool
    {
        if (!\array_key_exists($column, $row)) {
            throw new Exception\InvalidFlagRowException(
                message: sprintf('Missing or invalid column "%s" in flag row', $column),
            );
        }

        if (\is_bool($row[$column])) {
            return $row[$column];
        }

        if (\is_int($row[$column])) {
            return $row[$column] !== 0;
        }

        if (\is_string($row[$column])) {
            return $row[$column] !== '' && $row[$column] !== '0';
        }

        throw new Exception\InvalidFlagRowException(
            message: sprintf('Missing or invalid column "%s" in flag row', $column),
        );
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @return list<string>
     */
    private function extractEnvironments(array $row): array
    {
        if (!\array_key_exists('environments', $row)) {
            throw new Exception\InvalidFlagRowException(
                message: 'Missing column "environments" in flag row',
            );
        }

        if (\is_string($row['environments'])) {
            if ($row['environments'] === '') {
                return [];
            }

            try {
                return $this->validateEnvironments(
                    environments: json_decode(json: $row['environments'], associative: true, flags: JSON_THROW_ON_ERROR),
                    source: 'JSON',
                );
            } catch (\JsonException) {
                throw new Exception\InvalidFlagRowException(
                    message: sprintf('Invalid "environments" JSON: %s', $row['environments']),
                );
            }
        }

        return $this->validateEnvironments(environments: $row['environments'], source: 'column');
    }

    /**
     * @return list<string>
     */
    private function validateEnvironments(mixed $environments, string $source): array
    {
        if (!\is_array($environments)) {
            throw new Exception\InvalidFlagRowException(
                message: sprintf('Invalid "environments" %s: expected array, got %s', $source, get_debug_type($environments)),
            );
        }

        $result = [];

        foreach ($environments as $i => $item) {
            if (!\is_string($item)) {
                throw new Exception\InvalidFlagRowException(
                    message: sprintf('Invalid environments[%d]: expected string, got %s', $i, get_debug_type($item)),
                );
            }

            $result[] = $item;
        }

        return $result;
    }
}
