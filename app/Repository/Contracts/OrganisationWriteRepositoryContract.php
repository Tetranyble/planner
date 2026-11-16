<?php

namespace App\Repository\Contracts;

use App\Models\Organisation;
use App\Models\User;

interface OrganisationWriteRepositoryContract
{
    public function createForOwner(User $owner, string $name, string $timezone): Organisation;

    public function initializePreferences(Organisation $organisation): void;
}
