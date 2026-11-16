<?php

namespace App\Models;

use Database\Factories\ProviderGrantLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProviderGrantLink extends Model
{
    /** @use HasFactory<ProviderGrantLinkFactory> */
    use HasFactory;

    protected $fillable = [

        'detached_at',

        'grant_group_id',

        'organisation_id',

        'provider_connection_id',

    ];

    protected $casts = ['detached_at' => 'immutable_datetime'];

    // Eloquent foreign-key relationships; keyed to the actual database columns.

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
}
