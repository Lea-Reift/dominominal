<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Schema;

class CheckSetupIsCompletedMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request):Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            str_starts_with($request->path(), 'main') &&
            (!Schema::hasTable('settings') || !(Setting::query()->where(['setting' => 'setup', 'name' => 'is_completed'])->value('value') ?? false))
        ) {
            return redirect('/');
        }

        return $next($request);
    }
}
