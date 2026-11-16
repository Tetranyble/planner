<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PublicationSandboxRun extends Model
{
    use HasFactory;

    protected $fillable = [

        'attempt_count',

        'lease_token',

        'lease_until',

        'operation_key',

        'outcome_code',

        'outcome_kind',

        'provider',

        'safe_result',

        'scenario',

        'status',

    ];

    protected function casts(): array
    {
        return ['safe_result' => 'array', 'lease_until' => 'immutable_datetime'];
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

    public function publicationJob(): BelongsTo
    {
        return $this->belongsTo(PublicationJob::class, 'publication_job_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PublicationSandboxEvent::class, 'publication_sandbox_run_id');
    }
}
