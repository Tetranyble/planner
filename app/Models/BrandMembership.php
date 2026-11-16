<?php

namespace App\Models;

use Database\Factories\BrandMembershipFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Composite-key record used by factories/read assertions; writes go through ManageBrandMembers. */
final class BrandMembership extends Model
{
    /** @use HasFactory<BrandMembershipFactory> */
    use HasFactory;

    protected $table = 'brand_memberships';

    public $incrementing = false;

    protected $fillable = [

        'brand_id',

        'organisation_id',

        'role_id',

        'state',

        'user_id',

    ];

    protected static function newFactory(): BrandMembershipFactory
    {
        return BrandMembershipFactory::new();
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function performUpdate(Builder $query): bool
    {
        throw new LogicException('Composite-key brand membership updates must use a tenant-scoped repository.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Brand access must be revoked using the membership service.');
    }
}
