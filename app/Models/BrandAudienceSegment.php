<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\BrandAudienceSegmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandAudienceSegment extends Model
{
    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<BrandAudienceSegmentFactory> */
    use HasFactory, Uuidable;

    public function roles(): HasMany
    {
        return $this->hasMany(BrandAudienceRole::class, 'audience_segment_id');
    }

    public function painPoints(): HasMany
    {
        return $this->hasMany(BrandAudiencePainPoint::class, 'audience_segment_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(BrandAudienceGoal::class, 'audience_segment_id');
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
}
