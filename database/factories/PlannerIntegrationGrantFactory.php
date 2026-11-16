<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerIntegrationGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class PlannerIntegrationGrantFactory extends Factory
{
    protected $model = PlannerIntegrationGrant::class;

    public function definition(): array
    {
        return ['name' => 'Read only reporting', 'expires_at' => now()->addWeek()];
    }

    /** Intentionally require explicit tenant and Sanctum parent fixtures. */
    public function forScope(Organisation $org, Brand $brand, User $owner, int $sanctumTokenId): static
    {
        return $this->state(fn (): array => [
            'organisation_id' => $org->id, 'brand_id' => $brand->id, 'user_id' => $owner->id,
            'sanctum_token_id' => $sanctumTokenId,
        ]);
    }
}
