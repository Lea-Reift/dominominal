<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentVoucherMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly string $subjectText,
        public readonly string $pdfOutput,
        public readonly string $pdfName = 'voucher.pdf',
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('justinavillar@outlook.com', 'Justina Villar'),
            subject: $this->subjectText,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $text = <<<TEXT
            Este correo se envió automaticamente y no requiere una respuesta. </br>
            En caso de necesitarlo, puede contactar al correo {$this->envelope()->from->address}.
            TEXT;

        return new Content(
            htmlString: $text
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfOutput, $this->pdfName)
                ->withMime('application/pdf'),
        ];
    }
}
