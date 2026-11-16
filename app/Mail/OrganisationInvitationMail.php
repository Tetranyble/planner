<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class OrganisationInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $organisationName, public readonly string $acceptUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Invitation to join '.$this->organisationName.' on Planner');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.organisation-invitation');
    }
}
