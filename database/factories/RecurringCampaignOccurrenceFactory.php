<?php

namespace Database\Factories;

use App\Models\RecurringCampaignOccurrence;
use App\Models\RecurringSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<RecurringCampaignOccurrence> */
final class RecurringCampaignOccurrenceFactory extends Factory
{
    protected $model = RecurringCampaignOccurrence::class;

    /**
     * Factory defaults are safe fixture values. Scoped foreign keys are supplied
     * by the explicit parent state so database composite FK constraints hold.
     */
    public function definition(): array
    {
        return ['occurrence_key' => (string) Str::uuid(), 'local_at' => now()->addDay()->toDateTimeString(), 'run_at' => now()->addDay()];
    }

    public function forSchedule(RecurringSchedule $schedule): static
    {
        return $this->state(fn (): array => ['organisation_id' => $schedule->organisation_id, 'brand_id' => $schedule->brand_id, 'recurring_schedule_id' => $schedule->id]);
    }
}
