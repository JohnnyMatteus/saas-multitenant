<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class BookingConfirmedMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $studioName,
        public readonly string $studentName,
        public readonly string $classTitle,
        public readonly string $startsAtLocal,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Reserva confirmada: {$this->classTitle}");
    }

    public function content(): Content
    {
        return new Content(text: 'mail.booking-confirmed');
    }
}
