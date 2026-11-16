<?php

namespace App\Repository\Eloquent;

use App\Models\Organisation;
use App\Models\OrganisationInvitation;
use App\Models\User;
use App\Repository\Contracts\OrganisationInvitationRepositoryContract;
use Illuminate\Support\Collection;
use Tetranyble\Kinship\Models\Role;

final class OrganisationInvitationRepository implements OrganisationInvitationRepositoryContract
{
    public function retireOutstanding(Organisation $organisation, string $email): void
    {
        OrganisationInvitation::query()->where('organisation_id', $organisation->getKey())
            ->where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function create(Organisation $organisation, User $inviter, Role $role, string $email, string $tokenHash): OrganisationInvitation
    {
        return OrganisationInvitation::query()->create([
            'organisation_id' => $organisation->getKey(), 'role_id' => $role->getKey(),
            'invited_by_user_id' => $inviter->getKey(), 'email' => $email,
            'token_hash' => $tokenHash, 'expires_at' => now()->addDays(7),
        ]);
    }

    public function byTokenHash(string $tokenHash): ?OrganisationInvitation
    {
        return OrganisationInvitation::query()->where('token_hash', $tokenHash)->first();
    }

    public function markAccepted(OrganisationInvitation $invitation): void
    {
        $invitation->forceFill(['accepted_at' => now()])->save();
    }

    public function cancelByUuid(Organisation $organisation, string $uuid): ?OrganisationInvitation
    {
        $invitation = OrganisationInvitation::query()->where('organisation_id', $organisation->getKey())
            ->where('uuid', $uuid)->lockForUpdate()->first();
        if ($invitation !== null && $invitation->accepted_at === null && $invitation->revoked_at === null) {
            $invitation->forceFill(['revoked_at' => now()])->save();
        }

        return $invitation;
    }

    public function byTokenHashForUpdate(string $tokenHash): ?OrganisationInvitation
    {
        return OrganisationInvitation::query()->where('token_hash', $tokenHash)->lockForUpdate()->first();
    }

    public function pendingForOrganisation(Organisation $organisation): Collection
    {
        return OrganisationInvitation::query()
            ->leftJoin('outbox_events as delivery', function ($join): void {
                $join->on('delivery.aggregate_id', '=', 'organisation_invitations.id')
                    ->on('delivery.organisation_id', '=', 'organisation_invitations.organisation_id')
                    ->where('delivery.aggregate_type', '=', 'organisation_invitation')
                    ->where('delivery.event_type', '=', 'organisation.invitation.created');
            })
            ->where('organisation_invitations.organisation_id', $organisation->getKey())
            ->whereNull('organisation_invitations.accepted_at')
            ->whereNull('organisation_invitations.revoked_at')
            ->where('organisation_invitations.expires_at', '>', now())
            ->orderByDesc('organisation_invitations.created_at')
            ->limit(100)
            ->get([
                'organisation_invitations.uuid',
                'organisation_invitations.email',
                'organisation_invitations.expires_at',
                'delivery.state as delivery_state',
            ]);
    }
}
