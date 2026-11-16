<?php

namespace App\Models;

use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(LeadContact::class, 'lead_id');
    }
}
