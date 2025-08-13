<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\PaymentVoucherMail;
use App\Modules\Payroll\Models\PayrollDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    protected $signature = 'test:send-email {email=manuelguzman.villar@gmail.com}';

    protected $description = 'Send a test payment voucher email';

    public function handle(): void
    {
        $email = $this->argument('email');

        /** @var ?PayrollDetail */
        $payrollDetail = PayrollDetail::with(['payroll.company', 'employee'])
            ->inRandomOrder()
            ->first();

        if (!$payrollDetail) {
            $this->error('No payroll details found in database');
            return;
        }

        $payrollDisplay = $payrollDetail->display;
        $pdfOutput = $payrollDisplay->renderPDF();

        $mailSubject = "Volante de pago {$payrollDetail->employee->full_name} {$payrollDetail->payroll->period->format('d/m/Y')}";
        $pdfName = 'Volante-' . str_replace(' ', '-', $payrollDetail->employee->full_name) . '-' . $payrollDetail->payroll->period->format('d-m-Y') . '.pdf';

        $mail = new PaymentVoucherMail(
            subjectText: $mailSubject,
            pdfOutput: $pdfOutput,
            pdfName: $pdfName,
            payrollDisplay: $payrollDisplay
        );

        Mail::to($email)->send($mail);

        $this->info("Test email sent to {$email}");
        $this->info("Employee: {$payrollDetail->employee->full_name}");
        $this->info("Period: {$payrollDetail->payroll->period->format('d/m/Y')}");
        $this->info("Company: {$payrollDetail->payroll->company->name}");
    }
}
