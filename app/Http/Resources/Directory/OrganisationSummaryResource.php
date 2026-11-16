<?php

namespace App\Http\Resources\Directory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrganisationSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'timezone' => $this->timezone instanceof \BackedEnum ? $this->timezone->value : $this->timezone,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
        ];
    }
}
