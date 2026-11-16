<?php

namespace Database\Factories;

use App\Enums\StateEnum;
use App\Models\Brand;
use App\Models\BrandMembership;
use App\Models\OrganisationMembership;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * @extends Factory<BrandMembership>
 * Always call forExistingOrganisationMember() and forBrand(); this preserves the org-member FK.
 */
final class BrandMembershipFactory extends Factory
{
    protected $model = BrandMembership::class;

    public function definition(): array
    {
        return ['state' => StateEnum::ACTIVE->value];
    }

    public function forExistingOrganisationMember(OrganisationMembership $member): static
    {
        return $this->state(fn (): array => [
            'organisation_id' => $member->organisation_id,
            'user_id' => $member->user_id,
            'role_id' => $member->role_id,
        ]);
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => [
            'organisation_id' => $brand->organisation_id,
            'brand_id' => $brand->getKey(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['state' => StateEnum::REVOKED->value]);
    }

    public function create($attributes = [], ?Model $parent = null)
    {
        $resolved = array_merge($this->make($attributes)->getAttributes(), $attributes);
        foreach (['organisation_id', 'brand_id', 'user_id', 'role_id'] as $key) {
            if (empty($resolved[$key])) {
                throw new LogicException("BrandMembershipFactory needs explicit {$key}; use forExistingOrganisationMember() and forBrand().");
            }
        }

        return parent::create($attributes, $parent);
    }
}
