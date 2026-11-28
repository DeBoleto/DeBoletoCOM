<?php

namespace App\Modules\Checkout\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaseTicketsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $eventName,
        public readonly string $reference,
        public readonly string $ticketsUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Gracias por tu compra');
    }

    public function content(): Content
    {
        return new Content(view: 'checkout.mail.purchase-tickets');
    }
}
