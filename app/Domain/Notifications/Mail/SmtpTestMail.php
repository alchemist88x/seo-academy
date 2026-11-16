<?php

namespace App\Domain\Notifications\Mail;

use App\Domain\Academies\Models\Academy;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SmtpTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $senderName,
        public string $testTimestamp,
    ) {}

    public function envelope(): Envelope
    {
        $academyName = Academy::current()?->name ?? config('app.name', 'SEO Academy');

        return new Envelope(
            subject: "[{$academyName}] SMTP Connection Test Successful",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.smtp-test',
        );
    }
}
