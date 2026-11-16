<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\SocialOauthAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SocialOauthAttempt extends Model
{
    /** @use HasFactory<SocialOauthAttemptFactory> */
    use HasFactory, Uuidable;

    protected $fillable = ['organisation_id', 'brand_id', 'user_id', 'provider', 'state_digest',
        'redirect_uri', 'expires_at', 'consumed_at'];

    protected $hidden = ['state_digest'];

    protected $casts = ['expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime'];

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
