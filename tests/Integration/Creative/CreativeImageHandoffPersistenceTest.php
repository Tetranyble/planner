<?php

namespace Tests\Integration\Creative;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\CreativeImageHandoffRepositoryContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Run only on disposable MySQL with migrated tables and installed Storage/Kinship. */
final class CreativeImageHandoffPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function image(): array
    {
        return ['sha256' => str_repeat('c', 64), 'bytes' => 'raw-png-test-fixture',
            'width' => 1024, 'height' => 1024];
    }

    public function test_metadata_ticket_is_scoped_to_exact_actor_brand_and_organisation_without_image_bytes(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $secondBrand = Brand::factory()->forOrganisation($org)->create();
        $repo = app(CreativeImageHandoffRepositoryContract::class);
        $ticket = $repo->issue($actor, $org, $brand, $this->image(), 60);
        $this->assertSame('AWAITING_UPLOAD', $ticket->state);
        $this->assertSame(1024, $ticket->width);
        $this->assertSame('image/png', $ticket->mime_type);
        $this->assertSame($ticket->id, $repo->lockOwned($actor, $org, $brand, $ticket->uuid)->id);
        $this->assertDatabaseMissing('creative_image_handoffs', ['uuid' => $ticket->uuid,
            'sha256' => 'raw-png-test-fixture']);
        $this->assertFalse(str_contains(json_encode($ticket->getAttributes()), 'raw-png-test-fixture'));
        foreach ([[$other, $org, $brand], [$actor, $org, $secondBrand]] as $scope) {
            try {
                $repo->lockOwned(...[...$scope, $ticket->uuid]);
                $this->fail('Foreign actor or brand accessed handoff.');
            } catch (ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
    }
}
