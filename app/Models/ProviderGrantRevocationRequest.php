<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\ProviderGrantRevocationRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProviderGrantRevocationRequest extends Model
{
    /** @use HasFactory<ProviderGrantRevocationRequestFactory> */
    use HasFactory, Uuidable;

    protected $fillable = ['organisation_id', 'grant_group_id', 'provider_connection_id', 'requested_by_user_id',
        'confirmed_by_user_id', 'provider', 'status', 'affected_connections', 'impact_digest', 'reason',
        'expires_at', 'confirmed_at', 'lease_token', 'lease_until', 'attempted_at',
        'provider_acknowledged_at', 'safe_error_code'];

    protected $hidden = ['impact_digest', 'lease_token'];

    protected $casts = ['expires_at' => 'immutable_datetime', 'confirmed_at' => 'immutable_datetime',
        'lease_until' => 'immutable_datetime', 'attempted_at' => 'immutable_datetime',
        'provider_acknowledged_at' => 'immutable_datetime'];

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function grantGroup(): BelongsTo
    {
        return $this->belongsTo(ProviderGrantGroup::class, 'grant_group_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function providerConnection(): BelongsTo
    {
        return $this->belongsTo(ProviderConnection::class, 'provider_connection_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
