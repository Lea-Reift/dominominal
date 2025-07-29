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
        $databaseName = 'dominominal.sqlite';
        $databasePath =  database_path($databaseName);

        config(['database.connections.sqlite.database' => $databasePath]);
        if (!file_exists($databasePath)) {
            touch($databasePath);
        }

        $frameworkCompiledViewPath = storage_path('framework/views');

        if (!is_dir($frameworkCompiledViewPath)) {
            mkdir($frameworkCompiledViewPath, recursive: true);
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
