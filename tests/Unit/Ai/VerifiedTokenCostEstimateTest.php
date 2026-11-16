<?php

namespace Tests\Unit\Ai;

use App\Domain\Ai\VerifiedTokenCostEstimate;
use PHPUnit\Framework\TestCase;

final class VerifiedTokenCostEstimateTest extends TestCase
{
    public function test_pricing_requires_explicit_integer_evidence_revision_and_model_rate(): void
    {
        $rates = ['test-model' => ['input_micro_usd_per_million' => 1000000, 'output_micro_usd_per_million' => 3000000]];
        $result = VerifiedTokenCostEstimate::calculate('test-model', ['input_tokens' => 200, 'output_tokens' => 100], $rates, 'internal-2026-11');
        self::assertSame(500, $result['micro_usd']);
        self::assertSame('internal-2026-11', $result['revision']);
        self::assertNull(VerifiedTokenCostEstimate::calculate('unknown', ['input_tokens' => 200, 'output_tokens' => 100], $rates, 'v1'));
        self::assertNull(VerifiedTokenCostEstimate::calculate('test-model', ['input_tokens' => 200], $rates, 'v1'));
        self::assertNull(VerifiedTokenCostEstimate::calculate('test-model', ['input_tokens' => -1, 'output_tokens' => 1], $rates, 'v1'));
        self::assertNull(VerifiedTokenCostEstimate::calculate('test-model', ['input_tokens' => '10', 'output_tokens' => 1], $rates, 'v1'));
        self::assertNull(VerifiedTokenCostEstimate::calculate('test-model', ['input_tokens' => 1, 'output_tokens' => 1], $rates, ''));
    }

    public function test_cost_overflow_fails_closed(): void
    {
        $rates = ['model' => ['input_micro_usd_per_million' => 100000000000, 'output_micro_usd_per_million' => 100000000000]];
        self::assertNull(VerifiedTokenCostEstimate::calculate('model', [
            'input_tokens' => 100000000000, 'output_tokens' => 100000000000], $rates, 'v2'));
    }
}
