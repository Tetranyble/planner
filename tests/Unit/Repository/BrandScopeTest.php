<?php

namespace Tests\Unit\Repository;

use App\Repository\Support\BrandScope;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BrandScopeTest extends TestCase
{
    public function test_accepts_valid_scope(): void
    {
        $scope = new BrandScope(12, 34);
        self::assertSame(12, $scope->organisationId);
        self::assertSame(34, $scope->brandId);
        $scope->assertWritableAttributes(['name' => 'Pensuh launch']);
    }

    #[DataProvider('invalidScopes')]
    public function test_rejects_invalid_scopes(int $organisationId, int $brandId): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BrandScope($organisationId, $brandId);
    }

    public static function invalidScopes(): iterable
    {
        yield 'zero organisation' => [0, 2];
        yield 'negative organisation' => [-1, 2];
        yield 'zero brand' => [1, 0];
        yield 'negative brand' => [1, -4];
    }

    #[DataProvider('protectedAttributes')]
    public function test_rejects_caller_supplied_identity_fields(string $field): void
    {
        $scope = new BrandScope(1, 2);
        $this->expectException(InvalidArgumentException::class);
        $scope->assertWritableAttributes([$field => null]);
    }

    public static function protectedAttributes(): iterable
    {
        foreach (['id', 'uuid', 'organisation_id', 'brand_id'] as $field) {
            yield $field => [$field];
        }
    }
}
