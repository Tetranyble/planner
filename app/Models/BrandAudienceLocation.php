<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\BrandAudienceLocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandAudienceLocation extends Model
{
    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<BrandAudienceLocationFactory> */
    use HasFactory, Uuidable;

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function audienceSegment(): BelongsTo
    {
        return $this->belongsTo(BrandAudienceSegment::class, 'audience_segment_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }
}
