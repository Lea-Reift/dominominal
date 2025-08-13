<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Models\Setting;
use App\Support\Services\BrevoService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * @property EloquentCollection<string, Setting> $emailSettings
 */
class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'support.pages.settings';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $title = 'Configuración General';

    protected EloquentCollection $emailSettings;
    protected Collection $validSenders;
    public ?array $emailFormData = [];
    public bool $showOtpInput = false;

    public function __construct()
    {
        // @phpstan-ignore-next-line
        $this->emailSettings = Setting::query()->getSettings('email')->keyBy('name');
        $this->emailFormData = $this->emailSettings->pluck('value', 'name')->toArray();

        try {
            $brevoService = new BrevoService();
            $this->validSenders = $brevoService->getValidSenders();
        } catch (\Exception) {
            $this->validSenders = collect();
        }
    }

    public function mount(): void
    {
        $this->getEmailSettingsForm()
            ->fill(empty($this->emailFormData) ? null : $this->emailFormData);

        $emailSetting = $this->emailSettings->where('name', 'username')->first();
        $verifiedSetting = $this->emailSettings->where('name', 'is_verified')->first();
        $senderIdSetting = $this->emailSettings->where('name', 'sender_id')->first();

        $hasEmail = $emailSetting && $emailSetting->value;
        $isVerified = $verifiedSetting && $verifiedSetting->value;
        $hasSenderId = $senderIdSetting && $senderIdSetting->value;

        $this->showOtpInput = $hasEmail && $hasSenderId && !$isVerified;
    }

    public function getEmailSettingsForm(): Form
    {
        $user = Auth::user();
        $isVerified = $this->checkEmailVerificationStatus();

        return Form::make($this)
            ->schema([
                TextInput::make('username')
                    ->label('Correo Electrónico')
                    ->required()
                    ->default($user->email)
                    ->email()
                    ->prefixIcon(fn (?string $state) => $this->getEmailPrefixIcon($state, $isVerified))
                    ->prefixIconColor(fn (?String $state) => $this->getEmailPrefixIconColor($state, $isVerified))
                    ->live()
                    ->afterStateUpdated(function () {
                        $this->showOtpInput = false;
                        $this->emailFormData['otpCode'] = null;
                    }),
                TextInput::make('otpCode')
                    ->label('Código de Verificación')
                    ->placeholder('Ingrese el código de 6 dígitos')
                    ->maxLength(6)
                    ->minLength(6)
                    ->numeric()
                    ->helperText(
                        str('Revise su bandeja de entrada de **Brevo** (servicio de envío de correos electrónicos) e ingrese el código de 6 dígitos que recibió.')  ->inlineMarkdown()
                            ->toHtmlString()
                    )
                    ->visible($this->showOtpInput),
            ])
            ->statePath('emailFormData');
    }

    protected function checkEmailVerificationStatus(): bool
    {
        $emailSetting = $this->emailSettings->where('name', 'username')->first();
        $verifiedSetting = $this->emailSettings->where('name', 'is_verified')->first();

        $currentEmail = $emailSetting?->value;
        if (!$currentEmail) {
            return false;
        }

        $dbVerified = $verifiedSetting && $verifiedSetting->value;

        if ($dbVerified) {
            return true;
        }

        try {
            $brevoService = new BrevoService();
            $isValidSender = $brevoService->isValidSender($currentEmail);

            if ($isValidSender) {
                $verifiedSetting = $this->emailSettings->firstWhere('name', 'is_verified') ?? new Setting();
                $verifiedSetting->setting = 'email';
                $verifiedSetting->name = 'is_verified';
                $verifiedSetting->value = true;
                $verifiedSetting->save();

                return true;
            }
        } catch (\Exception) {
        }

        return false;
    }

    protected function getEmailPrefixIcon(?string $currentEmail, bool $isVerified): string
    {
        $emailNeedsVerification =
            !$currentEmail ||
            !$this->emailSettings->get('username')?->value ||
            $this->emailSettings->get('username')->value !== $currentEmail;

        return match (true) {
            $emailNeedsVerification => 'heroicon-o-envelope',
            $isVerified => 'heroicon-o-check-circle',
            default => 'heroicon-o-x-circle'
        };
    }

    protected function getEmailPrefixIconColor(?string $currentEmail, bool $isVerified): string
    {
        $emailNeedsVerification =
           !$currentEmail ||
           !$this->emailSettings->get('username')?->value ||
           $this->emailSettings->get('username')->value !== $currentEmail;
        return match (true) {
            $emailNeedsVerification => 'gray',
            $isVerified => 'success',
            default => 'danger'
        };
    }

    public function submitEmailSettings(): void
    {
        if (empty($this->emailFormData)) {
            return;
        }

        if ($this->emailFormData['username'] !== $this->emailSettings->get('username')?->value) {
            $this->addNewEmail();
            return;
        }

        $this->validateOtpCode();
    }

    public function addNewEmail(): void
    {
        $email = $this->emailFormData['username'] ?? '';
        $name = 'Dominominal';

        try {
            $brevoService = new BrevoService();

            $response = $brevoService->addSender($email, $name);
            $senderId = $response['id'] ?? null;

            DB::transaction(function () use ($email, $name, $senderId) {
                $usernameSetting = $this->emailSettings->firstWhere('name', 'username') ?? new Setting();
                $usernameSetting->setting = 'email';
                $usernameSetting->name = 'username';
                $usernameSetting->value = $email;
                $usernameSetting->save();

                $nameSetting = $this->emailSettings->firstWhere('name', 'from_name') ?? new Setting();
                $nameSetting->setting = 'email';
                $nameSetting->name = 'from_name';
                $nameSetting->value = $name;
                $nameSetting->save();

                if ($senderId) {
                    $senderIdSetting = $this->emailSettings->firstWhere('name', 'sender_id') ?? new Setting();
                    $senderIdSetting->setting = 'email';
                    $senderIdSetting->name = 'sender_id';
                    $senderIdSetting->value = $senderId;
                    $senderIdSetting->save();
                }

                $verifiedSetting = $this->emailSettings->firstWhere('name', 'is_verified') ?? new Setting();
                $verifiedSetting->setting = 'email';
                $verifiedSetting->name = 'is_verified';
                $verifiedSetting->value = false;
                $verifiedSetting->save();
            });

            $this->showOtpInput = $senderId !== null;

            Notification::make()
                ->title('Correo configurado correctamente')
                ->body('Se ha enviado un código de verificación a su correo electrónico. Revise su bandeja de entrada e ingrese el código para completar la verificación.')
                ->warning()
                ->persistent()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al configurar el correo')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function validateOtpCode(): void
    {
        $otpCode = $this->emailFormData['otpCode'] ?? null;

        if (!$otpCode) {
            Notification::make()
                ->title('Error')
                ->body('Debe ingresar el código de verificación.')
                ->danger()
                ->send();
            return;
        }

        $senderIdSetting = $this->emailSettings->where('name', 'sender_id')->first();

        if (!$senderIdSetting || !$senderIdSetting->value) {
            Notification::make()
                ->title('Error')
                ->body('No se encontró el ID del remitente. Intente configurar el correo nuevamente.')
                ->danger()
                ->send();
            return;
        }

        try {
            $brevoService = new BrevoService();
            $isValid = $brevoService->validateSenderWithOTP((int)$senderIdSetting->value, (int)$otpCode);

            if ($isValid) {
                $verifiedSetting = $this->emailSettings->firstWhere('name', 'is_verified') ?? new Setting();
                $verifiedSetting->setting = 'email';
                $verifiedSetting->name = 'is_verified';
                $verifiedSetting->value = true;
                $verifiedSetting->save();

                $this->showOtpInput = false;
                $this->emailFormData['otpCode'] = null;

                Notification::make()
                    ->title('¡Correo verificado!')
                    ->body('Su dirección de correo electrónico ha sido verificada correctamente.')
                    ->success()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al validar el código')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function verifyEmail(): void
    {
        $email = $this->emailFormData['username'] ?? $this->emailSettings->where('name', 'username')->first()?->value;

        if (!$email) {
            Notification::make()
                ->title('Error')
                ->body('No hay un correo electrónico configurado.')
                ->danger()
                ->send();
            return;
        }

        try {
            $brevoService = new BrevoService();
            $this->validSenders = $brevoService->getValidSenders();
            $isValid = $brevoService->isValidSender($email);

            if ($isValid) {

                $verifiedSetting = $this->emailSettings->firstWhere('name', 'is_verified') ?? new Setting();
                $verifiedSetting->setting = 'email';
                $verifiedSetting->name = 'is_verified';
                $verifiedSetting->value = true;
                $verifiedSetting->save();

                Notification::make()
                    ->title('¡Correo verificado!')
                    ->body('Su dirección de correo electrónico ha sido verificada correctamente.')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Correo no verificado')
                    ->body('Su dirección de correo electrónico aún no está verificada en Brevo. Revise su bandeja de entrada o configure una dirección diferente.')
                    ->warning()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al verificar el correo')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
