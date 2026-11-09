<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Locale: user preference, then session, then the default-language setting (S0 step 4). */
class SetLocale
{
    public const SUPPORTED = ['en', 'am'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = Auth::user()?->locale
            ?? $request->session()->get('locale')
            ?? setting('general.default_language', config('app.locale'));

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
