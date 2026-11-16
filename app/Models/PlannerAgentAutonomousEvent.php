<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlannerAgentAutonomousEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['event', 'evidence', 'run_id'];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'created_at' => 'immutable_datetime'];
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function run(): BelongsTo
    {
        return $this->belongsTo(PlannerAgentAutonomousRun::class, 'run_id');
    }
}
