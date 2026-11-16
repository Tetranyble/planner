<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\OutreachDispatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachDispatch extends Model
{
    protected $table = 'outreach_dispatches';

    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<OutreachDispatchFactory> */
    use HasFactory, Uuidable;

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function outreachEnrollment(): BelongsTo
    {
        return $this->belongsTo(OutreachEnrollment::class, 'outreach_enrollment_id');
    }

    public function outreachStep(): BelongsTo
    {
        return $this->belongsTo(OutreachStep::class, 'outreach_step_id');
    }
}
