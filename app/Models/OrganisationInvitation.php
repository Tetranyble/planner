<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\OrganisationInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrganisationInvitation extends Model
{
    use HasFactory, Uuidable;

    protected static function newFactory(): OrganisationInvitationFactory
    {
        return OrganisationInvitationFactory::new();
    }

    protected $fillable = [
        'organisation_id', 'role_id', 'invited_by_user_id', 'email',
        'token_hash', 'expires_at', 'accepted_at', 'revoked_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function invitedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
