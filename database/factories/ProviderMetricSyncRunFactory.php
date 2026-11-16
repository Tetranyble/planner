<?php

namespace Database\Factories;

use App\Models\ProviderMetricSyncRun;
use App\Models\PublishedPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProviderMetricSyncRun> */
final class ProviderMetricSyncRunFactory extends Factory
{
    protected $model = ProviderMetricSyncRun::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'provider' => 'youtube', 'sync_day' => today('UTC'),
            'state' => 'PENDING', 'attempts' => 0, 'next_attempt_at' => now()];
    }

    public function forPost(PublishedPost $post, User $actor): static
    {
        return $this->state(fn () => ['organisation_id' => $post->organisation_id,
            'brand_id' => $post->brand_id, 'published_post_id' => $post->id,
            'requested_by_user_id' => $actor->id, 'provider' => $post->provider]);
    }
}
