<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const SUPPORTED_LOCALES = ['en', 'fr', 'ar'];
    private const DEFAULT_LOCALE = 'en';

    /**
     * Set the application locale from the Accept-Language or X-Locale header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);
        App::setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        // 1. Check X-Locale header (explicit override)
        $xLocale = $request->header('X-Locale');
        if ($xLocale && in_array($xLocale, self::SUPPORTED_LOCALES)) {
            return $xLocale;
        }

        // 2. Check Accept-Language header (e.g. "fr", "fr-FR", "ar;q=0.9")
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage) {
            // Extract primary language tag (first 2 chars)
            $primaryLang = strtolower(substr($acceptLanguage, 0, 2));
            if (in_array($primaryLang, self::SUPPORTED_LOCALES)) {
                return $primaryLang;
            }
        }

        return self::DEFAULT_LOCALE;
    }
}
