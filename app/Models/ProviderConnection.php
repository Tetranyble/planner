<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\ProviderConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderConnection extends Model
{
    /** @use HasFactory<ProviderConnectionFactory> */
    use HasFactory, Uuidable;

    protected $fillable = ['organisation_id', 'provider', 'external_actor_id', 'access_token_secret_ref',
        'refresh_token_secret_ref', 'scopes', 'token_expires_at', 'status', 'last_verified_at',
        'reconnect_reason', 'refresh_lease_token', 'refresh_lease_until', 'refresh_retry_at',
        'refresh_attempts', 'last_refresh_result', 'last_refresh_at', 'inspection_lease_token',
        'inspection_lease_until', 'inspection_retry_at', 'data_access_expires_at'];

    protected $hidden = ['access_token_secret_ref', 'refresh_token_secret_ref', 'refresh_lease_token', 'inspection_lease_token', 'scopes'];

    protected $casts = ['scopes' => 'array', 'token_expires_at' => 'immutable_datetime', 'last_verified_at' => 'immutable_datetime', 'refresh_lease_until' => 'immutable_datetime', 'refresh_retry_at' => 'immutable_datetime', 'last_refresh_at' => 'immutable_datetime', 'inspection_lease_until' => 'immutable_datetime', 'inspection_retry_at' => 'immutable_datetime', 'data_access_expires_at' => 'immutable_datetime'];

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }
}
