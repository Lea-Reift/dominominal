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
use Filament\Support\Enums\MaxWidth;

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
            ])
            ->pages([
                Pages\Dashboard::class,
            ])
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->sidebarCollapsibleOnDesktop()
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
            ])
            ->maxContentWidth(MaxWidth::Full);


        $modulesFolder = new DirectoryIterator(app_path('Modules'));

        foreach ($modulesFolder as $directory) {
            /** @var DirectoryIterator $directory */

            if ($directory->isDot() || $directory->isFile()) {
                continue;
            }

            if (!is_dir($directoryResourcesPath = $directory->getRealPath() . DIRECTORY_SEPARATOR . 'Resources')) {
                mkdir($directoryResourcesPath);
            }

            if (!is_dir($directoryPagesPath = $directory->getRealPath() . DIRECTORY_SEPARATOR . 'Pages')) {
                mkdir($directoryPagesPath);
            }

            $baseNamespace = str($directory->getPath())
                ->append('\\')
                ->replace([app_path(), DIRECTORY_SEPARATOR], ['App', '\\']);

            $resourcesNamespace = $baseNamespace
                ->append("{$directory->getBasename()}\\Resources")
                ->toString();

            $pagesNamespace = $baseNamespace
                ->append("{$directory->getBasename()}\\Pages")
                ->toString();

            $panel->discoverResources($directoryResourcesPath, $resourcesNamespace);
            $panel->discoverPages($directoryPagesPath, $pagesNamespace);
        }

        return $panel;
    }
}
