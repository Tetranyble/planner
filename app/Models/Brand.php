<?php

namespace App\Models;

use App\Concerns\Searchable;
use App\Concerns\Uuidable;
use App\Enums\StatusEnum;
use App\Enums\TimeZone;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory, Searchable, Uuidable;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'organisation_id',
        'name',
        'website_url',
        'timezone',
        'status',
        'settings',
    ];

    //    protected $attributes = [
    //        'timezone' => TimeZone::AFRICA_LAGOS->value,
    //        'status'   => StatusEnum::ACTIVE->value,
    //    ];

    protected $casts = [
        'timezone' => TimeZone::class,
        'status' => StatusEnum::class,
        'settings' => 'array',
    ];

    // ---------- Relationships ----------

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
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

    public function scopeForOrganisation(Builder $query, Organisation|int $org)
    {
        return $query->where(
            'organisation_id',
            $org instanceof Organisation ? $org->id : $org
        );
    }

    // ---------- Helpers ----------

    public function isActive(): bool
    {
        return $this->status === StatusEnum::ACTIVE;
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'brand_memberships')
            ->withPivot(['organisation_id', 'role_id', 'state'])
            ->withTimestamps();
    }

    // Brand — brands a user can access within an org
    public function usersInOrganisation(int $organisationId): BelongsToMany
    {
        return $this->members()
            ->wherePivot('organisation_id', $organisationId);
    }

    public function creativeImageHandoffs(): HasMany
    {
        return $this->hasMany(CreativeImageHandoff::class, 'brand_id');
    }

    public function recurringCampaignOccurrences(): HasMany
    {
        return $this->hasMany(RecurringCampaignOccurrence::class, 'brand_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'brand_id');
    }
}
