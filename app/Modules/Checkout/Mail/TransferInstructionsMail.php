<?php

namespace App\Modules\Checkout\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class TransferInstructionsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $eventName,
        public readonly string $reference,
        public readonly float $amount,
        public readonly string $clabe,
        public readonly string $bank,
        public readonly string $beneficiary,
        public readonly ?Carbon $dueDate,
        public readonly string $orderUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Instrucciones para pagar tu compra por SPEI');
    }

    public function content(): Content
    {
        return new Content(view: 'checkout.mail.transfer-instructions');
    }
}
