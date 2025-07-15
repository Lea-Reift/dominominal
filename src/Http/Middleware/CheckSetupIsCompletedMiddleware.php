<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class CheckSetupIsCompletedMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Schema::hasTable('settings')) {
            Artisan::call('migrate --force');
        }

        if (
            str_starts_with($request->path(), 'main') &&
            !(Setting::query()->where(['setting' => 'setup', 'name' => 'is_completed'])->value('value') ?? false)
        ) {
            return redirect('/');
        }

        return $next($request);
    }
}
