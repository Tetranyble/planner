<?php

namespace Database\Factories;

use App\Models\PlannerAgentActionReceipt;
use App\Models\PlannerAgentDelegation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PlannerAgentActionReceipt> */
final class PlannerAgentActionReceiptFactory extends Factory
{
    protected $model = PlannerAgentActionReceipt::class;

    /**
     * Factory defaults are safe fixture values. Scoped foreign keys are supplied
     * by the explicit parent state so database composite FK constraints hold.
     */
    public function definition(): array
    {
        return ['action' => 'RESEARCH', 'idempotency_key' => 'fixture-'.Str::random(24), 'input_hash' => str_repeat('c', 64), 'result' => ['fixture' => true]];
    }

    public function forDelegation(PlannerAgentDelegation $delegation): static
    {
        return $this->state(fn (): array => ['organisation_id' => $delegation->organisation_id, 'brand_id' => $delegation->brand_id, 'delegation_id' => $delegation->id]);
    }
}
