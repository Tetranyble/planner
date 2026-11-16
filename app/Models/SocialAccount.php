<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory, Uuidable;

    protected $fillable = ['organisation_id', 'brand_id', 'provider_connection_id', 'provider',
        'external_account_id', 'account_kind', 'display_name', 'handle', 'status', 'last_sync_at'];

    protected $casts = ['last_sync_at' => 'immutable_datetime'];

    public function capabilities(): HasMany
    {
        return $this->hasMany(SocialAccountCapability::class);
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

    public function providerConnection(): BelongsTo
    {
        return $this->belongsTo(ProviderConnection::class, 'provider_connection_id');
    }
}
