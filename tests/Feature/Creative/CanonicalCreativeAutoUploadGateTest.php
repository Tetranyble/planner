<?php

namespace Tests\Feature\Creative;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Ensures release switches block paid provider requests before they happen. */
final class CanonicalCreativeAutoUploadGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_automatic_storage_ingest_rejected_before_provider_call_if_unverified(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        config()->set('planner.creative_image_enabled', true);
        config()->set('planner.creative_auto_storage_enabled', false);
        config()->set('planner.storage_v3_adapter_verified', true);
        Http::fake();
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => 'Creative Upload Gate'])->assertCreated();
        $org = Organisation::query()->where('name', 'Creative Upload Gate')->firstOrFail();
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/brands", ['name' => 'Creative Upload Brand'])->assertCreated();
        $brand = Brand::query()->where('organisation_id', $org->id)->firstOrFail();
        $this->actingAs($owner)->postJson("/organisations/{$org->uuid}/brands/{$brand->uuid}/creative/private-preview", [
            'prompt' => 'Product landing page visual with warm colors and original graphics',
            'size' => '1024x1024', 'auto_store' => true,
        ])->assertForbidden();
        Http::assertNothingSent();
        $this->assertDatabaseCount('creative_image_handoffs', 0);
    }
}
