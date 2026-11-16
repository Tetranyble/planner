<?php

namespace App\Models;

use Database\Factories\AnalyticSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticSnapshot extends Model
{
    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<AnalyticSnapshotFactory> */
    use HasFactory;

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

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }
}
