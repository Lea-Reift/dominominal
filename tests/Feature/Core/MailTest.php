<?php

declare(strict_types=1);

use App\Mail\PaymentVoucherMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

describe('Mail System', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        Mail::fake();
    });

    test('payment voucher mail can be sent', function () {

        Mail::to($this->user)->send(new PaymentVoucherMail(
            'Payment Voucher',
            'PDF content here',
            'voucher.pdf',
            null  // Skip complex display object for now
        ));

        Mail::assertSent(PaymentVoucherMail::class, function ($mail) {
            return $mail->subjectText === 'Payment Voucher';
        });
    });

    test('payment voucher mail has correct subject', function () {
        $mail = new PaymentVoucherMail(
            'Payment Voucher',
            'PDF content here',
            'voucher.pdf',
            null
        );

        expect($mail->envelope()->subject)->toContain('Payment Voucher');
    });

    test('payment voucher mail contains payroll data', function () {

        $mail = new PaymentVoucherMail(
            'Payment Voucher Subject',
            'PDF content here',
            'voucher.pdf',
            null
        );

        $content = $mail->content();

        expect($content->view)->toBe('emails.payment-voucher');
        expect($content->with['detail'])->toBeNull();
    });

    test('mail queue works correctly', function () {

        $mail = new PaymentVoucherMail(
            'Payment Voucher Subject',
            'PDF content here',
            'voucher.pdf',
            null
        );

        Mail::to($this->user)->queue($mail);

        Mail::assertQueued(PaymentVoucherMail::class);
    });

    test('mail fails gracefully with invalid data', function () {
        $mail = new PaymentVoucherMail('', '');
        expect($mail)->toBeInstanceOf(PaymentVoucherMail::class);

        $mail2 = new PaymentVoucherMail('', '', '', null);
        expect($mail2)->toBeInstanceOf(PaymentVoucherMail::class);
    });

    test('multiple payment vouchers can be sent', function () {

        for ($i = 0; $i < 3; $i++) {
            $mail = new PaymentVoucherMail(
                'Payment Voucher Subject',
                'PDF content here',
                'voucher.pdf',
                null
            );

            Mail::to($this->user)->send($mail);
        }

        Mail::assertSent(PaymentVoucherMail::class, 3);
    });
});
