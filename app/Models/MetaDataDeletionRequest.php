<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\MetaDataDeletionRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Public status is keyed by a high-entropy confirmation code; never expose the actor identifier. */
final class MetaDataDeletionRequest extends Model
{
    /** @use HasFactory<MetaDataDeletionRequestFactory> */
    use HasFactory, Uuidable;

    protected $fillable = ['signed_request_digest', 'actor_digest', 'actor_ciphertext', 'confirmation_code',
        'state', 'matched_grants', 'processed_grants', 'attempts', 'last_error_code', 'accepted_at', 'completed_at'];

    protected $hidden = ['actor_digest', 'actor_ciphertext', 'signed_request_digest'];

    protected $casts = [
        'accepted_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime',
        'matched_grants' => 'integer', 'processed_grants' => 'integer', 'attempts' => 'integer',
    ];

    public function targets(): HasMany
    {
        return $this->hasMany(MetaDataDeletionTarget::class, 'request_id');
    }
}
