<?php

namespace Database\Factories;

use App\Models\ProviderMetricObservation;
use App\Models\ProviderMetricSyncRun;
use App\Models\PublishedPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProviderMetricObservation> */
final class ProviderMetricObservationFactory extends Factory
{
    protected $model = ProviderMetricObservation::class;

    public function definition(): array
    {
        return ['provider' => 'youtube', 'external_post_id' => 'Xy123AbC_-1',
            'channel_reference' => 'UC'.str_repeat('a', 22),
            'views' => 100, 'likes' => 5, 'comments' => 2,
            'response_sha256' => hash('sha256', 'synthetic-provider-statistics'),
            'provenance' => 'PROVIDER_API_OBSERVED', 'observed_at' => now()];
    }

    public function forRun(ProviderMetricSyncRun $run, PublishedPost $post, string $channel): static
    {
        if ((int) $run->published_post_id !== (int) $post->id || (int) $run->brand_id !== (int) $post->brand_id) {
            throw new \InvalidArgumentException('Metric sync fixture must belong to the same publication.');
        }

        return $this->state(fn () => ['organisation_id' => $post->organisation_id,
            'brand_id' => $post->brand_id, 'published_post_id' => $post->id,
            'provider_metric_sync_run_id' => $run->id, 'provider' => $post->provider,
            'external_post_id' => $post->external_post_id, 'channel_reference' => $channel]);
    }
}
