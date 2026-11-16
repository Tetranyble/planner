<?php

namespace Modules\Unipotence\Infrastructure\Persistence\Models;

use Database\Factories\UnipotenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Unipotence\Domain\Enums\UnipotenceState;

final class Unipotence extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'scope',
        'key',
        'request_hash',
        'reference',
        'claim_token',
        'state',
        'response_status',
        'response_body',
        'response_headers',
        'locked_until',
        'completed_at',
        'indeterminate_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => UnipotenceState::class,
            'response_body' => 'encrypted:array',
            'response_headers' => 'encrypted:array',
            'locked_until' => 'datetime',
            'completed_at' => 'datetime',
            'indeterminate_at' => 'datetime',
        ];
    }

    protected static function newFactory(): UnipotenceFactory
    {
        return UnipotenceFactory::new();
    }
}
