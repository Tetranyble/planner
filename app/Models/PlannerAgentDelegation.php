<?php

namespace App\Models;

use Database\Factories\PlannerAgentDelegationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PlannerAgentDelegation extends Model
{
    use HasFactory;

    protected $fillable = [

        'account_uuids',

        'brand_id',

        'daily_action_limit',

        'daily_arm_limit',

        'expires_at',

        'mode',

        'name',

        'organisation_id',

        'revoked_at',

        'sanctum_token_id',

        'user_id',

    ];

    protected function casts(): array
    {
        return ['account_uuids' => 'array', 'expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    protected static function newFactory(): PlannerAgentDelegationFactory
    {
        return PlannerAgentDelegationFactory::new();
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actionReceipts(): HasMany
    {
        return $this->hasMany(PlannerAgentActionReceipt::class, 'delegation_id');
    }
}
