<?php

namespace Database\Factories;

use App\Models\ApprovalRequest;
use App\Models\Brand;
use App\Models\ContentTarget;
use App\Models\PublicationJob;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PublicationJob>
 */
class PublicationJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'occurrence_key' => 'fixture-'.Str::random(16),
            'run_at' => now(),
        ];
    }

    /** Use a saved brand so its organisation and brand ids cannot diverge. */
    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }

    public function forApprovedTarget(ContentTarget $target, ApprovalRequest $approval): static
    {
        if ((int) $target->organisation_id !== (int) $approval->organisation_id ||
            (int) $target->brand_id !== (int) $approval->brand_id) {
            throw new \InvalidArgumentException('Approval and target must belong to the same brand.');
        }

        return $this->state(fn (): array => ['organisation_id' => $target->organisation_id,
            'brand_id' => $target->brand_id, 'content_target_id' => $target->id,
            'approval_request_id' => $approval->id, 'run_at' => now()->addHour()]);
    }
}
