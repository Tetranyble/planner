<?php

namespace App\Http\Controllers\Workspace;

use App\Domain\Membership\InvitationToken;
use App\Http\Controllers\Controller;
use App\Repository\Contracts\OrganisationInvitationRepositoryContract;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class OrganisationInvitationController extends Controller
{
    public function show(Request $request, string $token, OrganisationInvitationRepositoryContract $invitations): Response
    {
        abort_unless(InvitationToken::hasValidFormat($token), 404);
        $invitation = $invitations->byTokenHash(InvitationToken::digest($token));
        abort_if($invitation === null, 404);
        abort_if($invitation->accepted_at !== null || $invitation->revoked_at !== null
            || $invitation->expires_at->isPast() || ! $invitation->organisation->isActive(), 410);
        abort_unless(hash_equals($invitation->email, mb_strtolower($request->user()->email)), 403);

        $response = Inertia::render('Organisations/AcceptInvitation', [
            'token' => $token,
            'organisation' => ['name' => $invitation->organisation->name],
        ])->toResponse($request);
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
