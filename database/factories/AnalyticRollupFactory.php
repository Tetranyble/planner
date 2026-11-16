<?php

namespace Database\Factories;

use App\Models\AnalyticRollup;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnalyticRollup> */
final class AnalyticRollupFactory extends Factory
{
    protected $model = AnalyticRollup::class;

    public function definition(): array
    {
        return ['period_grain' => 'DAY', 'period_start' => now()->toDateString(),
            'metric_key' => 'impressions', 'metric_value' => 7, 'computed_at' => now()];
    }

    public function forAccount(SocialAccount $account): static
    {
        return $this->state(fn () => ['organisation_id' => $account->organisation_id,
            'brand_id' => $account->brand_id, 'social_account_id' => $account->id]);
    }
}
