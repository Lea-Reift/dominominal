<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $databasePath = base_path('../dominominal.sqlite');
        config(['database.connections.sqlite.database' => $databasePath]);
        if (!file_exists($databasePath)) {
            touch($databasePath);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Number::useLocale('en');
        Number::useCurrency('USD');
    }
}
