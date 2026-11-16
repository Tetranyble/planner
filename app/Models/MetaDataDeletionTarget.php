<?php

namespace App\Models;

use Database\Factories\MetaDataDeletionTargetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MetaDataDeletionTarget extends Model
{
    /** @use HasFactory<MetaDataDeletionTargetFactory> */
    use HasFactory;

    protected $fillable = [

        'organisation_id',

        'processed_at',

        'provider_connection_id',

        'request_id',

        'review_reason',

        'state',

    ];

    protected $casts = ['processed_at' => 'immutable_datetime'];

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function providerConnection(): BelongsTo
    {
        return $this->belongsTo(ProviderConnection::class, 'provider_connection_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MetaDataDeletionRequest::class, 'request_id');
    }
}
