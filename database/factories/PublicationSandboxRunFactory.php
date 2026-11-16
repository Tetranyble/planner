<?php

namespace Database\Factories;

use App\Models\PublicationSandboxRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PublicationSandboxRun> */
final class PublicationSandboxRunFactory extends Factory
{
    protected $model = PublicationSandboxRun::class;

    public function definition(): array
    {
        return ['operation_key' => 'sandbox:'.$this->faker->unique()->numerify('########'), 'provider' => 'facebook', 'scenario' => 'timeout', 'status' => 'QUEUED', 'attempt_count' => 0];
    }
}
