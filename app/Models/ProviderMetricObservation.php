<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable provider read observation. Never claim it is audited revenue or unique reach. */
final class ProviderMetricObservation extends Model
{
    use HasFactory;

    protected $fillable = [

        'channel_reference',

        'comments',

        'external_post_id',

        'likes',

        'observed_at',

        'provenance',

        'provider',

        'response_sha256',

        'views',

    ];

    protected function casts(): array
    {
        return ['observed_at' => 'immutable_datetime'];
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function providerMetricSyncRun(): BelongsTo
    {
        return $this->belongsTo(ProviderMetricSyncRun::class, 'provider_metric_sync_run_id');
    }

    public function publishedPost(): BelongsTo
    {
        return $this->belongsTo(PublishedPost::class, 'published_post_id');
    }
}
