<?php

namespace Tests\Unit\Ai;

use App\Domain\Ai\ProviderEvidenceContext;
use App\Domain\Analytics\ProviderPerformanceSignals;
use PHPUnit\Framework\TestCase;

final class ProviderEvidenceContextTest extends TestCase
{
    public function test_it_requires_matched_provider_observations_and_stable_integrity(): void
    {
        $trend = ProviderPerformanceSignals::evaluate([[10, 12], [10, 14], [10, 11]], false, 'x');
        $evidence = ProviderEvidenceContext::fromSignals(['x' => $trend], ['x']);
        self::assertTrue($evidence['eligible']);
        self::assertSame('public_post_impressions', $evidence['snapshot']['observations'][0]['metric']);
        self::assertSame($evidence['sha256'], ProviderEvidenceContext::digest($evidence['snapshot']));
        self::assertFalse($evidence['snapshot']['publishing_authorized']);
    }
}
