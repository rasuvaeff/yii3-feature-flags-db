<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3FeatureFlagsDb\Tests;

use InvalidArgumentException;
use Rasuvaeff\Yii3FeatureFlagsDb\FeatureFlagsTableName;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(FeatureFlagsTableName::class)]
final class FeatureFlagsTableNameTest
{
    public function defaultsToTheDocumentedName(): void
    {
        Assert::same((new FeatureFlagsTableName())->value, 'feature_flags');
        Assert::same((string) new FeatureFlagsTableName(), 'feature_flags');
    }

    public function acceptsASchemaQualifiedName(): void
    {
        Assert::same((new FeatureFlagsTableName('public.feature_flags'))->value, 'public.feature_flags');
    }

    public function indexBaseFlattensTheSchemaSeparator(): void
    {
        // a dot cannot appear in an index name
        Assert::same((new FeatureFlagsTableName('public.feature_flags'))->forIndexName(), 'public_feature_flags');
        Assert::same((new FeatureFlagsTableName('feature_flags'))->forIndexName(), 'feature_flags');
    }

    #[DataProvider('invalidNamesProvider')]
    public function rejectsAnythingOutsideTheIdentifierWhitelist(string $name): void
    {
        Expect::exception(InvalidArgumentException::class);

        new FeatureFlagsTableName($name);
    }

    public static function invalidNamesProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'starts with digit' => ['1table'];
        yield 'space' => ['my table'];
        yield 'semicolon injection' => ['t; DROP TABLE users'];
        yield 'dash' => ['my-table'];
        yield 'two dots' => ['a.b.c'];
        // PCRE's $ also matches before a trailing newline — the pattern is
        // anchored with \z so this is rejected
        yield 'trailing newline' => ["feature_flags\n"];
    }
}
