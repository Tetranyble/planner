<?php

namespace App\Models;

use App\Concerns\Searchable;
use App\Concerns\Uuidable;
use App\Enums\StateEnum;
use App\Enums\StatusEnum;
use App\Enums\TimeZone;
use App\Services\Membership\OrganisationMembershipService;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tetranyble\Kinship\Contracts\Workspace as KinshipWorkspace;
use Tetranyble\Kinship\Models\Role;

class Organisation extends Model implements KinshipWorkspace
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory, Searchable, Uuidable;

    /**
     * The "uuid" column is the route key, but `id` remains the PK.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'name',
        'created_by_user_id',
        'timezone',
        'status',
    ];

    protected $attributes = [
        'timezone' => TimeZone::AFRICA_LAGOS->value,
        'status' => StatusEnum::ACTIVE->value,
    ];

    protected $casts = [
        'timezone' => TimeZone::class,
        'status' => StatusEnum::class,
    ];

    public function getWorkspaceIdentifier(): int|string
    {
        return (int) $this->getKey();
    }

    // ---------- Scopes ----------

    public function scopeActive(Builder $query)
    {
        return $query->where('status', StatusEnum::ACTIVE->value);
    }

    public function scopeSuspended(Builder $query)
    {
        return $query->where('status', StatusEnum::SUSPENDED->value);
    }

    public function scopeClosed(Builder $query)
    {
        return $query->where('status', StatusEnum::CLOSED->value);
    }

    // ---------- Helpers ----------

    public function isActive(): bool
    {
        return $this->status === StatusEnum::ACTIVE;
    }

    public function preferenceRules(): HasMany
    {
        return $this->hasMany(OrganisationPreferenceRule::class, 'organisation_id');
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(OrganisationPreference::class, 'organisation_id');
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class, 'organisation_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganisationMembership::class, 'organisation_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organisation_memberships')
            ->withPivot(['role_id', 'state', 'invited_by_user_id', 'joined_at'])
            ->withTimestamps();
    }

    public function createMembership(User $user, Role $role, StateEnum $state = StateEnum::ACTIVE): void
    {
        app(OrganisationMembershipService::class)
            ->grant($this, $user, $role, $state);
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function creativeImageHandoffs(): HasMany
    {
        return $this->hasMany(CreativeImageHandoff::class, 'organisation_id');
    }

    public function apiRateLimitBuckets(): HasMany
    {
        return $this->hasMany(ApiRateLimitBucket::class, 'organisation_id');
    }
}
