<?php

namespace App\Repository\Eloquent;

use App\Models\OrganisationInvitation;
use App\Repository\Contracts\OrganisationInvitationOutboxRepositoryContract;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OrganisationInvitationOutboxRepository implements OrganisationInvitationOutboxRepositoryContract
{
    public function queue(OrganisationInvitation $invitation, string $token): void
    {
        DB::table('outbox_events')->insert([
            'uuid' => (string) Str::uuid(),
            'organisation_id' => $invitation->organisation_id,
            'aggregate_type' => 'organisation_invitation',
            'aggregate_id' => $invitation->getKey(),
            'event_type' => 'organisation.invitation.created',
            'event_key' => 'organisation.invitation.'.$invitation->uuid,
            'payload' => json_encode([
                'version' => 1,
                'invitation_id' => $invitation->getKey(),
                'encrypted_token' => Crypt::encryptString($token),
            ], JSON_THROW_ON_ERROR),
            'state' => 'PENDING',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
