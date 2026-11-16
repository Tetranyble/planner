<?php

namespace App\Models;

use Database\Factories\ProviderConnectionCredentialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Secret ciphertext must never be exposed via serializers, logging, or API resources. */
final class ProviderConnectionCredential extends Model
{
    /** @use HasFactory<ProviderConnectionCredentialFactory> */
    use HasFactory;

    protected $fillable = [

        'access_token_ciphertext',

        'organisation_id',

        'provider_connection_id',

        'refresh_token_ciphertext',

        'refresh_token_expires_at',

    ];

    protected $hidden = ['access_token_ciphertext', 'refresh_token_ciphertext'];

    protected $casts = ['refresh_token_expires_at' => 'immutable_datetime'];

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function providerConnection(): BelongsTo
    {
        return $this->belongsTo(ProviderConnection::class, 'provider_connection_id');
    }
}
