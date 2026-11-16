<?php

namespace App\Models;

use Database\Factories\PlannerOperationalAlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PlannerOperationalAlert extends Model
{
    use HasFactory;

    protected $fillable = [

        'brand_id',

        'first_seen_at',

        'last_seen_at',

        'observed_count',

        'organisation_id',

        'resolved_at',

        'signal',

        'state',

    ];

    protected function casts(): array
    {
        return [
            'observed_count' => 'integer',
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): PlannerOperationalAlertFactory
    {
        return PlannerOperationalAlertFactory::new();
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PlannerOperationalAlertEvent::class, 'planner_operational_alert_id');
    }
}
