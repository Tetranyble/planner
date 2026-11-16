<?php

namespace App\Models;

use App\Concerns\Uuidable;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory, Uuidable;

    /**
     * Tenant identifiers and primary keys are deliberately excluded.
     * Business services must validate transitions before calling repositories.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'objective', 'status', 'starts_at', 'ends_at',
        'budget_minor', 'currency', 'owner_user_id', 'brief',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'budget_minor' => 'integer',
        ];
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

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
