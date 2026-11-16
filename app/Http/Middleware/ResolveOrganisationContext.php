<?php

namespace App\Http\Middleware;

use App\Authorization\CurrentOrganisation;
use App\Models\Organisation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Must execute after auth middleware and before any Kinship permission middleware. */
final class ResolveOrganisationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $identifier = $request->route('organisation') ?? $request->header('X-Organisation-Id');
        abort_if($identifier === null, 400, 'An organisation context is required.');

        if ($identifier instanceof Organisation) {
            $organisation = $identifier;
        } elseif (is_scalar($identifier)) {
            $value = (string) $identifier;
            $organisation = ctype_digit($value)
                ? Organisation::query()->find((int) $value)
                : Organisation::query()->where('uuid', $value)->first();
        } else {
            abort(400, 'Invalid organisation context.');
        }

        abort_unless($organisation !== null, 404);
        app(CurrentOrganisation::class)->select($user, (int) $organisation->getKey());

        return $next($request);
    }
}
