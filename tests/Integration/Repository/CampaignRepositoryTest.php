<?php

namespace Tests\Integration\Repository;

use App\Models\Brand;
use App\Models\Organisation;
use App\Repository\Contracts\CampaignRepositoryContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/** Requires a dedicated MySQL testing database (configured externally). */
final class CampaignRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_retrieval_pagination_and_update_are_scoped_to_both_dimensions(): void
    {
        $orgA = Organisation::factory()->create();
        $orgB = Organisation::factory()->create();
        $brandA = Brand::factory()->forOrganisation($orgA)->create();
        $brandB = Brand::factory()->forOrganisation($orgA)->create();
        $brandC = Brand::factory()->forOrganisation($orgB)->create();
        $repository = app(CampaignRepositoryContract::class);

        $campaign = $repository->createForBrand($orgA->id, $brandA->id, ['name' => 'Campaign A']);
        $repository->createForBrand($orgA->id, $brandB->id, ['name' => 'Campaign B']);
        $repository->createForBrand($orgB->id, $brandC->id, ['name' => 'Campaign C']);

        $this->assertSame(1, $repository->paginateForBrand($orgA->id, $brandA->id)->total());
        $this->assertSame($campaign->id, $repository->findForBrand($orgA->id, $brandA->id, $campaign->id)?->id);
        $this->assertNull($repository->findForBrand($orgA->id, $brandB->id, $campaign->id));
        $this->assertNull($repository->findForBrand($orgB->id, $brandC->id, $campaign->id));
        $this->assertNull($repository->findForBrand($orgB->id, $brandA->id, $campaign->id));

        $saved = $repository->updateForBrand($orgA->id, $brandA->id, $campaign->id, ['name' => 'Updated']);
        $this->assertSame('Updated', $saved->name);
        $this->assertSame($orgA->id, $saved->organisation_id);
        $this->assertSame($brandA->id, $saved->brand_id);
    }

    public function test_cross_brand_update_is_rejected_even_with_known_row_id(): void
    {
        $org = Organisation::factory()->create();
        $a = Brand::factory()->forOrganisation($org)->create();
        $b = Brand::factory()->forOrganisation($org)->create();
        $repository = app(CampaignRepositoryContract::class);
        $campaign = $repository->createForBrand($org->id, $a->id, ['name' => 'Protected']);

        try {
            $repository->updateForBrand($org->id, $b->id, $campaign->id, ['name' => 'Wrong']);
            $this->fail('Cross-brand update should not succeed.');
        } catch (ModelNotFoundException) {
            $this->assertSame('Protected', $campaign->fresh()->name);
        }
    }

    public function test_cross_organisation_delete_is_rejected(): void
    {
        $a = Organisation::factory()->create();
        $b = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($a)->create();
        $repository = app(CampaignRepositoryContract::class);
        $campaign = $repository->createForBrand($a->id, $brand->id, ['name' => 'Protected']);

        $this->expectException(ModelNotFoundException::class);
        $repository->deleteForBrand($b->id, $brand->id, $campaign->id);
    }

    public function test_unknown_writable_attribute_is_rejected(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();

        $this->expectException(InvalidArgumentException::class);
        app(CampaignRepositoryContract::class)->createForBrand(
            $org->id, $brand->id, ['name' => 'Allowed', 'unexpected' => 'not allowed']
        );
    }

    public function test_cross_brand_delete_does_not_remove_record(): void
    {
        $org = Organisation::factory()->create();
        $a = Brand::factory()->forOrganisation($org)->create();
        $b = Brand::factory()->forOrganisation($org)->create();
        $repo = app(CampaignRepositoryContract::class);
        $campaign = $repo->createForBrand($org->id, $a->id, ['name' => 'Keep me']);

        try {
            $repo->deleteForBrand($org->id, $b->id, $campaign->id);
            $this->fail('Must not delete another brand\'s campaign.');
        } catch (ModelNotFoundException) {
            $this->assertNotNull($campaign->fresh());
        }
    }

    public function test_caller_cannot_override_scope_or_primary_key(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $repository = app(CampaignRepositoryContract::class);

        $this->expectException(InvalidArgumentException::class);
        $repository->createForBrand($org->id, $brand->id, [
            'name' => 'Spoofed', 'organisation_id' => $org->id + 100,
        ]);
    }
}
