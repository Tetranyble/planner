<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Campaign> */
final class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    /** Scoped foreign keys are intentionally required via forBrand() or a more specific factory state. */
    public function definition(): array
    {
        return ['name' => fake()->sentence(3), 'objective' => 'AWARENESS', 'status' => 'DRAFT',
            'brief' => fake()->sentence(), 'budget_minor' => 0, 'currency' => 'NGN'];
    }

    public function forBrand(Brand $brand): static
    {
        return $this->state(fn () => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }
}
