<?php

namespace Database\Factories;

use App\Models\PublicationSandboxEvent;
use App\Models\PublicationSandboxRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PublicationSandboxEvent> */
final class PublicationSandboxEventFactory extends Factory
{
    protected $model = PublicationSandboxEvent::class;

    /**
     * Factory defaults are safe fixture values. Scoped foreign keys are supplied
     * by the explicit parent state so database composite FK constraints hold.
     */
    public function definition(): array
    {
        return ['event' => 'CREATED', 'safe_details' => [], 'created_at' => now()];
    }

    public function forRun(PublicationSandboxRun $run): static
    {
        return $this->state(fn (): array => ['publication_sandbox_run_id' => $run->id]);
    }
}
