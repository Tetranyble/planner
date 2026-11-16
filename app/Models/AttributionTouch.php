<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\AttributionTouchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributionTouch extends Model
{
    protected $table = 'attribution_touches';

    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<AttributionTouchFactory> */
    use HasFactory, Uuidable;

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function trackingLink(): BelongsTo
    {
        return $this->belongsTo(TrackingLink::class, 'tracking_link_id');
    }
}
