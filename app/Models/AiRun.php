<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\AiRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRun extends Model
{
    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<AiRunFactory> */
    use HasFactory, Uuidable;

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function automationRun(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'automation_run_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }
}
