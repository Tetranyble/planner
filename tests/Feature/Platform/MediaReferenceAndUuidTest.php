<?php

namespace Tests\Feature\Platform;

use App\Models\Brand;
use App\Models\BrandProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaReferenceAndUuidTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_media_reference_is_available_without_a_duplicate_upload_system(): void
    {
        $this->assertTrue(Schema::hasColumn('media_assets', 'storage_media_id'));
    }

    public function test_brand_profile_generates_uuid_on_insert(): void
    {
        $brand = Brand::factory()->create();
        $profile = new BrandProfile;
        $profile->organisation_id = $brand->organisation_id;
        $profile->brand_id = $brand->id;
        $profile->save();

        $this->assertTrue(Str::isUuid($profile->uuid));
    }
}
