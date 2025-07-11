<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Models\Setting;
use App\Models\User;
use App\Support\Pages\Filament\Actions\SetupAction;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;
use Filament\Pages\SimplePage;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;

class Setup extends SimplePage
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'support.pages.setup';

    protected User $user;

    protected Setting $setupIsCompletedSetting;

    public function __construct()
    {
        $this->setupIsCompletedSetting = Setting::where(['setting' => 'setup', 'name' => 'is_completed',])->firstOrFail();
    }

    public function mount(): void
    {
        if ($this->setupIsCompletedSetting->value) {
            redirect(Filament::getPanel()->getUrl());
        }
    }

    public function confirmationAction(): Action
    {
        return SetupAction::make('confirmation')
            ->requiresConfirmation()
            ->modalIcon('heroicon-m-document-chart-bar')
            ->modalHeading('Bienvenido a Dominominal')
            ->modalDescription('Antes de empezar, hay que configurar algunas cosas')
            ->modalSubmitActionLabel('Empecemos')
            ->extraAttributes([
                'wire:init' => new HtmlString('mountAction(\'confirmation\')'),
            ])
            ->action(fn () => $this->replaceMountedAction('setupWizard'));
    }

    public function setupWizardAction(): Action
    {
        return SetupAction::make('setupWizard')
            ->label('Configuración Inicial')
            ->modalWidth(MaxWidth::Medium)
            ->form([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),
                TextInput::make('email')
                    ->label('Correo Electrónico')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable()
                    ->confirmed(),
                TextInput::make('password_confirmation')
                    ->label('Confirmar Contraseña')
                    ->required()
                    ->password()
                    ->revealable()
            ])
            ->action(function (array $data) {
                $user = User::query()->create($data);

                $this->setupIsCompletedSetting->value = true;
                $this->setupIsCompletedSetting->save();

                $this->replaceMountedAction('setupCompleted', ['user' => $user->id]);
            });
    }

    public function setupCompletedAction(): Action
    {
        return SetupAction::make('setupCompleted')
            ->requiresConfirmation()
            ->modalIcon('heroicon-m-check-circle')
            ->modalHeading('Bienvenido a Dominominal')
            ->modalDescription('Todo listo. Ya puede empezar 😁')
            ->modalSubmitActionLabel('Ingresar')
            ->action(function (array $arguments) {
                Auth::loginUsingId($arguments['user'], true);
                $this->redirectRoute('filament.main.pages.dashboard');
            });
    }
}
