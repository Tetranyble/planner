<?php

namespace Tests\Unit\Publishing;

use App\Domain\Publishing\Delivery\DispatchEnvelope;
use App\Domain\Publishing\Delivery\MockHttpExchange;
use App\Domain\Publishing\Delivery\MockProviderRequestCompiler;
use App\Domain\Publishing\Delivery\OfflineMockDeliveryHarness;
use PHPUnit\Framework\TestCase;

final class OfflineMockProviderTransportTest extends TestCase
{
    public function test_all_six_providers_and_seven_replies_are_offline(): void
    {
        foreach (['facebook', 'instagram', 'linkedin', 'x', 'tiktok', 'youtube'] as $provider) {
            foreach (['confirmed', 'accepted_no_receipt', 'rate_limited', 'rejected', 'server_error', 'timeout', 'failed_before_send'] as $fixture) {
                $r = OfflineMockDeliveryHarness::run($provider, $fixture);
                self::assertSame(0, $r['network_requests']);
                self::assertSame(0, $r['live_publications']);
                self::assertFalse($r['safety_gate']['may_dispatch']);
                self::assertSame(1, $r['mock_exchanges']);
                self::assertStringStartsWith('/mock/v1/', $r['mock_request']['path']);
                if (in_array($fixture, ['timeout', 'server_error', 'accepted_no_receipt'], true)) {
                    self::assertSame('QUARANTINE', $r['retry_policy']['action']);
                }
            }
        }
    }

    public function test_media_is_provider_constrained(): void
    {
        $this->expectException(\DomainException::class);
        MockProviderRequestCompiler::compile(new DispatchEnvelope('instagram', 'mock:operation:0001', 'TEXT', str_repeat('a', 64), 'MOCK_TARGET'));
    }

    public function test_external_url_cannot_be_submitted_to_mock_transport(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MockHttpExchange('facebook', 'POST', 'https://graph.facebook.com', ['Content-Type' => 'application/json'], []);
    }
}
