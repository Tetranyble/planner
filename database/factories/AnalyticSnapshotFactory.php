<?php

namespace Database\Factories;

use App\Models\AnalyticSnapshot;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnalyticSnapshot> */
final class AnalyticSnapshotFactory extends Factory
{
    protected $model = AnalyticSnapshot::class;

    public function definition(): array
    {
        return ['observed_at' => now(), 'metrics' => json_encode(['impressions' => 7], JSON_THROW_ON_ERROR),
            'metric_scope' => 'ACCOUNT', 'collection_status' => 'COMPLETE'];
    }

    public function forAccount(SocialAccount $account): static
    {
        return $this->state(fn () => ['organisation_id' => $account->organisation_id,
            'brand_id' => $account->brand_id, 'social_account_id' => $account->id]);
    }
}
