<?php

namespace Database\Factories;

use App\Models\YouTubeResumableEvent;
use App\Models\YouTubeResumableTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<YouTubeResumableEvent> */
final class YouTubeResumableEventFactory extends Factory
{
    protected $model = YouTubeResumableEvent::class;

    /**
     * Factory defaults are safe fixture values. Scoped foreign keys are supplied
     * by the explicit parent state so database composite FK constraints hold.
     */
    public function definition(): array
    {
        return ['action' => 'CREATED', 'to_state' => 'HELD', 'safe_details' => [], 'created_at' => now()];
    }

    public function forTransfer(YouTubeResumableTransfer $transfer): static
    {
        return $this->state(fn (): array => ['organisation_id' => $transfer->organisation_id, 'brand_id' => $transfer->brand_id, 'youtube_resumable_transfer_id' => $transfer->id]);
    }
}
