<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Actions\TableRowActions;

use App\Modules\Payroll\Models\Payroll;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use App\Mail\PaymentVoucherMail;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Mail;
use App\Modules\Payroll\Models\PayrollDetail;

class ShowPaymentVoucherAction
{
    protected Action $action;

    public function __construct(
        protected Payroll $record,
    ) {
        $this->action = Action::make('show_payment_voucher')
            ->label('Mostrar volante')
            ->icon('heroicon-s-inbox-arrow-down')
            ->modalHeading(fn (PayrollDetail $record) => $record->employee->full_name)
            ->color('info')
            ->modalContent(fn (PayrollDetail $record) => view(
                'show-pdf',
                ['pdf_base64_string' => base64_encode($record->display->renderPDF())],
            ))
            ->form([
                TextInput::make('employee_email')
                    ->label('Correo del empleado')
                    ->email()
                    ->default(fn (PayrollDetail $record) => $record->employee->email)
                    ->required(),
            ])
            ->modalSubmitActionLabel('Enviar comprobante')
            ->action(function (PayrollDetail $record, array $data) {
                $employeeEmail = $record->employee->email ?? $data['employee_email'];
                $pdfOutput = $record->display->renderPDF();

                $mailSubject = "Volante de pago {$record->employee->full_name} {$record->payroll->period->format('d/m/Y')}";

                $mail = new PaymentVoucherMail($mailSubject, $pdfOutput);

                defer(fn () => Mail::to($employeeEmail)->send($mail));

                Notification::make('send_payment_voucher')
                    ->title('Voucher enviado con exito')
                    ->success()
                    ->send();
            });
    }

    public static function make(Payroll $payroll): Action
    {
        return (new self($payroll))->getAction();
    }

    protected function getAction(): Action
    {
        return $this->action;
    }
}
