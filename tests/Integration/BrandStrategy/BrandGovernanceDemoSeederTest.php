<?php

namespace Tests\Integration\BrandStrategy;

use App\Models\Brand;
use App\Models\BrandClaim;
use App\Models\BrandClaimEvidence;
use App\Models\BrandKnowledgeSource;
use Database\Seeders\BrandGovernanceDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ensure demos are explicitly selected, idempotent and cannot create approved marketing claims. */
final class BrandGovernanceDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_opt_in_demo_seeder_is_idempotent_and_never_creates_trusted_claims(): void
    {
        $brand = Brand::factory()->create();
        putenv('PLANNER_DEMO_BRAND_UUID='.$brand->uuid);
        $_ENV['PLANNER_DEMO_BRAND_UUID'] = $brand->uuid;
        try {
            $this->seed(BrandGovernanceDemoSeeder::class);
            $this->seed(BrandGovernanceDemoSeeder::class);
            self::assertSame(1, BrandKnowledgeSource::query()->where('brand_id', $brand->id)->count());
            self::assertSame(1, BrandClaim::query()->where('brand_id', $brand->id)->count());
            self::assertSame(1, BrandClaimEvidence::query()->where('brand_id', $brand->id)->count());
            self::assertSame('PENDING', BrandKnowledgeSource::query()->where('brand_id', $brand->id)->firstOrFail()->trust_status);
            self::assertSame('DRAFT', BrandClaim::query()->where('brand_id', $brand->id)->firstOrFail()->status);
        } finally {
            putenv('PLANNER_DEMO_BRAND_UUID');
            unset($_ENV['PLANNER_DEMO_BRAND_UUID']);
        }
    }
}
