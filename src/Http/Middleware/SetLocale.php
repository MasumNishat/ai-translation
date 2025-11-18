<?php

namespace Masum\AiTranslator\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Masum\AiTranslator\Models\Language;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $locale = $this->detectLocale($request);

        // Validate locale against active languages
        if ($locale && $this->isValidLocale($locale)) {
            app()->setLocale($locale);

            // Sync Carbon locale if Carbon is available
            if (class_exists(\Carbon\Carbon::class)) {
                \Carbon\Carbon::setLocale($locale);
            }

            // Store locale in session for web requests
            if ($request->hasSession()) {
                $request->session()->put(
                    config('ai-translator.detection.session_key', 'locale'),
                    $locale
                );
            }
        }

        return $next($request);
    }

    /**
     * Detect locale from various sources based on configuration.
     */
    protected function detectLocale(Request $request): ?string
    {
        $sources = config('ai-translator.detection.sources', ['query', 'header', 'session', 'cookie']);

        foreach ($sources as $source) {
            $locale = match ($source) {
                'query' => $this->getFromQuery($request),
                'header' => $this->getFromHeader($request),
                'session' => $this->getFromSession($request),
                'cookie' => $this->getFromCookie($request),
                default => null,
            };

            if ($locale) {
                return $locale;
            }
        }

        // Fall back to app default
        return config('app.locale', 'en');
    }

    /**
     * Get locale from query parameter.
     */
    protected function getFromQuery(Request $request): ?string
    {
        $param = config('ai-translator.detection.query_param', 'locale');

        return $request->query($param);
    }

    /**
     * Get locale from Accept-Language header.
     */
    protected function getFromHeader(Request $request): ?string
    {
        $headerName = config('ai-translator.detection.header_name', 'Accept-Language');
        $acceptLanguage = $request->header($headerName);

        if (!$acceptLanguage) {
            return null;
        }

        // Parse Accept-Language header (e.g., "en-US,en;q=0.9,bn;q=0.8")
        // Extract the primary language code
        $languages = explode(',', $acceptLanguage);

        foreach ($languages as $language) {
            $lang = strtok($language, ';-');

            if ($lang && $this->isValidLocale($lang)) {
                return strtolower($lang);
            }
        }

        return null;
    }

    /**
     * Get locale from session.
     */
    protected function getFromSession(Request $request): ?string
    {
        if (!$request->hasSession()) {
            return null;
        }

        $sessionKey = config('ai-translator.detection.session_key', 'locale');

        return $request->session()->get($sessionKey);
    }

    /**
     * Get locale from cookie.
     */
    protected function getFromCookie(Request $request): ?string
    {
        $cookieName = config('ai-translator.detection.cookie_name', 'app_locale');

        return $request->cookie($cookieName);
    }

    /**
     * Check if locale is valid (exists in active languages).
     */
    protected function isValidLocale(string $locale): bool
    {
        $activeLanguages = Language::getActive();

        return $activeLanguages->contains('code', $locale);
    }
}
