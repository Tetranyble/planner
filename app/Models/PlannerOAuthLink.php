<?php

namespace App\Models;

use Database\Factories\PlannerOAuthLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlannerOAuthLink extends Model
{
    use HasFactory;

    protected $table = 'planner_oauth_links';

    protected $fillable = [
        'oauth_client_id',
        'user_id',
        'organisation_id',
        'brand_id',
        'delegation_id',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return ['revoked_at' => 'immutable_datetime'];
    }

    protected static function newFactory(): PlannerOAuthLinkFactory
    {
        return PlannerOAuthLinkFactory::new();
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
