<?php

namespace Tests\Feature\Content;

use App\Models\Brand;
use App\Models\Organisation;
use App\Services\Publishing\ExecutePublicationPlans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicationExecutionEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_arm_request_is_rejected_before_job_lookup(): void
    {
        $organisation = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($organisation)->create();
        $this->postJson("/api/v1/organisations/{$organisation->uuid}/brands/{$brand->uuid}/content/publication-intents/1000/arm", [
            'expected_version' => 0, 'acknowledge_live_delivery' => true,
        ])->assertUnauthorized();
    }

    public function test_default_worker_does_not_mutate_or_send_jobs(): void
    {
        $this->assertFalse(ExecutePublicationPlans::isEnabled());
        $result = app(ExecutePublicationPlans::class)->process(10);
        $this->assertTrue($result['disabled']);
        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['claimed']);
    }
}
