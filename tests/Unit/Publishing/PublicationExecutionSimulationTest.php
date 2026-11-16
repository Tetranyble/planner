<?php

namespace Tests\Unit\Publishing;

use App\Domain\Publishing\Delivery\DispatchEnvelope;
use App\Domain\Publishing\Delivery\DispatchSafetyGate;
use App\Domain\Publishing\Delivery\ProviderFailureClassifier;
use App\Domain\Publishing\Delivery\RetrySafetyPolicy;
use App\Domain\Publishing\Delivery\SimulationAdapter;
use App\Domain\Publishing\Delivery\SimulationHarness;
use PHPUnit\Framework\TestCase;

final class PublicationExecutionSimulationTest extends TestCase
{
    public function test_all_six_networks_and_scenarios_are_offline(): void
    {
        foreach (['facebook', 'instagram', 'linkedin', 'x', 'tiktok', 'youtube'] as $provider) {
            foreach (SimulationHarness::SCENARIOS as $scenario) {
                $result = SimulationHarness::run($provider, $scenario);
                self::assertFalse($result['gate']['may_dispatch']);
                self::assertSame(0, $result['network_requests']);
                self::assertSame(0, $result['database_writes']);
            }
        }
    }

    public function test_ambiguous_provider_failure_never_retries(): void
    {
        foreach ([null, 408, 503] as $status) {
            $outcome = ProviderFailureClassifier::classify($status, true);
            self::assertSame('UNCERTAIN', $outcome->kind);
            self::assertSame('QUARANTINE', RetrySafetyPolicy::decision($outcome, 1, 5, true)['action']);
        }
    }

    public function test_429_requires_verified_idempotency_and_uses_retry_after(): void
    {
        $rate = ProviderFailureClassifier::classify(429, true, 600);
        self::assertSame('HOLD_FOR_REVIEW', RetrySafetyPolicy::decision($rate, 1, 5, false)['action']);
        $decision = RetrySafetyPolicy::decision($rate, 1, 5, true);
        self::assertSame('RETRY_LATER', $decision['action']);
        self::assertGreaterThanOrEqual(600, $decision['delay_seconds']);
    }

    public function test_2xx_without_receipt_is_ambiguous(): void
    {
        self::assertSame('UNCERTAIN', ProviderFailureClassifier::classify(202, true)->kind);
    }

    public function test_even_all_true_facts_cannot_enable_live_delivery(): void
    {
        $facts = array_fill_keys(['remote_kill_switch_off', 'provider_validated', 'scope_validated', 'account_active', 'credential_healthy', 'version_approved', 'policy_current', 'media_verified', 'operation_key_persisted', 'idempotency_supported', 'lease_owned', 'run_due'], true);
        self::assertFalse(DispatchSafetyGate::evaluate($facts)['may_dispatch']);
    }

    public function test_provider_mismatch_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        (new SimulationAdapter('x', 'success'))->dispatch(new DispatchEnvelope('facebook','simulation:00000001','TEXT',str_repeat('a',64),'TARGET'));
    }
}
