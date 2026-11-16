<?php

namespace Tests\Unit\Models;

use App\Enums\StatusEnum;
use App\Enums\TimeZone;
use App\Models\Brand;
use App\Models\Organisation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Schema
    // ---------------------------------------------------------------

    public function test_it_has_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('brands', [
            'id',
            'uuid',
            'organisation_id',
            'name',
            'website_url',
            'timezone',
            'status',
            'settings',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_uuid_is_unique(): void
    {
        $brand = Brand::factory()->create();

        $this->expectException(QueryException::class);

        Brand::factory()->create(['uuid' => $brand->uuid]);
    }

    public function test_same_brand_name_allowed_across_organisations(): void
    {
        $orgA = Organisation::factory()->create();
        $orgB = Organisation::factory()->create();

        Brand::factory()->forOrganisation($orgA)->create(['name' => 'Acme']);
        Brand::factory()->forOrganisation($orgB)->create(['name' => 'Acme']);

        $this->assertSame(2, Brand::where('name', 'Acme')->count());
    }

    public function test_deleting_organisation_with_brands_is_restricted(): void
    {
        $org = Organisation::factory()->create();
        Brand::factory()->forOrganisation($org)->create();

        $this->expectException(QueryException::class);

        $org->delete();
    }

    // ---------------------------------------------------------------
    // Model behavior
    // ---------------------------------------------------------------

    public function test_it_auto_generates_a_uuid_on_create(): void
    {
        $brand = Brand::factory()->create(['uuid' => null]);

        $this->assertNotNull($brand->uuid);
        $this->assertTrue(Str::isUuid($brand->uuid));
    }

    public function test_primary_key_is_incrementing_bigint(): void
    {
        $brand = Brand::factory()->create();

        $this->assertIsInt($brand->id);
        $this->assertTrue($brand->incrementing);
    }

    public function test_route_key_is_uuid(): void
    {
        $this->assertSame('uuid', (new Brand)->getRouteKeyName());
    }

    public function test_it_casts_enums_and_settings_json(): void
    {
        $brand = Brand::factory()
            ->withSettings(['theme' => 'dark', 'features' => ['a', 'b']])
            ->create();

        $this->assertInstanceOf(TimeZone::class, $brand->timezone);
        $this->assertInstanceOf(StatusEnum::class, $brand->status);
        $this->assertIsArray($brand->settings);
        $this->assertSame('dark', $brand->settings['theme']);
        $this->assertSame(['a', 'b'], $brand->settings['features']);
    }

    public function test_settings_is_nullable(): void
    {
        $brand = Brand::factory()->create(['settings' => null]);

        $this->assertNull($brand->fresh()->settings);
    }

    public function test_website_url_is_nullable(): void
    {
        $brand = Brand::factory()->withoutWebsite()->create();

        $this->assertNull($brand->website_url);
    }

    public function test_it_applies_default_attributes(): void
    {
        $brand = Brand::factory()->create([
            //            'timezone' => null,
            //            'status'   => null,
        ]);

        $fresh = $brand->fresh();

        $this->assertSame(TimeZone::AFRICA_LAGOS, $fresh->timezone);
        $this->assertSame(StatusEnum::ACTIVE, $fresh->status);
    }

    public function test_active_scope_filters_by_status(): void
    {
        Brand::factory()->count(2)->create();
        Brand::factory()->suspended()->create();
        Brand::factory()->closed()->create();

        $this->assertSame(2, Brand::active()->count());
        $this->assertSame(1, Brand::suspended()->count());
        $this->assertSame(1, Brand::closed()->count());
    }

    public function test_for_organisation_scope(): void
    {
        $orgA = Organisation::factory()->create();
        $orgB = Organisation::factory()->create();

        Brand::factory()->forOrganisation($orgA)->count(2)->create();
        Brand::factory()->forOrganisation($orgB)->count(3)->create();

        $this->assertSame(2, Brand::forOrganisation($orgA)->count());
        $this->assertSame(3, Brand::forOrganisation($orgB)->count());
    }

    public function test_is_active_helper(): void
    {
        $this->assertTrue(Brand::factory()->make(['status' => StatusEnum::ACTIVE->value])->isActive());
        $this->assertFalse(Brand::factory()->make(['status' => StatusEnum::SUSPENDED->value])->isActive());
    }

    public function test_relationship_to_organisation(): void
    {
        $org = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($org)->create();

        $this->assertTrue($brand->organisation->is($org));
        $this->assertTrue($org->brands->contains($brand));
    }
}
