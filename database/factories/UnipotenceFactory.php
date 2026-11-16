<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Unipotence\Infrastructure\Persistence\Models\Unipotence;

/** @extends Factory<Unipotence> */
final class UnipotenceFactory extends Factory
{
    protected $model = Unipotence::class;

    /**
     * Factory defaults are safe fixture values. Scoped foreign keys are supplied
     * by the explicit parent state so database composite FK constraints hold.
     */
    public function definition(): array
    {
        return ['scope' => 'fixture:'.Str::random(8), 'key' => Str::random(24), 'request_hash' => str_repeat('d', 64), 'claim_token' => (string) Str::uuid(), 'state' => 'claimed', 'locked_until' => now()->addMinutes(5)];
    }
}
