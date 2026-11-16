<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Organisation;
use App\Models\PlannerAgentDelegation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class PlannerAgentDelegationFactory extends Factory
{
    protected $model = PlannerAgentDelegation::class;

    public function definition(): array
    {
        return ['name' => 'Agent research and drafting', 'mode' => 'REVIEW_REQUIRED', 'account_uuids' => [], 'daily_action_limit' => 20, 'daily_arm_limit' => 0, 'expires_at' => now()->addDays(7)];
    }

    public function forScope(Organisation $org, Brand $brand, User $owner, int $tokenId): static
    {
        return $this->state(fn (): array => ['organisation_id' => $org->id, 'brand_id' => $brand->id, 'user_id' => $owner->id, 'sanctum_token_id' => $tokenId]);
    }
}
