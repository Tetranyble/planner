<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\AiActionApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiActionApproval extends Model
{
    /** Explicitly deny mass assignment by default. Trusted repositories use forceFill. */
    protected $fillable = [];

    /** @use HasFactory<AiActionApprovalFactory> */
    use HasFactory, Uuidable;

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function aiToolExecution(): BelongsTo
    {
        return $this->belongsTo(AiToolExecution::class, 'ai_tool_execution_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function decidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }
}
