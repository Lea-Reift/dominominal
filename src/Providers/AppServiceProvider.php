<?php

declare(strict_types=1);

namespace App\Providers;

use App\Mail\BrevoTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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

        Mail::extend('brevo', function (array $config = []) {
            return new BrevoTransport($config['key']);
        });
    }
}
