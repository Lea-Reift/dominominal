<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Actions\TableRowActions;

use Filament\Actions\Action;
use Throwable;
use App\Models\Setting;
use App\Modules\Payroll\Models\Payroll;
use App\Support\Services\BrevoService;
use Filament\Notifications\Notification;
use App\Mail\PaymentVoucherMail;
use Filament\Forms\Components\TextInput;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Mail;
use App\Modules\Payroll\Models\PayrollDetail;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ShowPaymentVoucherAction
{
    protected Action $action;

    public function __construct(
        protected Payroll $payroll,
    ) {
        $this->action = Action::make('show_payment_voucher')
            ->label('Mostrar volante de pago')
            ->icon('heroicon-s-inbox-arrow-down')
            ->color('info')
            ->button()
            ->modalContent(fn (array $arguments) => view(
                'components.payment-voucher-table',
                ['detail' => $this->getDetailFromArguments($arguments)->display, 'mode' => 'modal'],
            ))
            ->modalHeading('')
            ->stickyModalHeader(false)
            ->schema(fn (array $arguments) => [
                TextInput::make('employee_email')
                    ->label('Correo del empleado')
                    ->email()
                    ->default($this->getDetailFromArguments($arguments)->employee->email)
                    ->required(),
            ])
            ->modalSubmitActionLabel('Enviar comprobante')
            ->extraModalFooterActions(fn (array $arguments): array => [
                Action::make('print_voucher')
                    ->label('Imprimir volante')
                    ->url(function () use ($arguments) {
                        $detail = $this->getDetailFromArguments($arguments);
                        return route('filament.main.payrolls.details.show.export.pdf', [
                            'company' => $detail->payroll->company_id,
                            'payroll' => $detail->payroll_id,
                            'detail' => $detail->id
                        ]);
                    })
                    ->openUrlInNewTab(),
            ])
            ->action(Closure::fromCallable([$this, 'actionCallback']));
    }

    protected function getDetailFromArguments(array $arguments): PayrollDetail
    {
        /** @var PayrollDetail $detail */
        $detail = $this->payroll->details->findOrFail(abs(parse_float($arguments['item'])));
        return $detail;
    }

    public static function make(Payroll $payroll): Action
    {
        return (new self($payroll))->getAction();
    }

    protected function getAction(): Action
    {
        return $this->action;
    }

    protected function actionCallback(mixed $arguments, array $data): void
    {
        $record = $this->getDetailFromArguments($arguments);
        $this->checkEmailConfiguration();

        $employeeEmail = $data['employee_email'];
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
        /** @var EloquentCollection<int, Setting> */
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
                    Action::make('configure')
                        ->label('Configurar')
                        ->url('/main/settings')
                        ->markAsRead(),
                ])
                ->send();

            throw new Halt();
        }

        if (!$isVerified) {
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
                            Action::make('verify')
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
            } catch (Throwable) {
                Notification::make('email_verification_error')
                    ->title('Error al verificar correo')
                    ->body('No se pudo verificar el estado del correo electrónico. Verifique su configuración.')
                    ->danger()
                    ->persistent()
                    ->actions([
                        Action::make('configure')
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
