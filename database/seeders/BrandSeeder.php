<?php

namespace Database\Seeders;

use App\Authorization\CurrentOrganisation;
use App\Enums\StateEnum;
use App\Models\Brand;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Membership\ManageBrandMembers;
use App\Services\Membership\OrganisationMembershipService;
use App\Services\Onboarding\CreateBrandService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;
use Tetranyble\Kinship\Models\Role;

/** Demonstrates a second user and two brands through real application workflows. */
final class BrandSeeder extends Seeder
{
    public function run(
        CreateBrandService $brands,
        OrganisationMembershipService $organisationMemberships,
        ManageBrandMembers $brandMembers,
        CurrentOrganisation $context,
    ): void {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo brand seeding is restricted to local/testing environments.');
        }
        $organisation = Organisation::query()->where('name', OrganisationSeeder::DEMO_ORG)->firstOrFail();
        $owner = User::query()->where('email', OrganisationSeeder::DEMO_EMAIL)->firstOrFail();
        $previous = auth()->user();
        auth()->setUser($owner);

        try {
            $context->within($owner, (int) $organisation->getKey(), function () use ($owner, $organisation, $brands, $organisationMemberships, $brandMembers): void {
                foreach (['Example Education Brand', 'Example Commerce Brand'] as $brandName) {
                    if (! Brand::query()->where('organisation_id', $organisation->getKey())
                        ->where('name', $brandName)->exists()) {
                        $brands->execute($owner, $organisation, $brandName, null, 'Africa/Lagos');
                    }
                }
                $collaborator = User::query()->where('email', 'planner-demo-manager@example.test')->first()
                    ?? User::factory()->create([
                        'name' => 'Example Manager', 'email' => 'planner-demo-manager@example.test',
                        'password' => Hash::make(Str::random(64)),
                    ]);
                $managerRole = Role::query()->forWorkspace((int) $organisation->getKey())
                    ->where('name', 'manager')->firstOrFail();
                if (! $organisation->users()->whereKey($collaborator->getKey())
                    ->wherePivot('state', StateEnum::ACTIVE->value)->exists()) {
                    $organisationMemberships->grant($organisation, $collaborator, $managerRole);
                }
                $exampleBrand = Brand::query()->where('organisation_id', $organisation->getKey())
                    ->where('name', 'Example Education Brand')->firstOrFail();
                if (! $exampleBrand->members()->whereKey($collaborator->getKey())
                    ->wherePivot('state', StateEnum::ACTIVE->value)->exists()) {
                    $brandMembers->add($owner, $organisation, $exampleBrand,
                        (int) $collaborator->getKey(), 'manager');
                }
            });
        } finally {
            if ($previous instanceof User) {
                auth()->setUser($previous);
            } else {
                auth()->forgetUser();
            }
        }
    }
}
