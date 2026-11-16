<?php

namespace Database\Factories;

use App\Models\YouTubeResumableAttempt;
use App\Models\YouTubeResumableTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<YouTubeResumableAttempt> */
final class YouTubeResumableAttemptFactory extends Factory
{
    protected $model = YouTubeResumableAttempt::class;

    /**
     * Factory defaults are safe fixture values. Scoped foreign keys are supplied
     * by the explicit parent state so database composite FK constraints hold.
     */
    public function definition(): array
    {
        return ['sequence' => 1, 'action' => 'INIT', 'state' => 'STARTED', 'offset_bytes' => 0];
    }

    public function forTransfer(YouTubeResumableTransfer $transfer): static
    {
        return $this->state(fn (): array => ['organisation_id' => $transfer->organisation_id, 'brand_id' => $transfer->brand_id, 'youtube_resumable_transfer_id' => $transfer->id]);
    }
}
