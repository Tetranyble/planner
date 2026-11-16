<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ContentTarget;
use App\Models\RecurringSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringSchedule>
 */
class RecurringScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rrule' => 'FREQ=DAILY;INTERVAL=1',
            'start_local' => now(),
        ];
    }

    /** Use a saved brand so its organisation and brand ids cannot diverge. */
    public function forBrand(Brand $brand): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id]);
    }

    public function forTarget(ContentTarget $target, ?User $actor = null): static
    {
        return $this->state(fn (): array => ['organisation_id' => $target->organisation_id,
            'brand_id' => $target->brand_id, 'content_target_id' => $target->id,
            'created_by_user_id' => $actor?->id]);
    }
}
