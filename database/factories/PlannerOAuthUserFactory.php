<?php

namespace Database\Factories;

use App\Models\PlannerOAuthUser;

/** @extends UserFactory<PlannerOAuthUser> */
final class PlannerOAuthUserFactory extends UserFactory
{
    /** OAuth principal shares the underlying users table and safe User fixture values. */
    protected $model = PlannerOAuthUser::class;
}
