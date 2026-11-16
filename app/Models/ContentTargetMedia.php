<?php

namespace App\Models;

use Database\Factories\ContentTargetMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentTargetMedia extends Model
{
    protected $table = 'content_target_media';

    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<ContentTargetMediaFactory> */
    use HasFactory;

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function contentTarget(): BelongsTo
    {
        return $this->belongsTo(ContentTarget::class, 'content_target_id');
    }

    public function mediaVariant(): BelongsTo
    {
        return $this->belongsTo(MediaVariant::class, 'media_variant_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }
}
