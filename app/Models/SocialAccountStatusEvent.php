<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\SocialAccountStatusEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccountStatusEvent extends Model
{
    /** @use HasFactory<SocialAccountStatusEventFactory> */
    use HasFactory, Uuidable;

    protected $fillable = ['organisation_id', 'brand_id', 'social_account_id', 'event_type',
        'old_status', 'new_status', 'recorded_at', 'details'];

    protected $casts = ['details' => 'array', 'recorded_at' => 'immutable_datetime'];

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
