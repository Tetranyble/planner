<?php

namespace Tests\Unit\Models;

use App\Models\ApiRateLimitBucket;
use App\Models\Brand;
use App\Models\Country;
use App\Models\CreativeImageHandoff;
use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A few real MySQL FK and bidirectional relationship checks. The source-only
 * contract covers all other model declarations without repeatedly migrating.
 */
final class ModelRelationshipMySqlTest extends TestCase
{
    use RefreshDatabase;

    public function test_creative_handoff_factory_respects_uuid_and_both_tenant_relationships(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();

        $handoff = CreativeImageHandoff::factory()->forBrand($brand, $actor)
            ->create(['uuid' => null]);

        self::assertTrue(Str::isUuid($handoff->uuid));
        self::assertTrue($handoff->organisation->is($org));
        self::assertTrue($handoff->brand->is($brand));
        self::assertTrue($handoff->actorUser->is($actor));
        self::assertTrue($brand->creativeImageHandoffs->contains($handoff));
        self::assertTrue($org->creativeImageHandoffs->contains($handoff));
        self::assertTrue($actor->creativeImageHandoffs->contains($handoff));
    }

    public function test_creative_handoff_rejects_cross_tenant_brand_assignment(): void
    {
        $org = Organisation::factory()->create();
        $otherOrg = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();
        $actor = User::factory()->create();

        $this->expectException(QueryException::class);
        CreativeImageHandoff::factory()->forBrand($brand, $actor)
            ->create(['organisation_id' => $otherOrg->id]);
    }

    public function test_api_rate_limit_bucket_factory_uses_persisted_organisation(): void
    {
        $org = Organisation::factory()->create();
        $bucket = ApiRateLimitBucket::factory()->forOrganisation($org)->create();

        self::assertTrue($bucket->organisation->is($org));
        self::assertTrue($org->apiRateLimitBuckets->contains($bucket));
    }

    public function test_lead_and_contact_factories_preserve_brand_and_organisation_scope(): void
    {
        $organisation = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($organisation)->create();
        $country = Country::factory()->create();
        $lead = Lead::factory()->forBrandAndCountry($brand, $country)->create();
        $contact = LeadContact::factory()->forLead($lead)->create();

        self::assertTrue($lead->organisation->is($organisation));
        self::assertTrue($lead->brand->is($brand));
        self::assertTrue($contact->lead->is($lead));
        self::assertTrue($lead->contacts->contains($contact));
        self::assertTrue($brand->leads->contains($lead));
    }
}
