<?php

namespace App\Repository\Eloquent;

use App\Enums\StatusEnum;
use App\Models\Organisation;
use App\Models\User;
use App\Repository\Contracts\OrganisationWriteRepositoryContract;

/** Explicit owner/bootstrap operations; never exposes a generic unscoped writer. */
final class OrganisationWriteRepository implements OrganisationWriteRepositoryContract
{
    public function __construct(private readonly Organisation $model) {}

    public function createForOwner(User $owner, string $name, string $timezone): Organisation
    {
        return $this->model->newQuery()->create([
            'created_by_user_id' => $owner->getKey(),
            'name' => $name,
            'timezone' => $timezone,
            'status' => StatusEnum::ACTIVE->value,
        ]);
    }

    public function initializePreferences(Organisation $organisation): void
    {
        $organisation->preferences()->create([]);
    }
}
