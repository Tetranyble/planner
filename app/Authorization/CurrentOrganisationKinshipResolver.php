<?php

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Tetranyble\Kinship\Contracts\WorkspaceResolver;

final class CurrentOrganisationKinshipResolver implements WorkspaceResolver
{
    public function resolve(Model $subject): int|string|null
    {
        return $subject instanceof User
            ? app(CurrentOrganisation::class)->idFor($subject)
            : null;
    }
}
