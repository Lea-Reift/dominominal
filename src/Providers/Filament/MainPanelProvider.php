<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use DirectoryIterator;

class MainPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel
            ->default()
            ->id('main')
            ->path('main')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ]);

        $modulesFolder = new DirectoryIterator(app_path('Modules'));

        foreach ($modulesFolder as $directory) {
            /** @var DirectoryIterator $directory */

            if ($directory->isDot() || $directory->isFile()) {
                continue;
            }

            if (!is_dir($directoryResourcesPath = $directory->getRealPath().DIRECTORY_SEPARATOR."Resources")) {
                mkdir($directoryResourcesPath);
            }

            $namespace = str($directory->getPath())
                ->append("\\")
                ->replace([app_path(), DIRECTORY_SEPARATOR], ['App', '\\'])
                ->append("{$directory->getBasename()}\\Resources")
                ->toString();

            $panel->discoverResources($directoryResourcesPath, $namespace);
        }

        return $panel
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
