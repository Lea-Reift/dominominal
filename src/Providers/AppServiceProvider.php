<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $databaseName = 'dominominal.sqlite';
        $databasePath = $this->app->environment('local')
            ? database_path($databaseName)
            : base_path('../' . $databaseName);

        config(['database.connections.sqlite.database' => $databasePath]);
        if (!file_exists($databasePath)) {
            touch($databasePath);
        }

        if (!Schema::hasTable('settings')) {
            Artisan::call('migrate --force');
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
