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
use Closure;

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
                'components.voucher-table',
                ['detail' => $record->display, 'mode' => 'modal'],
            ))
            ->form([
                TextInput::make('employee_email')
                    ->label('Correo del empleado')
                    ->email()
                    ->default(fn (PayrollDetail $record) => $record->employee->email)
                    ->required(),
            ])
            ->modalSubmitActionLabel('Enviar comprobante')
            ->action(Closure::fromCallable([$this, 'actionCallback']));
    }

    public static function make(Payroll $payroll): Action
    {
        return (new self($payroll))->getAction();
    }

    protected function getAction(): Action
    {
        return $this->action;
    }

    protected function actionCallback(PayrollDetail $record, array $data): void
    {
        $employeeEmail = $record->employee->email ?? $data['employee_email'];
        $pdfOutput = $record->display->renderPDF();

        $mailSubject = "Volante de pago {$record->employee->full_name} {$record->payroll->period->format('d/m/Y')}";
        $pdfName = 'Volante-' . str_replace(' ', '-', $record->employee->full_name) . '-' . $record->payroll->period->format('d-m-Y') . '.pdf';

        $mail = new PaymentVoucherMail(
            subjectText: $mailSubject,
            pdfOutput: $pdfOutput,
            pdfName: $pdfName,
            payrollDisplay: $record->display
        );

        defer(fn () => Mail::to($employeeEmail)->send($mail));

        Notification::make('send_payment_voucher')
            ->title('Voucher enviado con exito')
            ->success()
            ->send();
    }
}
