<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Grid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * @property Collection<int, Setting> $emailSettings
 */
class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'support.pages.settings';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $title = 'Configuración General';

    protected Collection $emailSettings;
    public ?array $emailFormData = [];

    public function __construct()
    {
        $this->emailSettings = Setting::query()->getSettings('email');
        $this->emailFormData = $this->emailSettings->pluck('value', 'name')->toArray();
    }

    public function mount(): void
    {
        $this->getEmailSettingsForm()->fill(empty($this->emailFormData) ? null : $this->emailFormData);
    }

    public function getEmailSettingsForm(): Form
    {
        $user = Auth::user();
        return Form::make($this)
            ->schema([
                Grid::make(3)
                    ->schema([
                        TextInput::make('from_name')
                            ->label('Nombre del destinatario')
                            ->required()
                            ->default($user->name),
                        TextInput::make('host')
                            ->label('Servidor')
                            ->hint('Dirección IP o URL de un servidor SMTP válido')
                            ->placeholder('192.168.1.1')
                            ->required()
                            ->rule(function ($attribute, $value, $fail) {
                                if (
                                    !filter_var($value, FILTER_VALIDATE_URL) &&
                                    !filter_var($value, FILTER_VALIDATE_IP)
                                ) {
                                    $fail('Debe ser una URL o una dirección IP válida.');
                                }
                            }),
                        TextInput::make('port')
                            ->label('Puerto')
                            ->required()
                            ->default(587)
                            ->integer(),
                        TextInput::make('username')
                            ->label('Correo Electrónico')
                            ->required()
                            ->default($user->email)
                            ->email(),
                        TextInput::make('password')
                            ->label('Contraseña')
                            ->required()
                            ->password()
                            ->revealable(),
                    ])
            ])
            ->statePath('emailFormData');
    }

    public function submitEmailSettings(): void
    {
        if (empty($this->emailFormData)) {
            return;
        }

        DB::transaction(function () {
            foreach ($this->emailFormData as $name => $value) {

                $setting = $this->emailSettings->firstWhere('name', $name) ?? new Setting();
                $setting->setting = 'email';
                $setting->name = $name;
                $setting->is_encrypted = $name === 'password';
                $setting->value = $value;

                $setting->save();
            }
        });

        Notification::make()
            ->title('Configuración de correo guardada')
            ->success()
            ->send();

        // if ($this->emailSettings->isEmpty()) {
        // $data = Arr::map($this->emailFormData, fn (string|int $value, string $name) => [
        //     'setting' => 'email',
        //     'name' => $name,
        //     'value' => $value,
        //     'is_encrypted' => $name === 'password',
        // ]);

        //     Setting::query()->fillAndInsert($data);
        //     $notification->send();
        //     return;
        // }

    }
}
