<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Durable recurrence projection only. A projection is NOT an approved post. */
final class RecurringCampaignOccurrence extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id',
        'brand_id',
        'recurring_schedule_id',
        'occurrence_key',
        'local_at',
        'run_at',
        'held_at',
    ];

    protected function casts(): array
    {
        return ['run_at' => 'immutable_datetime', 'held_at' => 'immutable_datetime'];
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

    public function recurringSchedule(): BelongsTo
    {
        return $this->belongsTo(RecurringSchedule::class, 'recurring_schedule_id');
    }
}
