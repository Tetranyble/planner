<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\PlannerAgentAutonomousRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PlannerAgentAutonomousRun extends Model
{
    use HasFactory, Uuidable;

    protected $fillable = [

        'brand_id',

        'content_uuid',

        'delegation_id',

        'failure_code',

        'findings',

        'idempotency_key',

        'lease_expires_at',

        'lease_token',

        'objective',

        'organisation_id',

        'request_hash',

        'requested_by_user_id',

        'research_attempts',

        'research_hash',

        'resource_attempts',

        'resource_hash',

        'resource_kind',

        'resource_model',

        'resource_revision_count',

        'resource_text',

        'sources',

        'status',

        'title',

        'work_order_uuid',

        'uuid',

    ];

    protected function casts(): array
    {
        return ['sources' => 'array', 'lease_expires_at' => 'immutable_datetime'];
    }

    protected static function newFactory(): PlannerAgentAutonomousRunFactory
    {
        return PlannerAgentAutonomousRunFactory::new();
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

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PlannerAgentAutonomousEvent::class, 'run_id');
    }
}
