# TASK 09: Middleware & Request Handling

**Priority:** P2 (High)
**Total Estimated Time:** 12-16 hours
**Dependencies:** TASK_03 (Testing)
**Status:** ⏳ Pending

---

## Overview

Implement middleware for language detection, switching, route translation, localization, and request/response handling.

---

## Subtasks

### P2-T09-S01: Language Detection Middleware

**Estimated Time:** 3-4 hours
**Priority:** P2
**Dependencies:** None

#### Description
Automatically detect and set user's preferred language based on multiple sources.

#### Implementation

**1. Create Middleware**

```php
<?php

namespace Masum\AiTranslator\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Masum\AiTranslator\Models\Language;
use Symfony\Component\HttpFoundation\Response;

class DetectLanguage
{
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        $language = $this->detectLanguage($request);

        if ($language) {
            app()->setLocale($language);
            session(['language' => $language]);
        }

        return $next($request);
    }

    /**
     * Detect language from multiple sources
     */
    protected function detectLanguage(Request $request): ?string
    {
        // Priority order:
        // 1. Query parameter (?lang=en)
        // 2. Session
        // 3. Cookie
        // 4. User preference (if authenticated)
        // 5. Browser Accept-Language header
        // 6. Default language

        $sources = [
            fn() => $this->fromQueryParameter($request),
            fn() => $this->fromSession($request),
            fn() => $this->fromCookie($request),
            fn() => $this->fromUser($request),
            fn() => $this->fromBrowser($request),
            fn() => $this->fromDefault(),
        ];

        foreach ($sources as $source) {
            $language = $source();
            if ($language && $this->isValidLanguage($language)) {
                return $language;
            }
        }

        return config('app.locale');
    }

    /**
     * Get language from query parameter
     */
    protected function fromQueryParameter(Request $request): ?string
    {
        return $request->query('lang') ?? $request->query('locale');
    }

    /**
     * Get language from session
     */
    protected function fromSession(Request $request): ?string
    {
        return $request->session()->get('language');
    }

    /**
     * Get language from cookie
     */
    protected function fromCookie(Request $request): ?string
    {
        return $request->cookie('language');
    }

    /**
     * Get language from authenticated user
     */
    protected function fromUser(Request $request): ?string
    {
        if ($request->user() && isset($request->user()->language)) {
            return $request->user()->language;
        }

        return null;
    }

    /**
     * Get language from browser Accept-Language header
     */
    protected function fromBrowser(Request $request): ?string
    {
        $acceptLanguage = $request->header('Accept-Language');

        if (!$acceptLanguage) {
            return null;
        }

        // Parse Accept-Language header (e.g., "en-US,en;q=0.9,es;q=0.8")
        $languages = [];
        foreach (explode(',', $acceptLanguage) as $lang) {
            $parts = explode(';q=', $lang);
            $code = substr(trim($parts[0]), 0, 2); // Get first 2 chars (en from en-US)
            $quality = isset($parts[1]) ? (float) $parts[1] : 1.0;
            $languages[$code] = $quality;
        }

        // Sort by quality descending
        arsort($languages);

        foreach (array_keys($languages) as $code) {
            if ($this->isValidLanguage($code)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Get default language
     */
    protected function fromDefault(): string
    {
        $defaultLanguage = Language::where('is_default', true)->first();
        return $defaultLanguage?->code ?? config('app.locale');
    }

    /**
     * Check if language code is valid and active
     */
    protected function isValidLanguage(string $code): bool
    {
        return Language::where('code', $code)
            ->where('is_active', true)
            ->exists();
    }
}
```

**2. Register Middleware**

```php
<?php

// In package service provider

public function boot(): void
{
    $router = $this->app['router'];

    $router->aliasMiddleware('detect.language', DetectLanguage::class);
    $router->aliasMiddleware('set.language', SetLanguage::class);

    // Apply globally if configured
    if (config('ai-translator.middleware.auto_detect', true)) {
        $router->pushMiddlewareToGroup('web', DetectLanguage::class);
    }
}
```

**3. Configuration**

```php
<?php

// config/ai-translator.php

return [
    'middleware' => [
        'auto_detect' => env('TRANSLATOR_AUTO_DETECT', true),
        'detection_sources' => [
            'query' => true,
            'session' => true,
            'cookie' => true,
            'user' => true,
            'browser' => true,
        ],
        'cookie_lifetime' => 60 * 24 * 365, // 1 year
    ],
];
```

#### Testing

```php
test('detects language from query parameter', function () {
    $language = createLanguage(['code' => 'es']);

    $response = $this->get('/?lang=es');

    expect(app()->getLocale())->toBe('es');
});

test('detects language from Accept-Language header', function () {
    $language = createLanguage(['code' => 'fr']);

    $response = $this->get('/', ['Accept-Language' => 'fr-FR,fr;q=0.9']);

    expect(app()->getLocale())->toBe('fr');
});

test('falls back to default language if invalid', function () {
    $defaultLang = createLanguage(['code' => 'en', 'is_default' => true]);

    $response = $this->get('/?lang=invalid');

    expect(app()->getLocale())->toBe('en');
});
```

#### Acceptance Criteria
- [ ] Detects language from all configured sources
- [ ] Respects priority order
- [ ] Validates language is active
- [ ] Sets locale correctly
- [ ] Persists selection to session/cookie
- [ ] Tests achieve 90%+ coverage

---

### P2-T09-S02: Language Switching Middleware

**Estimated Time:** 2-3 hours
**Priority:** P2
**Dependencies:** P2-T09-S01

#### Description
Middleware to handle language switching with proper redirects.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

class SetLanguage
{
    /**
     * Handle language switch request
     */
    public function handle(Request $request, Closure $next): Response
    {
        $lang = $request->route('language') ?? $request->input('language');

        if ($lang && $this->isValidLanguage($lang)) {
            app()->setLocale($lang);
            session(['language' => $lang]);

            // Set cookie
            cookie()->queue(
                'language',
                $lang,
                config('ai-translator.middleware.cookie_lifetime', 525600)
            );

            // Update user preference if authenticated
            if ($request->user()) {
                $request->user()->update(['language' => $lang]);
            }

            // Redirect back or to intended URL
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'language' => $lang,
                    'message' => 'Language updated successfully',
                ]);
            }
        }

        return $next($request);
    }

    protected function isValidLanguage(string $code): bool
    {
        return Language::where('code', $code)
            ->where('is_active', true)
            ->exists();
    }
}
```

**Routes**

```php
// Add language switching route
Route::get('/language/{language}', function ($language) {
    return redirect()->back();
})->middleware(['web', 'set.language'])->name('set-language');

Route::post('/api/language', function (Request $request) {
    // Handled by middleware
    return response()->json(['success' => true]);
})->middleware(['set.language']);
```

#### Acceptance Criteria
- [ ] Can switch language via route
- [ ] Can switch language via API
- [ ] Updates session, cookie, and user preference
- [ ] Redirects correctly
- [ ] Validates language code

---

### P2-T09-S03: Localized Routes Middleware

**Estimated Time:** 4-5 hours
**Priority:** P3
**Dependencies:** P2-T09-S01

#### Description
Support for localized URLs (e.g., /en/about, /es/acerca-de).

#### Implementation

**1. Route Localization Service**

```php
<?php

namespace Masum\AiTranslator\Services;

use Illuminate\Support\Facades\Route;
use Masum\AiTranslator\Models\Language;

class LocalizedRouteService
{
    protected array $translatedRoutes = [];

    /**
     * Register localized route group
     */
    public function group(array $routes): void
    {
        $languages = Language::where('is_active', true)->get();

        foreach ($languages as $language) {
            Route::prefix($language->code)
                ->name("{$language->code}.")
                ->middleware(['web', 'set.language'])
                ->group(function () use ($routes, $language) {
                    app()->setLocale($language->code);

                    foreach ($routes as $name => $route) {
                        $path = $this->getTranslatedPath($name, $language->code);

                        Route::get($path, $route['action'])
                            ->name($name);
                    }
                });
        }
    }

    /**
     * Get translated route path
     */
    protected function getTranslatedPath(string $name, string $locale): string
    {
        $key = "routes.{$name}";

        // Try to get translation
        $translation = Translation::where('key', $key)
            ->whereHas('language', fn($q) => $q->where('code', $locale))
            ->value('value');

        return $translation ?? $name;
    }

    /**
     * Generate localized URL
     */
    public function route(string $name, array $parameters = [], ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $routeName = "{$locale}.{$name}";

        if (Route::has($routeName)) {
            return route($routeName, $parameters);
        }

        return route($name, $parameters);
    }
}
```

**2. Helper Functions**

```php
<?php

if (!function_exists('localized_route')) {
    function localized_route(string $name, array $parameters = [], ?string $locale = null): string
    {
        $service = app(LocalizedRouteService::class);
        return $service->route($name, $parameters, $locale);
    }
}

if (!function_exists('current_route_in_locale')) {
    function current_route_in_locale(string $locale): string
    {
        $currentRoute = Route::currentRouteName();
        $currentLocale = app()->getLocale();

        // Remove current locale prefix
        $routeName = str_replace("{$currentLocale}.", '', $currentRoute);

        return localized_route($routeName, Route::current()->parameters(), $locale);
    }
}
```

**3. Example Usage**

```php
// Define localized routes
app(LocalizedRouteService::class)->group([
    'about' => ['action' => [PageController::class, 'about']],
    'contact' => ['action' => [PageController::class, 'contact']],
    'services' => ['action' => [PageController::class, 'services']],
]);

// Use in Blade
<a href="{{ localized_route('about') }}">About</a>

// Language switcher
@foreach(ai_languages() as $lang)
    <a href="{{ current_route_in_locale($lang->code) }}">
        {{ $lang->native_name }}
    </a>
@endforeach
```

#### Acceptance Criteria
- [ ] Routes work with language prefixes
- [ ] Route paths can be translated
- [ ] Helper functions work correctly
- [ ] Language switcher maintains current page
- [ ] SEO-friendly URLs

---

### P2-T09-S04: API Locale Middleware

**Estimated Time:** 2-3 hours
**Priority:** P2
**Dependencies:** None

#### Description
Handle localization for API requests.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiLocale
{
    /**
     * Handle API localization
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get locale from header or query parameter
        $locale = $request->header('Accept-Language')
            ?? $request->header('X-Locale')
            ?? $request->query('locale')
            ?? $request->query('lang')
            ?? config('app.locale');

        // Extract language code (first 2 chars)
        $locale = substr($locale, 0, 2);

        if ($this->isValidLanguage($locale)) {
            app()->setLocale($locale);
        }

        $response = $next($request);

        // Add Content-Language header to response
        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }

    protected function isValidLanguage(string $code): bool
    {
        return Language::where('code', $code)
            ->where('is_active', true)
            ->exists();
    }
}
```

#### Acceptance Criteria
- [ ] Accepts locale from headers
- [ ] Accepts locale from query params
- [ ] Sets Content-Language header
- [ ] Validates language codes
- [ ] Works with API responses

---

### P2-T09-S05: Translation Tracking Middleware

**Estimated Time:** 1-2 hours
**Priority:** P4
**Dependencies:** None

#### Description
Track missing translations during requests.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TrackMissingTranslations
{
    protected array $missingKeys = [];

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('app.debug')) {
            return $next($request);
        }

        // Register translator callback to track missing keys
        app('translator')->setMissingKeysCallback(function ($key) {
            $this->missingKeys[] = [
                'key' => $key,
                'locale' => app()->getLocale(),
                'url' => request()->url(),
            ];
        });

        $response = $next($request);

        // Log or report missing translations
        if (!empty($this->missingKeys)) {
            Log::channel('translations')->warning('Missing translations detected', [
                'count' => count($this->missingKeys),
                'keys' => $this->missingKeys,
            ]);

            // Optionally add to response headers in debug mode
            if (config('app.debug')) {
                $response->headers->set('X-Missing-Translations', count($this->missingKeys));
            }
        }

        return $response;
    }
}
```

#### Acceptance Criteria
- [ ] Tracks missing translation keys
- [ ] Logs missing translations
- [ ] Only active in debug mode
- [ ] Reports useful debugging info

---

## Definition of Done

- [ ] All 5 subtasks completed
- [ ] All acceptance criteria met
- [ ] Middleware registered properly
- [ ] Language detection works reliably
- [ ] Localized routes functional
- [ ] API localization working
- [ ] Tests achieve 85%+ coverage
- [ ] Documentation updated

---

## Notes

- Consider CDN/caching implications for localized routes
- Add middleware configuration options
- Document middleware usage in README
- Consider performance impact of language detection
