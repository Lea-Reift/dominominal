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
        $databasePath = base_path(config('database.connections.sqlite.database'));
        if (!file_exists($databasePath)) {
            file_put_contents($databasePath, '');
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
