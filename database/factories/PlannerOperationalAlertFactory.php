<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerOperationalAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlannerOperationalAlert> */
final class PlannerOperationalAlertFactory extends Factory
{
    protected $model = PlannerOperationalAlert::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'brand_id' => fn (array $attributes) => Brand::factory()->forOrganisation(
                Organisation::query()->findOrFail($attributes['organisation_id']))->create()->id,
            'signal' => 'publication_uncertain', 'state' => 'OPEN', 'observed_count' => 1,
            'first_seen_at' => now(), 'last_seen_at' => now(), 'resolved_at' => null,
        ];
    }

    public function forScope(Organisation $org, Brand $brand): static
    {
        return $this->state(fn () => ['organisation_id' => $org->id, 'brand_id' => $brand->id]);
    }
}
