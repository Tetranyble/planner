<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class PlannerOperationalAlertMail extends Mailable
{
    public function __construct(public readonly string $brandName, public readonly string $signal) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Planner operational safety review required');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.planner-operational-alert');
    }
}
