<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class SessionCanceledMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $studioName,
        public readonly string $studentName,
        public readonly string $classTitle,
        public readonly string $startsAtLocal,
        public readonly bool $wasWaitlisted,
        public readonly ?string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Aula cancelada: {$this->classTitle}");
    }

    public function content(): Content
    {
        return new Content(text: 'mail.session-canceled');
    }
}
