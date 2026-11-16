<?php

namespace App\Models;

use App\Concerns\Searchable;
use App\Concerns\Uuidable;
use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use Database\Factories\OrganisationPreferenceRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tetranyble\Kinship\Models\Role;

class OrganisationPreferenceRule extends Model
{
    /** @use HasFactory<OrganisationPreferenceRuleFactory> */
    use HasFactory, Searchable, Uuidable;

    /** `uuid` is the opaque identifier for URLs, not the PK. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'event_type',
        'channel',
        'enabled',
        'role_id',
        'organisation_id',
    ];

    protected $attributes = [
        'event_type' => NotificationEventType::PUBLICATION_FAILED->value,
        'channel' => NotificationChannel::EMAIL->value,
        'enabled' => true,
    ];

    protected $casts = [
        'event_type' => NotificationEventType::class,
        'channel' => NotificationChannel::class,
        'enabled' => 'boolean',
    ];

    // ---------- Relationships ----------

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    // ---------- Scopes ----------

    public function scopeEnabled(Builder $query)
    {
        return $query->where('enabled', true);
    }

    public function scopeDisabled(Builder $query)
    {
        return $query->where('enabled', false);
    }

    public function scopeForEvent(Builder $query, NotificationEventType $event)
    {
        return $query->where('event_type', $event->value);
    }

    public function scopeForChannel(Builder $query, NotificationChannel $channel)
    {
        return $query->where('channel', $channel->value);
    }

    // ---------- Helpers ----------

    public function isEnabled(): bool
    {
        return $this->enabled === true;
    }
}
