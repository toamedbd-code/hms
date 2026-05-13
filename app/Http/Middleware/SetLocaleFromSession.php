<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromSession
{
    /**
     * Apply locale from session on every web request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedLocales = ['en', 'bn'];

        $queryLocale = strtolower((string) $request->query('lang', ''));
        $sessionLocale = strtolower((string) $request->session()->get('locale', ''));
        $cookieLocale = strtolower((string) $request->cookie('locale', ''));

        $locale = in_array($queryLocale, $allowedLocales, true)
            ? $queryLocale
            : (in_array($sessionLocale, $allowedLocales, true)
                ? $sessionLocale
                : (in_array($cookieLocale, $allowedLocales, true)
                    ? $cookieLocale
                    : strtolower((string) config('app.locale', 'en'))));

        if (!in_array($locale, $allowedLocales, true)) {
            $locale = 'en';
        }

        if ($request->session()->get('locale') !== $locale) {
            $request->session()->put('locale', $locale);
        }

        app()->setLocale($locale);

        return $next($request);
    }
}