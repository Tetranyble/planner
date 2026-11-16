<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Lead;
use App\Models\LeadContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadContact>
 */
class LeadContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return []; // All columns are scoped foreign keys; set them with forBrand()/forOrganisation() or state().
    }

    /** Use a saved brand so its organisation and brand ids cannot diverge. */
    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }

    public function forLead(Lead $lead): static
    {
        return $this->state(fn (): array => ['organisation_id' => $lead->organisation_id,
            'brand_id' => $lead->brand_id, 'lead_id' => $lead->id, 'full_name' => 'Test contact']);
    }
}
