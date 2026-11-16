<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\SocialOAuthSelectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ephemeral consent-to-brand assignment. Never expose ciphertext in web or API responses. */
final class SocialOAuthSelection extends Model
{
    /** @use HasFactory<SocialOAuthSelectionFactory> */
    use HasFactory, Uuidable;

    protected $table = 'social_oauth_selections';

    protected $fillable = ['organisation_id', 'brand_id', 'user_id', 'provider', 'meta_actor_digest',
        'grant_ciphertext', 'candidates_ciphertext', 'expires_at', 'consumed_at'];

    protected $hidden = ['grant_ciphertext', 'candidates_ciphertext'];

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
