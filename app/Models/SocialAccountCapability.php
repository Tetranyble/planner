<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\SocialAccountCapabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccountCapability extends Model
{
    /** @use HasFactory<SocialAccountCapabilityFactory> */
    use HasFactory, Uuidable;

    protected $fillable = [
        'organisation_id',
        'brand_id',
        'social_account_id',
        'capability',
        'mode',
        'reason',
        'checked_at',
        'valid_until',
    ];

    protected $casts = ['checked_at' => 'immutable_datetime', 'valid_until' => 'immutable_datetime'];

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }
}
