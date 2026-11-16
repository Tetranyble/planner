<?php

namespace Database\Factories;

use App\Models\PlannerAgentWorkEvent;
use App\Models\PlannerAgentWorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlannerAgentWorkEvent> */
final class PlannerAgentWorkEventFactory extends Factory
{
    protected $model = PlannerAgentWorkEvent::class;

    /**
     * Factory defaults are safe fixture values. Scoped foreign keys are supplied
     * by the explicit parent state so database composite FK constraints hold.
     */
    public function definition(): array
    {
        return ['action' => 'CREATED', 'evidence' => [], 'created_at' => now()];
    }

    public function forWorkOrder(PlannerAgentWorkOrder $order): static
    {
        return $this->state(fn (): array => ['work_order_id' => $order->id]);
    }
}
