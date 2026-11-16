<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class RequirePlannerFirstPartySession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user !== null && Auth::guard('web')->check() &&
            ! ($user->currentAccessToken() instanceof PersonalAccessToken), 403,
            'Integration credentials may be managed only from a first-party user session.');

        return $next($request);
    }
}
