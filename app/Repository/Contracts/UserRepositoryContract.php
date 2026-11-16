<?php

namespace App\Repository\Contracts;

use App\Models\User;

/**
 * Global identity persistence. Authorization to view users is enforced by
 * application services; this repository must never be used for brand data.
 *
 * @extends Persistence<User>
 */
interface UserRepositoryContract extends Persistence
{
    public function findByEmail(string $email): ?User;
}
