<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uses the language saved in the "locale" cookie (English by default).
 * Also switches Carbon, so dates and "5 minutes ago" follow the language.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('locale');

        if (is_string($locale) && array_key_exists($locale, config('khaaja.locales'))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
