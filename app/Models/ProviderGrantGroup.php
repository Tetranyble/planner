<?php

namespace App\Models;

use Database\Factories\ProviderGrantGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class ProviderGrantGroup extends Model
{
    /** @use HasFactory<ProviderGrantGroupFactory> */
    use HasFactory;

    protected $fillable = ['actor_digest', 'application_id', 'provider_family'];

    protected $hidden = ['actor_digest'];
}
