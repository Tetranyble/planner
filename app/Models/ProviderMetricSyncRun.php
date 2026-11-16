<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProviderMetricSyncRun extends Model
{
    use HasFactory, Uuidable;

    protected $fillable = [

        'attempts',

        'completed_at',

        'lease_owner',

        'lease_until',

        'next_attempt_at',

        'outcome_code',

        'provider',

        'state',

        'sync_day',

    ];

    protected $hidden = ['lease_owner'];

    protected function casts(): array
    {
        return ['sync_day' => 'immutable_date', 'lease_until' => 'immutable_datetime',
            'next_attempt_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
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

    public function publishedPost(): BelongsTo
    {
        return $this->belongsTo(PublishedPost::class, 'published_post_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
