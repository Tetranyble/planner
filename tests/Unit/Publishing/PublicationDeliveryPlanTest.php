<?php

namespace Tests\Unit\Publishing;

use App\Domain\Publishing\PublicationDeliveryPlan;
use PHPUnit\Framework\TestCase;

final class PublicationDeliveryPlanTest extends TestCase
{
    public function test_every_provider_is_fail_closed_and_offline(): void
    {
        foreach (['facebook', 'instagram', 'linkedin', 'x', 'tiktok', 'youtube'] as $provider) {
            $result = PublicationDeliveryPlan::preview($provider, 'VIDEO', true, true);
            $this->assertSame(0, $result['network_requests']);
            $this->assertFalse($result['may_dispatch']);
            $this->assertContains('REMOTE_PUBLISHING_NOT_ENABLED', $result['checks']);
            $this->assertNotNull($result['required_capability']);
        }
    }

    public function test_unknown_provider_and_missing_inputs_fail_closed(): void
    {
        $result = PublicationDeliveryPlan::preview('unknown', 'TEXT', false, false);
        $this->assertContains('UNKNOWN_PROVIDER', $result['checks']);
        $this->assertContains('MISSING_CREDENTIAL', $result['checks']);
        $this->assertContains('MISSING_APPROVAL', $result['checks']);
    }
}
