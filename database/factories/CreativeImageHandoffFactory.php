<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\CreativeImageHandoff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CreativeImageHandoff> */
final class CreativeImageHandoffFactory extends Factory
{
    protected $model = CreativeImageHandoff::class;

    /**
     * Factory defaults are safe fixture values. Scoped foreign keys are supplied
     * by the explicit parent state so database composite FK constraints hold.
     */
    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'sha256' => str_repeat('a', 64), 'byte_size' => 4096, 'width' => 1024, 'height' => 1024, 'mime_type' => 'image/png', 'state' => 'AWAITING_UPLOAD', 'expires_at' => now()->addMinutes(15)];
    }

    public function forBrand(Brand $brand, User $actor): static
    {
        return $this->state(fn (): array => ['organisation_id' => $brand->organisation_id, 'brand_id' => $brand->id, 'actor_user_id' => $actor->id]);
    }
}
