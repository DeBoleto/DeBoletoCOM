<?php

namespace App\Modules\BoxOffice\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DigitalTicketsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $eventName,
        public readonly int $purchaseId,
        private readonly string $pdf,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Gracias por tu compra');
    }

    public function content(): Content
    {
        return new Content(view: 'boxoffice.mail.digital-tickets');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, "boletos-{$this->purchaseId}.pdf")->withMime('application/pdf'),
        ];
    }
}
