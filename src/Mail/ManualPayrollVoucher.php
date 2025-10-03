<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use App\Support\ValueObjects\ManualVoucherDisplay;

class ManualPayrollVoucher extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ManualVoucherDisplay $voucher
    ) {
        $this->mailer = 'brevo';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Comprobante de Pago - ' . $this->voucher->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.manual-payroll-voucher',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->voucher->renderPDF(), 'comprobante-pago.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
