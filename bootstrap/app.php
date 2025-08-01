<?php

declare(strict_types=1);

use App\Console\Commands\ClearCompilationResourcesCommand;
use App\Console\Commands\CompileAppCommand;
use App\Console\Commands\GenerateSplashscreenCommand;
use App\Console\Commands\SendTestEmail;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckSetupIsCompletedMiddleware;

$baseDir = dirname(__DIR__);
return Application::configure(basePath: $baseDir)
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([
            CheckSetupIsCompletedMiddleware::class,
        ]);
    })
    ->withCommands([
        CompileAppCommand::class,
        GenerateSplashscreenCommand::class,
        ClearCompilationResourcesCommand::class,
        SendTestEmail::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create()
    ->useAppPath("{$baseDir}/src");
