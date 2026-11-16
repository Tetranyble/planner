<?php

namespace Database\Factories;

use App\Models\YouTubeResumableTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<YouTubeResumableTransfer> */
final class YouTubeResumableTransferFactory extends Factory
{
    protected $model = YouTubeResumableTransfer::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(), 'state' => 'HELD', 'next_action' => 'INIT',
            'total_bytes' => 2048000, 'committed_bytes' => 0, 'canonical_sha256' => hash('sha256', 'synthetic-youtube'),
            'canonical_mime' => 'video/mp4', 'poll_count' => 0, 'version' => 1, 'sequence' => 0,
            // Tenant, brand, media run, asset and author must come from genuine scoped fixtures.
        ];
    }
}
