<?php

namespace App\Models;

use Database\Factories\RecurringScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringSchedule extends Model
{
    /** @use HasFactory<RecurringScheduleFactory> */
    use HasFactory;

    protected $fillable = [

        'end_at',

        'max_occurrences',

        'next_expand_at',

        'rrule',

        'start_local',

        'timezone',

    ];

    public function target(): BelongsTo
    {
        return $this->belongsTo(ContentTarget::class, 'content_target_id');
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function contentTarget(): BelongsTo
    {
        return $this->belongsTo(ContentTarget::class, 'content_target_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(RecurringCampaignOccurrence::class, 'recurring_schedule_id');
    }
}
