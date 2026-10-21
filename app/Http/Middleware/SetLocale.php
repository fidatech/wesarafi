<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class SetLocale
{
    public function handle(Request $request, Closure $next)
{
    if ($request->has('lang') && in_array($request->get('lang'), ['en', 'fa', 'ps'], true)) {
        $locale = $request->get('lang');
        session(['locale' => $locale]);
        // Log::info("Locale switched to: {$locale}");
    }

    $localeFromSession = session('locale', config('app.locale'));
    if (! in_array($localeFromSession, ['en', 'fa', 'ps'], true)) $localeFromSession = 'en';
    App::setLocale($localeFromSession);
    // Log::info("Locale from session: {$localeFromSession}");

    return $next($request);
}

}
