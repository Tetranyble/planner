<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only events: application code only INSERTs; no modify/delete workflow. */
final class PlannerOperationalAlertEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'event_type',
        'observed_at',
        'observed_count',
        'organisation_id',
        'planner_operational_alert_id',
    ];

    public $timestamps = true;

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function plannerOperationalAlert(): BelongsTo
    {
        return $this->belongsTo(PlannerOperationalAlert::class, 'planner_operational_alert_id');
    }
}
