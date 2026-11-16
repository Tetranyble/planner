<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlannerAgentActionReceipt extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [

        'action',

        'brand_id',

        'delegation_id',

        'idempotency_key',

        'input_hash',

        'organisation_id',

        'result',

    ];

    protected function casts(): array
    {
        return ['result' => 'array', 'created_at' => 'immutable_datetime'];
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(PlannerAgentDelegation::class, 'delegation_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }
}
