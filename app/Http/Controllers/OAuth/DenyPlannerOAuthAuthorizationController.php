<?php

namespace App\Http\Controllers\OAuth;

use Laravel\Passport\Http\Controllers\DenyAuthorizationController;
use Symfony\Component\HttpFoundation\Response;

final class DenyPlannerOAuthAuthorizationController
{
    public function __invoke(DenyAuthorizationController $passport): Response
    {
        return app()->call([$passport, 'deny']);
    }
}
