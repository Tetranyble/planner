<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;

trait Uuidable
{
    public function initializeUuidable(): void
    {
        $this->mergeFillable(['uuid']);
    }

    protected static function bootUuidable(): void
    {
        static::creating(function (Model $model) {
            $model->uuid ??= (string) str()->uuid();
        });
    }
}
