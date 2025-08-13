<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Actions\TableRowActions;

use App\Models\Setting;
use App\Modules\Payroll\Models\Payroll;
use App\Support\Services\BrevoService;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use App\Mail\PaymentVoucherMail;
use Filament\Forms\Components\TextInput;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Mail;
use App\Modules\Payroll\Models\PayrollDetail;
use Closure;
use Filament\Notifications\Actions\Action as NotificationAction;

class ShowPaymentVoucherAction
{
    protected Action $action;

    public function __construct(
        protected Payroll $payroll,
    ) {
        $this->action = Action::make('show_payment_voucher')
            ->label('Mostrar volante')
            ->icon('heroicon-s-inbox-arrow-down')
            ->color('info')
            ->modalContent(fn (PayrollDetail $record) => view(
                'components.payment-voucher-table',
                ['detail' => $record->display, 'mode' => 'modal'],
            ))
            ->modalHeading('')
            ->stickyModalHeader(false)
            ->form([
                TextInput::make('employee_email')
                    ->label('Correo del empleado')
                    ->email()
                    ->default(fn (PayrollDetail $record) => $record->employee->email)
                    ->required(),
            ])
            ->modalSubmitActionLabel('Enviar comprobante')
            ->extraModalFooterActions(fn (PayrollDetail $record): array => [
                Action::make('print_voucher')
                    ->label('Imprimir volante')
                    ->url(route('filament.main.payrolls.details.show.export.pdf', ['detail' => $record]))
                    ->openUrlInNewTab(),
            ])
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
        $this->checkEmailConfiguration();

        $employeeEmail = $record->employee->email ?? $data['employee_email'];
        $pdfOutput = $record->display->renderPDF();

        $mailSubject = "Volante de pago {$record->employee->full_name} {$record->payroll->period->format('d/m/Y')}";
        $pdfName = 'Volante-' . $record->employee->document_number . '-' . $record->payroll->period->format('d-m-Y') . '.pdf';

        $mail = new PaymentVoucherMail(
            subjectText: $mailSubject,
            pdfOutput: $pdfOutput,
            pdfName: $pdfName,
            payrollDisplay: $record->display
        );

        defer(fn () => Mail::to($employeeEmail)->send($mail));

        Notification::make('send_payment_voucher')
            ->title('Voucher enviado con éxito')
            ->success()
            ->send();
    }

    protected function checkEmailConfiguration(): void
    {
        $emailSettings = Setting::query()->getSettings('email');
        $emailSetting = $emailSettings->where('name', 'username')->first();
        $verifiedSetting = $emailSettings->where('name', 'is_verified')->first();

        $hasEmail = $emailSetting && $emailSetting->value;
        $isVerified = $verifiedSetting && $verifiedSetting->value;

        if (!$hasEmail) {
            Notification::make('email_not_configured')
                ->title('Correo no configurado')
                ->body('No hay un correo electrónico configurado para el envío de volantes.')
                ->danger()
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('configure')
                        ->label('Configurar')
                        ->url('/main/settings')
                        ->markAsRead(),
                ])
                ->send();

            throw new Halt();
        }

        if ($hasEmail && !$isVerified) {
            try {
                $brevoService = new BrevoService();
                $isValidSender = $brevoService->isValidSender($emailSetting->value);

                if (!$isValidSender) {
                    Notification::make('email_not_verified')
                        ->title('Correo no verificado')
                        ->body(
                            'Su dirección de correo electrónico aún no está verificada en Brevo.' .
                            'Revise su bandeja de entrada o configure una dirección diferente.'
                        )
                        ->warning()
                        ->persistent()
                        ->actions([
                            NotificationAction::make('verify')
                                ->label('Verificar correo')
                                ->url('/main/settings')
                                ->markAsRead(),
                        ])
                        ->send();

                    throw new Halt();
                }

                $verifiedSetting = $emailSettings->firstWhere('name', 'is_verified') ?? new Setting();
                $verifiedSetting->setting = 'email';
                $verifiedSetting->name = 'is_verified';
                $verifiedSetting->value = true;
                $verifiedSetting->save();
            } catch (\Throwable) {
                Notification::make('email_verification_error')
                    ->title('Error al verificar correo')
                    ->body('No se pudo verificar el estado del correo electrónico. Verifique su configuración.')
                    ->danger()
                    ->persistent()
                    ->actions([
                        NotificationAction::make('configure')
                            ->label('Configurar')
                            ->url('/main/settings')
                            ->markAsRead(),
                    ])
                    ->send();

                throw new Halt();
            }
        }
    }
}
