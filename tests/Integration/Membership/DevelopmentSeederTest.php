<?php

namespace Tests\Integration\Membership;

use App\Models\Brand;
use App\Models\BrandMembership;
use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\OrganisationSeeder;
use Database\Seeders\PlannerDevelopmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DevelopmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_is_idempotent_and_creates_scoped_manager_access(): void
    {
        $this->seed(PlannerDevelopmentSeeder::class);
        $this->seed(PlannerDevelopmentSeeder::class);
        $org = Organisation::query()->where('name', OrganisationSeeder::DEMO_ORG)->sole();
        $this->assertSame(2, Brand::query()->where('organisation_id', $org->getKey())->count());
        $this->assertSame(1, User::query()->where('email', OrganisationSeeder::DEMO_EMAIL)->count());
        $this->assertSame(1, User::query()->where('email', 'planner-demo-manager@example.test')->count());
        $manager = User::query()->where('email', 'planner-demo-manager@example.test')->sole();
        $brand = Brand::query()->where('organisation_id', $org->id)
            ->where('name', 'Example Education Brand')->sole();
        $this->assertSame(1, BrandMembership::query()->where('organisation_id', $org->id)
            ->where('brand_id', $brand->id)->where('user_id', $manager->id)->count());
    }
}
