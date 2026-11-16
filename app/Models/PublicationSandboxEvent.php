<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PublicationSandboxEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['event', 'publication_sandbox_run_id', 'safe_details'];

    protected function casts(): array
    {
        return ['safe_details' => 'array', 'created_at' => 'immutable_datetime'];
    }

    // Eloquent foreign-key relationships; keyed to the actual database columns.

    public function publicationSandboxRun(): BelongsTo
    {
        return $this->belongsTo(PublicationSandboxRun::class, 'publication_sandbox_run_id');
    }
}
