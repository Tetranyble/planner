<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlannerAgentWorkEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['action', 'evidence', 'work_order_id'];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'created_at' => 'immutable_datetime'];
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(PlannerAgentWorkOrder::class, 'work_order_id');
    }
}
