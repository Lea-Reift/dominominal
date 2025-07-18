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
        if (!Schema::hasTable('settings') && !$this->app->runningInConsole()) {
            Artisan::call('migrate --force');
        }

        Number::useLocale('en');
        Number::useCurrency('USD');
    }
}
