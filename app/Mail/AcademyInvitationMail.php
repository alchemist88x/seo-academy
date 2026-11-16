<?php

namespace App\Mail;

use App\Domain\Academies\Models\Academy;
use App\Domain\Identity\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Route;

class AcademyInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $acceptUrl;

    public function __construct(
        public Invitation $invitation,
        public string $rawToken,
        public Academy $academy,
    ) {
        $this->acceptUrl = Route::has('invitations.accept')
            ? route('invitations.accept', ['token' => $this->rawToken])
            : url('/invitations/'.$this->rawToken);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to join {$this->academy->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.academy-invitation',
        );
    }
}
