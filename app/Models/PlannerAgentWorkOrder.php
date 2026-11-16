<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\PlannerAgentWorkOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PlannerAgentWorkOrder extends Model
{
    use HasFactory, Uuidable;

    protected $fillable = [

        'brand_id',

        'created_by_user_id',

        'delegation_id',

        'draft_content_uuid',

        'findings',

        'objective',

        'organisation_id',

        'research_hash',

        'sources',

        'status',

        'title',

        'uuid',

    ];

    protected function casts(): array
    {
        return ['sources' => 'array'];
    }

    protected static function newFactory(): PlannerAgentWorkOrderFactory
    {
        return PlannerAgentWorkOrderFactory::new();
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(PlannerAgentDelegation::class, 'delegation_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PlannerAgentWorkEvent::class, 'work_order_id');
    }
}
