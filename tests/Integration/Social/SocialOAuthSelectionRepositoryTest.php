<?php

namespace Tests\Integration\Social;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\SocialOAuthSelection;
use App\Models\User;
use App\Repository\Contracts\SocialOAuthSelectionRepositoryContract;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Real MySQL contract tests: never use DB facade for fixtures. */
final class SocialOAuthSelectionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_builds_scoped_encrypted_pending_grant(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $user = User::factory()->create();
        $row = SocialOAuthSelection::factory()->forBrandAndActor($brand, $user)->create();
        $this->assertSame($org->id, $row->organisation_id);
        $this->assertSame($brand->id, $row->brand_id);
        $this->assertStringNotContainsString('synthetic-access-token', $row->grant_ciphertext);
        $this->assertStringNotContainsString('grant_ciphertext', $row->toJson());
        $this->assertCount(2, app(SocialOAuthSelectionRepositoryContract::class)->accounts($row));
    }

    public function test_pending_selection_cannot_reference_another_tenants_brand(): void
    {
        $brand = Brand::factory()->create();
        $otherOrg = Organisation::factory()->create();
        $user = User::factory()->create();
        $this->expectException(QueryException::class);
        SocialOAuthSelection::factory()->forBrandAndActor($brand, $user)
            ->create(['organisation_id' => $otherOrg->id]);
    }

    public function test_expired_and_consumed_selections_are_pruned_without_deleting_live_grants(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $user = User::factory()->create();
        SocialOAuthSelection::factory()->forBrandAndActor($brand, $user)
            ->create(['expires_at' => now()->subHours(2)]);
        $active = SocialOAuthSelection::factory()->forBrandAndActor($brand, $user)->create();
        $pruned = app(SocialOAuthSelectionRepositoryContract::class)->pruneExpired();
        $this->assertSame(1, $pruned);
        $this->assertModelExists($active);
        $this->assertSame(1, SocialOAuthSelection::query()->count());
    }
}
