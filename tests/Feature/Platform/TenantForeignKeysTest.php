<?php

namespace Tests\Feature\Platform;

use App\Models\Brand;
use App\Models\Organisation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantForeignKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_profile_cannot_reference_a_brand_in_another_organisation(): void
    {
        $first = Organisation::factory()->create();
        $second = Organisation::factory()->create();
        $foreignBrand = Brand::factory()->forOrganisation($second)->create();

        $this->expectException(QueryException::class);
        DB::table('brand_profiles')->insert([
            'uuid' => (string) Str::uuid(),
            'organisation_id' => $first->id,
            'brand_id' => $foreignBrand->id,
        ]);
    }

    public function test_social_account_cannot_borrow_other_organisation_provider_connection(): void
    {
        $first = Organisation::factory()->create();
        $second = Organisation::factory()->create();
        $brand = Brand::factory()->forOrganisation($first)->create();
        $externalConnection = DB::table('provider_connections')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'organisation_id' => $second->id,
            'provider' => 'facebook',
            'access_token_secret_ref' => 'secret:fake-access',
            'refresh_token_secret_ref' => 'secret:fake-refresh',
        ]);

        $this->expectException(QueryException::class);
        DB::table('social_accounts')->insert([
            'uuid' => (string) Str::uuid(),
            'organisation_id' => $first->id,
            'brand_id' => $brand->id,
            'provider_connection_id' => $externalConnection,
            'provider' => 'facebook',
            'external_account_id' => 'page-123',
            'account_kind' => 'PAGE',
            'display_name' => 'Test page',
        ]);
    }
}
