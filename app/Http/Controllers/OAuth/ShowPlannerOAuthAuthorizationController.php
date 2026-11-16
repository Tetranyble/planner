<?php

namespace App\Http\Controllers\OAuth;

use Laravel\Passport\Http\Controllers\AuthorizationController;

/** Planner owns the route; Passport only validates and signs OAuth authorization codes. */
final class ShowPlannerOAuthAuthorizationController
{
    public function __invoke(AuthorizationController $passport): mixed
    {
        return app()->call([$passport, 'authorize']);
    }
}
