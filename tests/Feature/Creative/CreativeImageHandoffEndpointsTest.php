<?php

namespace Tests\Feature\Creative;

use App\Models\Brand;
use App\Models\CreativeImageHandoff;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\CreativeImageHandoffRepositoryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Requires local Laravel + disposable MySQL + Kinship runtime (NOT executed in source environment). */
final class CreativeImageHandoffEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(User $owner, string $name): array
    {
        $this->actingAs($owner)->postJson('/api/v1/organisations', ['name' => $name])->assertCreated();
        $org = Organisation::query()->where('name', $name)->firstOrFail();
        $this->actingAs($owner)->postJson("/api/v1/organisations/{$org->uuid}/brands", ['name' => $name.' Brand'])->assertCreated();
        $brand = Brand::query()->where('organisation_id', $org->id)->firstOrFail();

        return [$org, $brand];
    }

    private function claim(User $actor, Organisation $org, Brand $brand): CreativeImageHandoff
    {
        return app(CreativeImageHandoffRepositoryContract::class)->issue($actor, $org, $brand, [
            'sha256' => str_repeat('a', 64), 'bytes' => 'synthetic-binary-only-in-memory',
            'width' => 1024, 'height' => 1024,
        ], 60);
    }

    public function test_expired_handoff_is_rejected_without_importing_storage_asset(): void
    {
        config()->set('planner.creative_image_enabled', true);
        $owner = User::factory()->create();
        [$org, $brand] = $this->workspace($owner, 'Creative Handoff Owner');
        $ticket = $this->claim($owner, $org, $brand);
        $ticket->forceFill(['expires_at' => now()->subSecond()])->save();
        $url = "/organisations/{$org->uuid}/brands/{$brand->uuid}/creative/handoffs/{$ticket->uuid}/link";
        $this->actingAs($owner)->postJson($url, ['storage_media_id' => 42, 'reviewed' => true])->assertStatus(409);
        $this->assertDatabaseMissing('creative_image_handoffs', ['uuid' => $ticket->uuid, 'state' => 'LINKED']);
    }

    public function test_operator_review_required_and_unknown_storage_cannot_be_claimed(): void
    {
        config()->set('planner.creative_image_enabled', true);
        $owner = User::factory()->create();
        [$org, $brand] = $this->workspace($owner, 'Creative Handoff Review');
        $ticket = $this->claim($owner, $org, $brand);
        $url = "/organisations/{$org->uuid}/brands/{$brand->uuid}/creative/handoffs/{$ticket->uuid}/link";
        $this->actingAs($owner)->postJson($url, ['storage_media_id' => 999999])->assertUnprocessable()
            ->assertJsonValidationErrors('reviewed');
        $this->actingAs($owner)->postJson($url, ['storage_media_id' => 999999, 'reviewed' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('storage_media_id');
        $this->assertSame('AWAITING_UPLOAD', $ticket->fresh()->state);
    }

    public function test_claim_cannot_be_accessed_by_another_organisation(): void
    {
        config()->set('planner.creative_image_enabled', true);
        $first = User::factory()->create();
        $other = User::factory()->create();
        [$org, $brand] = $this->workspace($first, 'Creative Handoff Tenant A');
        $claim = $this->claim($first, $org, $brand);
        [$foreignOrg, $foreignBrand] = $this->workspace($other, 'Creative Handoff Tenant B');
        $url = "/organisations/{$foreignOrg->uuid}/brands/{$foreignBrand->uuid}/creative/handoffs/{$claim->uuid}/link";
        $this->actingAs($other)->postJson($url, ['storage_media_id' => 42, 'reviewed' => true])->assertNotFound();
        $this->assertSame('AWAITING_UPLOAD', $claim->fresh()->state);
    }
}
