<?php

namespace Database\Factories;

use App\Models\IntegrationSyncCursor;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IntegrationSyncCursor> */
final class IntegrationSyncCursorFactory extends Factory
{
    protected $model = IntegrationSyncCursor::class;

    public function definition(): array
    {
        return ['sync_kind' => 'insights', 'cursor_ref' => 'synthetic-cursor',
            'state' => 'READY', 'watermark_at' => now()];
    }

    public function forAccount(SocialAccount $account): static
    {
        return $this->state(fn () => ['organisation_id' => $account->organisation_id,
            'brand_id' => $account->brand_id, 'social_account_id' => $account->id]);
    }
}
