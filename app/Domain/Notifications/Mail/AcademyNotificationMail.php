<?php

namespace App\Domain\Notifications\Mail;

use App\Domain\Academies\Models\Academy;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AcademyNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $notificationTitle,
        public string $notificationMessage,
        public ?string $actionUrl = null,
        public ?string $actionText = null,
        public ?string $academyName = null,
    ) {
        $this->academyName ??= Academy::current()?->name ?? config('app.name', 'SEO Academy');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->academyName}] {$this->notificationTitle}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.academy-notification',
        );
    }
}
