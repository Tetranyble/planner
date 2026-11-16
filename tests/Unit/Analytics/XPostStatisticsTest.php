<?php

namespace Tests\Unit\Analytics;

use App\Domain\Analytics\Provider\XPostStatistics;
use PHPUnit\Framework\TestCase;

final class XPostStatisticsTest extends TestCase
{
    public function test_provider_identity_and_null_semantics(): void
    {
        $x = XPostStatistics::normalize(['data' => ['id' => '1923456789012345678', 'author_id' => '12345678901234567',
            'public_metrics' => ['like_count' => 0, 'reply_count' => null]]],
            '1923456789012345678', '12345678901234567');
        self::assertSame(0, $x['likes']);
        self::assertNull($x['comments']);
        self::assertNull($x['views']);
        self::assertSame(64, strlen($x['sha256']));
    }

    public function test_mismatched_author_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        XPostStatistics::normalize(['data' => ['id' => '123456789', 'author_id' => '999',
            'public_metrics' => ['like_count' => 3]]], '123456789', '111');
    }
}
