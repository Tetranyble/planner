<?php

namespace App\Models;

use Database\Factories\OrganisationMembershipFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Composite-key membership record. Use scoped repository/service for mutations. */
final class OrganisationMembership extends Model
{
    /** @use HasFactory<OrganisationMembershipFactory> */
    use HasFactory;

    protected $table = 'organisation_memberships';

    public $incrementing = false;

    protected $fillable = [

        'invited_by_user_id',

        'joined_at',

        'organisation_id',

        'role_id',

        'state',

        'user_id',

    ];

    protected $casts = ['joined_at' => 'datetime'];

    protected static function newFactory(): OrganisationMembershipFactory
    {
        return OrganisationMembershipFactory::new();
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function performUpdate(Builder $query): bool
    {
        throw new LogicException('Composite-key membership updates must use a tenant-scoped repository.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Composite-key membership deletion is forbidden; revoke access through the service.');
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function invitedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
