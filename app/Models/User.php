<?php

namespace App\Models;

use App\Authorization\CurrentOrganisation;
use App\Concerns\Searchable;
use App\Enums\StateEnum;
use App\Services\Membership\OrganisationMembershipService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tetranyble\Kinship\Concerns\HasRolesAndPermissions;
use Tetranyble\Kinship\Contracts\WorkspaceSubject;
use Tetranyble\Kinship\Models\Role;
use Tetranyble\Storage\Concerns\BelongsToStorageWorkspace;
use Tetranyble\Storage\Modules\Workspace\Application\Contracts\StorageUser;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, StorageUser, WorkspaceSubject
{
    protected $fillable = ['name', 'email', 'password'];

    /** @use HasFactory<UserFactory> */
    use BelongsToStorageWorkspace, HasApiTokens, HasFactory, HasRolesAndPermissions, Notifiable, Searchable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Kinship receives only active, validated workspace context. */
    public function getWorkspaceIdentifier(): int|string|null
    {
        return app(CurrentOrganisation::class)->idFor($this);
    }

    /** Storage's workspace relation resolves through the same secure context. */
    public function getWorkspaceIdAttribute(): ?int
    {
        return app(CurrentOrganisation::class)->idFor($this);
    }

    public function organisationMemberships(): HasMany
    {
        return $this->hasMany(OrganisationMembership::class, 'user_id');
    }

    public function invitationsSent(): HasMany
    {
        return $this->hasMany(OrganisationMembership::class, 'invited_by_user_id');
    }

    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class, 'organisation_memberships')
            ->withPivot(['role_id', 'state', 'invited_by_user_id', 'joined_at'])
            ->withTimestamps();
    }

    public function createMembership(Organisation $organisation, Role $role, StateEnum $state = StateEnum::ACTIVE): void
    {
        app(OrganisationMembershipService::class)
            ->grant($organisation, $this, $role, $state);
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'brand_memberships')
            ->withPivot(['organisation_id', 'role_id', 'state'])
            ->withTimestamps();
    }

    public function brandsInOrganisation(int $organisationId)
    {
        return Brand::query()
            ->join('brand_memberships', 'brand_memberships.brand_id', '=', 'brands.id')
            ->where('brand_memberships.user_id', $this->id)
            ->where('brand_memberships.organisation_id', $organisationId)
            ->where('brand_memberships.state', StateEnum::ACTIVE->value)
            ->select('brands.*');
    }

    public function creativeImageHandoffs(): HasMany
    {
        return $this->hasMany(CreativeImageHandoff::class, 'actor_user_id');
    }
}
