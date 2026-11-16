<?php

namespace Database\Factories;

use App\Models\PlannerOperationalAlert;
use App\Models\PlannerOperationalAlertEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlannerOperationalAlertEvent> */
final class PlannerOperationalAlertEventFactory extends Factory
{
    protected $model = PlannerOperationalAlertEvent::class;

    /**
     * Factory defaults are safe fixture values. Scoped foreign keys are supplied
     * by the explicit parent state so database composite FK constraints hold.
     */
    public function definition(): array
    {
        return ['event_type' => 'OBSERVED', 'observed_count' => 1, 'observed_at' => now()];
    }

    public function forAlert(PlannerOperationalAlert $alert): static
    {
        return $this->state(fn (): array => ['organisation_id' => $alert->organisation_id, 'brand_id' => $alert->brand_id, 'planner_operational_alert_id' => $alert->id]);
    }
}
