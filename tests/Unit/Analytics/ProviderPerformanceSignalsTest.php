<?php

namespace Tests\Unit\Analytics;

use App\Domain\Analytics\ProviderPerformanceSignals;
use PHPUnit\Framework\TestCase;

final class ProviderPerformanceSignalsTest extends TestCase
{
    public function test_no_statistical_claim_with_fewer_than_three_matched_videos(): void
    {
        $result = ProviderPerformanceSignals::evaluate([[10, 20], [20, 30]]);
        self::assertSame('INSUFFICIENT_BASELINES', $result['status']);
        self::assertNull($result['change_percent']);
        self::assertNull($result['views']);
    }

    public function test_comparison_is_same_video_counts_not_campaign_roi(): void
    {
        $result = ProviderPerformanceSignals::evaluate([[10, 20], [20, 30], [30, 40]]);
        self::assertSame(60, $result['views']['baseline']);
        self::assertSame(90, $result['views']['latest']);
        self::assertSame(50.0, $result['change_percent']);
        self::assertContains('roi', $result['not_measured']);
    }
}
