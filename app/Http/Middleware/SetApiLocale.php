<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiLocale
{
    /**
     * Supported locales list.
     */
    protected array $supportedLocales = ['en', 'ar'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Admin Panel Requests: Controlled explicitly by admin session/query, defaulting to English
        if ($request->is('admin*')) {
            $queryLocale = $request->query('lang') ?? $request->query('locale');
            if ($queryLocale && is_string($queryLocale)) {
                $clean = strtolower(substr($queryLocale, 0, 2));
                if (in_array($clean, $this->supportedLocales)) {
                    session(['admin_locale' => $clean]);
                }
            }

            $locale = session('admin_locale') ?? config('app.locale', 'en');
            $locale = strtolower(substr((string) $locale, 0, 2));
            if (! in_array($locale, $this->supportedLocales)) {
                $locale = 'en';
            }

            app()->setLocale($locale);

            return $next($request);
        }

        // 2. API / Storefront Requests
        if (
            $request->is('api/ar*') ||
            $request->is('ar/*') ||
            $request->segment(1) === 'ar' ||
            $request->segment(2) === 'ar'
        ) {
            $locale = 'ar';
        } else {
            $locale = $request->header('X-Locale')
                ?? $request->query('lang')
                ?? $request->query('locale')
                ?? session('locale')
                ?? $this->detectAcceptLanguage($request)
                ?? config('app.locale', 'en');
        }

        $locale = strtolower(substr((string) $locale, 0, 2));

        if (! in_array($locale, $this->supportedLocales)) {
            $locale = config('app.fallback_locale', 'en');
        }

        app()->setLocale($locale);

        $response = $next($request);

        if (method_exists($response, 'header')) {
            $response->header('Content-Language', $locale);
        }

        return $response;
    }

    /**
     * Detect locale from Accept-Language header.
     */
    protected function detectAcceptLanguage(Request $request): ?string
    {
        $acceptLang = $request->header('Accept-Language');
        if (! $acceptLang) {
            return null;
        }

        if (str_contains(strtolower($acceptLang), 'ar')) {
            return 'ar';
        }

        return 'en';
    }
}
