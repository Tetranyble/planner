<?php

namespace Database\Factories;

use App\Models\PlannerAgentAutonomousEvent;
use App\Models\PlannerAgentAutonomousRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlannerAgentAutonomousEvent> */
final class PlannerAgentAutonomousEventFactory extends Factory
{
    protected $model = PlannerAgentAutonomousEvent::class;

    /**
     * Factory defaults are safe fixture values. Scoped foreign keys are supplied
     * by the explicit parent state so database composite FK constraints hold.
     */
    public function definition(): array
    {
        return ['event' => 'RESEARCH_QUEUED', 'evidence' => [], 'created_at' => now()];
    }

    public function forRun(PlannerAgentAutonomousRun $run): static
    {
        return $this->state(fn (): array => ['run_id' => $run->id]);
    }
}
