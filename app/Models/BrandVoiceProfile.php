<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\BrandVoiceProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandVoiceProfile extends Model
{
    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<BrandVoiceProfileFactory> */
    use HasFactory, Uuidable;

    public function rules(): HasMany
    {
        return $this->hasMany(BrandVoiceRule::class, 'voice_profile_id');
    }

    public function terms(): HasMany
    {
        return $this->hasMany(BrandVoiceTerm::class, 'voice_profile_id');
    }

    public function examples(): HasMany
    {
        return $this->hasMany(BrandVoiceExample::class, 'voice_profile_id');
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
