# Laravel AI Translator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/masum/laravel-ai-translator.svg?style=flat-square)](https://packagist.org/packages/masum/laravel-ai-translator)
[![Total Downloads](https://img.shields.io/packagist/dt/masum/laravel-ai-translator.svg?style=flat-square)](https://packagist.org/packages/masum/laravel-ai-translator)
[![PHP Version](https://img.shields.io/packagist/php-v/masum/laravel-ai-translator.svg?style=flat-square)](https://packagist.org/packages/masum/laravel-ai-translator)
[![License](https://img.shields.io/packagist/l/masum/laravel-ai-translator.svg?style=flat-square)](https://packagist.org/packages/masum/laravel-ai-translator)

AI-powered Laravel translation package with Google Gemini API integration, smart caching, and automatic translation management.

---

## Table of Contents

- [Features](#features) — What the package provides at a glance
- [Requirements](#requirements) — PHP, Laravel, and API key prerequisites
- [Installation](#installation) — Step-by-step setup guide
  - [1. Install via Composer](#1-install-via-composer)
  - [2. Publish Configuration](#2-publish-configuration)
  - [3. Publish and Run Migrations](#3-publish-and-run-migrations)
  - [4. Configure Environment](#4-configure-environment)
  - [5. Add Languages](#5-add-languages)
  - [6. Register Gates (Optional)](#6-register-gates-optional)
  - [7. Add Middleware (Optional)](#7-add-middleware-optional)
- [Configuration](#configuration) — All available config options explained
- [Transparent `__()` Override](#transparent---override-zero-blade-changes) — Drop-in AI translation with no Blade changes required
- [Middleware & Locale Detection](#middleware) — How language is detected, persisted, and switched per request
- [Queue Configuration](#queue-configuration) — Async translation processing with database or Redis queues
- [Rate Limiting](#rate-limiting) — Protect the Gemini API from abuse with configurable limits
- [Usage](#usage) — How to translate strings, use helpers, and integrate with models
  - [Basic Translation](#basic-translation) — `__()`, `trans()`, and `Translation::get()`
  - [Auto-Translate with AI](#auto-translate-with-ai) — Trigger AI translation on demand
  - [Helper Functions](#using-helper-functions) — Full reference of all helper functions (numbers, time, language, etc.)
  - [Using with Models](#using-with-models) — Translate model attributes automatically
- [API Reference](#api-endpoints) — RESTful endpoints for language and translation management
  - [Endpoints](#api-endpoints) — Full list of available routes
  - [Examples](#api-examples) — cURL and request examples
- [Smart Caching](#smart-caching-flow) — 3-tier caching architecture and invalidation strategy
- [Permission Gates](#permission-gates) — Control access to translation management features
- [API Key Priority](#api-key-priority) — How the Gemini API key is resolved (DB → Config → Env)
- [Audit Trail](#audit-trail) — Full history of translation changes with user tracking
- [Translation Groups](#translation-groups) — Organise translations by namespace or module
- [Advanced Features](#advanced-features) — Finding missing keys, clearing cache, and more
- [Building a Custom Admin UI](#building-a-custom-admin-translation-manager) — How to build your own translation manager on top of this package
- [Markdown File Translation](#markdown-file-translation) — Translate entire `.md` files (front matter + body) with one Artisan command
- [API Quota Caution](#️-api-quota-caution-free-tier) — Free tier limits, call counts, and how to stay within quota
- [Troubleshooting](#troubleshooting) — Common issues and fixes
- [Testing](#testing)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [License](#license)
- [Credits](#credits)
- [Support](#support)

---

## Features

- **Smart 3-Tier Translation Retrieval** (Cache → Database → AI)
- **Automatic AI Translation** using Google Gemini API
- **Deferred Batch Translation** — missing keys collected during request, translated in one Gemini call after response is sent (zero page-load overhead)
- **Transparent `__()` Override** — drop-in replacement for Laravel's translator, no Blade changes needed
- **Markdown File Translation** — translate entire `.md` files (front matter + body) into locale sub-directories with one Artisan command
- **Multi-Language Support** with language management
- **Smart Caching** with automatic invalidation and cache tagging
- **Queue System** for asynchronous translation processing
- **Rate Limiting** to prevent API abuse and ensure fair usage
- **API Key Priority** (Database → Config → Environment)
- **Full Audit Trail** for translation changes
- **RESTful API** for translation management
- **Laravel Gates** for permission management
- **Language to Country Mapping** API
- **Model Trait** for easy integration
- **Translation History** tracking
- **Input Sanitization** for security
- **Database Indexing** for optimized queries

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12, or 13
- Google Gemini API key — get one free at [aistudio.google.com](https://aistudio.google.com)
- `google-gemini-php/laravel` package (pulled in automatically)

## Installation

### 1. Install via Composer

```bash
composer require masum/laravel-ai-translator
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=ai-translator-config
```

Also publish the Gemini Laravel config (required for the Gemini singleton to read your API key):

```bash
php artisan vendor:publish --provider="Gemini\Laravel\ServiceProvider"
```

### 3. Publish and Run Migrations

```bash
php artisan vendor:publish --tag=ai-translator-migrations
php artisan migrate
```

### 4. Configure Environment

Add to your `.env` file:

```env
# Gemini AI Configuration
GEMINI_API_KEY=your_gemini_api_key_here
GEMINI_MODEL=gemini-pro
GEMINI_TIMEOUT=30

# Cache Configuration
TRANSLATOR_CACHE_ENABLED=true
TRANSLATOR_CACHE_TTL=3600
TRANSLATOR_CACHE_PREFIX=ai_translator
TRANSLATOR_CACHE_USE_TAGS=true

# Translation Settings
TRANSLATOR_AUTO_TRANSLATE=true

# Queue Configuration (Optional - for background processing)
QUEUE_CONNECTION=redis
TRANSLATOR_QUEUE_ENABLED=true
TRANSLATOR_QUEUE_CONNECTION=redis
TRANSLATOR_QUEUE_NAME=translations
TRANSLATOR_QUEUE_BULK_NAME=translations-bulk
TRANSLATOR_QUEUE_TIMEOUT=120
TRANSLATOR_QUEUE_RETRIES=3

# Rate Limiting
TRANSLATOR_RATE_LIMIT=60
TRANSLATOR_AI_RATE_LIMIT=10
TRANSLATOR_BULK_RATE_LIMIT=5
TRANSLATOR_LANGUAGE_RATE_LIMIT=30

# Security (Optional)
TRANSLATOR_REQUIRE_AUTH=false
TRANSLATOR_ALLOW_GUEST=true
TRANSLATOR_SANITIZATION_ENABLED=true

# Locale Detection (Optional)
TRANSLATOR_PERSIST_LOCALE=true
```

### 5. Add Languages

Create languages in your database:

```php
use Masum\AiTranslator\Models\Language;

Language::create([
    'code' => 'en',
    'name' => 'English',
    'native_name' => 'English',
    'direction' => 'ltr',
    'is_default' => true,
    'is_active' => true,
]);

Language::create([
    'code' => 'bn',
    'name' => 'Bengali',
    'native_name' => 'বাংলা',
    'direction' => 'ltr',
    'is_active' => true,
]);
```

### 6. Register Gates (Optional)

In your `AuthServiceProvider.php`:

```php
use Masum\AiTranslator\Gates\TranslationGates;

public function boot(): void
{
    TranslationGates::register();
}
```

### 7. Add Middleware (Optional)

Register the `SetLocale` middleware in `bootstrap/app.php`. **Important:** it must be appended (not prepended) to the web group so it runs after `StartSession` — the session is not available before that.

```php
use Masum\AiTranslator\Http\Middleware\SetLocale;

->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        SetLocale::class,
    ]);
})
```

## Configuration

The package configuration file (`config/ai-translator.php`) includes:

```php
return [
    // Gemini AI settings
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-pro'),
        'timeout' => 30,
        'max_retries' => 3,
    ],

    // Translation behavior
    'translation' => [
        'fallback_locale' => 'en',
        'cache_ttl' => 3600,
        'auto_translate_enabled' => true,
    ],

    // Queue configuration
    'queue' => [
        'enabled' => env('TRANSLATOR_QUEUE_ENABLED', true),
        'name' => env('TRANSLATOR_QUEUE_NAME', 'translations'),
        'bulk_name' => env('TRANSLATOR_QUEUE_BULK_NAME', 'translations-bulk'),
        'connection' => env('TRANSLATOR_QUEUE_CONNECTION', null),
        'timeout' => env('TRANSLATOR_QUEUE_TIMEOUT', 120),
        'retries' => env('TRANSLATOR_QUEUE_RETRIES', 3),
        'backoff' => [10, 30, 60],
    ],

    // Rate limiting
    'rate_limiting' => [
        'translations' => [
            'max_attempts' => env('TRANSLATOR_RATE_LIMIT', 60),
            'decay_seconds' => 60,
        ],
        'auto_translate' => [
            'max_attempts' => env('TRANSLATOR_AI_RATE_LIMIT', 10),
            'decay_seconds' => 60,
        ],
        'bulk' => [
            'max_attempts' => env('TRANSLATOR_BULK_RATE_LIMIT', 5),
            'decay_seconds' => 60,
        ],
        'languages' => [
            'max_attempts' => env('TRANSLATOR_LANGUAGE_RATE_LIMIT', 30),
            'decay_seconds' => 60,
        ],
    ],

    // Permission gates
    'permissions' => [
        'manage_languages' => 'manage-languages',
        'manage_translations' => 'manage-translations',
        'auto_translate' => 'auto-translate',
        'manage_settings' => 'manage-translator-settings',
    ],

    // API routes
    'routes' => [
        'enabled' => true,
        'prefix' => 'api/translator',
        'middleware' => ['api'],
    ],
];
```

## Queue Configuration

The package supports asynchronous translation processing using Laravel's queue system. This improves performance by offloading expensive AI operations to background workers.

### Features

- **Asynchronous Processing** - AI translations run in the background
- **Automatic Retries** - Failed jobs retry with exponential backoff (10s, 30s, 60s)
- **Graceful Fallback** - Falls back to synchronous processing if queue fails

### Setup Queue Workers

#### For Development (Database Queue)

1. Create queue tables:
```bash
php artisan queue:table
php artisan queue:batches-table
php artisan migrate
```

2. Start queue worker:
```bash
php artisan queue:work --queue=translations-bulk,translations
```

#### For Production (Redis Recommended)

1. Configure Redis in `.env`:
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

2. Start queue worker with recommended options:
```bash
php artisan queue:work redis \
    --queue=translations-bulk,translations \
    --tries=3 \
    --timeout=120 \
    --sleep=3 \
    --max-jobs=1000 \
    --max-time=3600
```

#### Supervisor Configuration (Production)

Create `/etc/supervisor/conf.d/ai-translator-worker.conf`:

```ini
[program:ai-translator-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/artisan queue:work redis --queue=translations-bulk,translations --tries=3 --timeout=120 --max-jobs=1000 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/your/storage/logs/worker.log
stopwaitsecs=3600
```

Then reload Supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start ai-translator-worker:*
```

### Queue Management Commands

```bash
# Monitor queue status
php artisan queue:monitor translations,translations-bulk

# Restart workers (after code changes)
php artisan queue:restart

# Retry failed jobs
php artisan queue:retry all

# Flush failed jobs
php artisan queue:flush
```

### Disable Queues (Process Synchronously)

Set in `.env`:
```env
TRANSLATOR_QUEUE_ENABLED=false
```

Or force synchronous processing via API:
```bash
POST /api/translator/auto-translate?sync=true
```

## Rate Limiting

The package implements rate limiting to prevent API abuse and ensure fair usage across different endpoint types.

### Rate Limit Categories

| Category | Default Limit | Description |
|----------|--------------|-------------|
| **Translations** | 60/min | General translation API requests |
| **Auto-Translate** | 10/min | AI-powered translation requests (expensive) |
| **Languages** | 30/min | Language management endpoints |

### Environment Configuration

#### Development Settings
```env
TRANSLATOR_RATE_LIMIT=1000
TRANSLATOR_AI_RATE_LIMIT=100
TRANSLATOR_BULK_RATE_LIMIT=50
TRANSLATOR_LANGUAGE_RATE_LIMIT=300
```

#### Production (Low Traffic)
```env
TRANSLATOR_RATE_LIMIT=60
TRANSLATOR_AI_RATE_LIMIT=10
TRANSLATOR_BULK_RATE_LIMIT=5
TRANSLATOR_LANGUAGE_RATE_LIMIT=30
```

#### Production (High Traffic)
```env
TRANSLATOR_RATE_LIMIT=120
TRANSLATOR_AI_RATE_LIMIT=20
TRANSLATOR_BULK_RATE_LIMIT=10
TRANSLATOR_LANGUAGE_RATE_LIMIT=60
```

#### Enterprise
```env
TRANSLATOR_RATE_LIMIT=300
TRANSLATOR_AI_RATE_LIMIT=50
TRANSLATOR_BULK_RATE_LIMIT=25
TRANSLATOR_LANGUAGE_RATE_LIMIT=150
```

### Rate Limit Response

When rate limit is exceeded, API returns:

```json
{
  "message": "Too Many Requests",
  "status": 429,
  "retry_after": 60
}
```

### Monitoring Rate Limits

Check logs for rate limit hits:
```bash
tail -f storage/logs/laravel.log | grep "rate_limit"
```

## Transparent `__()` Override (Zero Blade Changes)

The recommended integration for existing Laravel applications is to override Laravel's built-in `__()` helper transparently via a custom translator class. This means **no changes to any Blade view** — all existing `__()`, `trans()`, and `@lang()` calls automatically go through the AI pipeline.

Missing translations are **collected in a static list during the request** and batch-translated in a single Gemini API call after the response is sent — so the current page load is never slowed down and the next page load is fully translated.

### 1. Create the Custom Translator

Create `app/Translation/AiTranslator.php`:

```php
<?php

namespace App\Translation;

use Illuminate\Translation\Translator;
use Masum\AiTranslator\Models\Translation;
use Masum\AiTranslator\Services\GeminiTranslationService;

/**
 * Overrides Laravel's translator so that __(), trans(), and @lang() in Blade
 * all go through the 3-tier AI lookup (cache → DB → Gemini) without any
 * changes to existing view files.
 *
 * Missing translations are collected during the request and batch-translated
 * in a single Gemini API call each after the response is sent — so the current
 * request is never slowed down and the next page load is fully translated.
 */
class AiTranslator extends Translator
{
    /** @var array<string, string[]> locale => [key, ...] */
    private static array $pending = [];

    private static bool $shutdownRegistered = false;

    public function get($key, array $replace = [], $locale = null, $fallback = true): string|array
    {
        $locale ??= $this->locale();

        $sourceLang = config('ai-translator.translation.fallback_locale', 'en');
        if ($locale === $sourceLang) {
            return parent::get($key, $replace, $locale, $fallback);
        }

        // Check cache + DB only (no AI call on this request).
        // Pass $key as the default — this is the "deferred" signal that
        // tells Translation::get() to skip inline AI translation.
        $translation = Translation::get($key, $locale, null, $key);

        if ($translation !== $key) {
            return $this->makeReplacements($translation, $replace);
        }

        // Queue the key for batch translation after the response is sent.
        $this->enqueuePending($key, $locale);

        return parent::get($key, $replace, $locale, $fallback);
    }

    private function enqueuePending(string $key, string $locale): void
    {
        if (! isset(self::$pending[$locale])) {
            self::$pending[$locale] = [];
        }

        if (in_array($key, self::$pending[$locale], true)) {
            return;
        }

        self::$pending[$locale][] = $key;

        if (! self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function(static fn () => self::flushPending());
        }
    }

    /**
     * Called after the response is sent. Batch-translates all collected missing
     * keys per locale in a single Gemini API call each.
     */
    public static function flushPending(): void
    {
        if (empty(self::$pending) || ! config('ai-translator.translation.auto_translate_enabled', true)) {
            return;
        }

        $sourceLang = config('ai-translator.translation.fallback_locale', 'en');
        $service = app(GeminiTranslationService::class);

        foreach (self::$pending as $locale => $keys) {
            if (empty($keys)) {
                continue;
            }

            try {
                // One Gemini call for all missing keys in this locale.
                $results = $service->translate($keys, $sourceLang, [$locale]);

                foreach ($results as $lang => $translated) {
                    // $translated may be a single string (one key) or array (multiple).
                    $values = is_array($translated) ? $translated : [$translated];
                    foreach ($keys as $i => $key) {
                        if (isset($values[$i])) {
                            Translation::set($key, $values[$i], $lang);
                        }
                    }
                }
            } catch (\Throwable $e) {
                logger()->error('Batch translation failed', [
                    'locale' => $locale,
                    'keys'   => $keys,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        self::$pending = [];
    }
}
```

### 2. Register in AppServiceProvider

In `app/Providers/AppServiceProvider.php`, replace Laravel's translator in `register()`:

```php
use App\Translation\AiTranslator;

public function register(): void
{
    $this->app->extend('translator', function ($original, $app) {
        $translator = new AiTranslator(
            $app['translation.loader'],
            $app->getLocale(),
        );
        $translator->setFallback($app->getFallbackLocale());

        return $translator;
    });
}
```

This works because `__()` internally calls `app('translator')->get(...)`. By replacing the binding, all translation helpers are transparently intercepted.

### 3. Inject Translations for JavaScript (Optional)

For Alpine.js or other frontend frameworks that need translations client-side, inject all translations into the page as `window.lang` in your layout's `<head>`:

```html
<script>window.lang = @json(trans_all());</script>
```

Then access them in JS without HTTP requests:

```js
window.lang['Save changes'] ?? 'Save changes'
```

### 4. Translating Inline HTML (Sentences with Markup)

When a sentence contains inline HTML (e.g. a `<span>` with a color), wrap the entire sentence — including the markup — in a single `__()` call and use `{!! !!}` to output unescaped HTML. This sends the full sentence to Gemini as one unit, giving much better translation quality than splitting it.

```blade
{{-- ✅ Good: full sentence + HTML in one call --}}
{!! __('The operations layer for <span style="color:var(--color-primary);">fiber networks.</span>') !!}

{{-- ❌ Bad: split into two calls — Gemini translates fragments without context --}}
{{ __('The operations layer for') }} <span ...>{{ __('fiber networks.') }}</span>
```

Gemini's prompt instructs it to preserve HTML tags (`<b>`, `<i>`, `<span>`, etc.) exactly, so the markup is safe to include in the translation key.

### How It Works

**During the request** — cache/DB only, no AI calls:

```
User visits page with locale = 'pt_BR'
        │
        ▼
__('Dashboard') called in Blade
        │
        ▼
AiTranslator::get('Dashboard', [], 'pt_BR')
        │
        ├─ locale == fallback locale? → YES → parent::get() (lang/ files)
        │
        ▼
Translation::get('Dashboard', 'pt_BR', null, 'Dashboard')
        │
    ┌───┴───────────────────┐
    │ Cache hit?            │ → YES → return cached value instantly
    └───────────────────────┘
        │ NO
        ▼
    ┌───────────────────────┐
    │ DB hit?               │ → YES → cache + return
    └───────────────────────┘
        │ NO — key missing
        ▼
AiTranslator: add 'Dashboard' to $pending['pt_BR']
Register shutdown function (once per request)
        │
        ▼
parent::get() → lang/ file or key itself (shown to user now)
```

**After the response is sent** — one Gemini call per locale:

```
register_shutdown_function fires
        │
        ▼
AiTranslator::flushPending()
        │
        ├─ $pending = ['pt_BR' => ['Dashboard', 'Save changes', 'Sign out', ...]]
        │
        ▼
GeminiTranslationService::translate(
    ['Dashboard', 'Save changes', 'Sign out', ...],
    sourceLang: 'en',
    targetLangs: ['pt_BR']
)
        │  (one HTTP call, all keys at once)
        ▼
Results saved → Translation::set() per key
Cached → next page load serves from cache instantly
```

Second page load: every key hits cache — zero AI cost, zero latency.

> **Key-as-default trick:** When `AiTranslator` calls `Translation::get($key, $locale, null, $key)`, the `$default` argument equals `$key`. Inside `Translation::get()` this is detected as the "deferred" signal (`$isDeferred = $default === $key`), which skips the inline AI call and lets `AiTranslator` handle batching instead.

---

## Usage

### Basic Translation

```php
// Get translation (cache → db → ai)
$welcomeMessage = __t('welcome.message', 'home', 'Welcome', 'bn');

// Set translation
trans_set('welcome.message', 'স্বাগতম', 'bn', 'home');
```

### Auto-Translate with AI

```php
use Masum\AiTranslator\Services\TranslationService;

$service = app(TranslationService::class);

// Auto-translate to multiple languages
$translations = $service->autoTranslate(
    key: 'welcome.title',
    sourceValue: 'Welcome to our website',
    sourceLang: 'en',
    targetLangs: ['bn', 'fr', 'es'],
    group: 'home'
);
```

### Using Helper Functions

The package provides a comprehensive set of helper functions for easy translation management.

#### Core Translation Functions

```php
// Get translation with smart caching (cache → db → ai)
$text = __t('welcome.message', 'home', 'Welcome', 'bn');
// Parameters: key, group, default, locale

// Set or update a translation
$translation = trans_set('welcome.message', 'স্বাগতম', 'bn', 'home');
// Parameters: key, value, locale, group, userId

// Auto-translate a key to multiple languages using AI
$translations = trans_auto(
    key: 'welcome.title',
    value: 'Welcome to our website',
    sourceLang: 'en',
    targetLangs: ['bn', 'fr', 'es'],
    group: 'home'
);

// Get all translations for current locale
$allTranslations = trans_all();
$allTranslations = trans_all('bn'); // specific locale

// Clear translation cache
trans_clear_cache(); // Clear all
trans_clear_cache('welcome.message', 'bn', 'home'); // Clear specific
trans_clear_cache(null, 'bn'); // Clear all for a language

// Get all translation groups
$groups = trans_groups();
// Returns: ['home', 'services', 'common', ...]

// Get translation history
$history = trans_history($translationId, 50);
```

#### Language Management Functions

```php
// Get all active languages
$languages = available_languages();

// Get the default language
$defaultLang = default_language();

// Get country info for a language
$countryInfo = language_to_country('bn');
// Returns: ['language_code' => 'bn', 'country' => 'Bangladesh', 'country_code' => 'BD', ...]

// Get all active languages (alternative)
$languages = ai_languages();
$allLanguages = ai_languages(false); // include inactive

// Get default language (alternative)
$defaultLang = ai_default_language();

// Get current language based on app locale
$currentLang = ai_current_language();

// Set application locale
$success = ai_set_language('bn'); // Returns true/false

// Get count of missing translations for a language
$missingCount = ai_trans_missing('bn');
```

#### Number & Time Translation Functions

##### `trans_number()` — Locale-Aware Numeral Rendering

Converts Western digits (0–9) into the numeral system of the target locale. This is a **pure digit substitution** — it works on any string containing digits (counts, prices, years, etc.).

```php
echo trans_number(12345, 'bn'); // ১২৩৪৫
echo trans_number(789, 'ar');   // ٧٨٩
echo trans_number(456, 'fa');   // ۴۵۶
echo trans_number(42, 'th');    // ๔๒
echo trans_number(100, 'en');   // 100  (no-op — Western digits already)
```

**Supported locales:** `bn`, `ar`, `fa`, `ur`, `ps`, `sd`, `ku`, `ug`, `pa`, `gu`, `or`, `ml`, `ta`, `te`, `kn`, `my`, `th`, `lo`, `km`, `dz`, `bo`. For any other locale the input is returned unchanged, so it is safe to use everywhere.

> **⚠️ Always use `trans_number()` when rendering dynamic numbers alongside translated strings in Blade.**
> Without it, the number stays in Western digits even though the surrounding text is fully translated — a common AI oversight.

**Blade usage pattern:**

```blade
{{-- ❌ Wrong — number stays as "34" even in Bengali --}}
{{ count($features) }} {!! __('features — zero point-tools.') !!}

{{-- ✅ Correct — renders as "৩৪ বৈশিষ্ট্যসমূহ — জিরো পয়েন্ট-টুলস।" in Bengali --}}
{{ trans_number(count($features)) }} {!! __('features — zero point-tools.') !!}
```

Apply to any dynamic value rendered next to translated text:

```blade
{{-- item counts --}}
{{ trans_number($cart->count()) }} {{ __('items in cart') }}

{{-- prices / amounts --}}
{{ trans_number(number_format($price, 2)) }} {{ __('BDT') }}

{{-- pagination --}}
{{ trans_number($currentPage) }} / {{ trans_number($totalPages) }}

{{-- years / dates --}}
{{ trans_number(date('Y')) }}
```

##### `trans_time()` — Translated Time Strings

```php
// Translate time format (translates AM/PM labels and converts digits)
echo trans_time('10:30 AM', 'bn'); // ১০:৩০ পূর্বাহ্ণ
```

##### `trans_working_hours()` — Working Hours Display

```php
// Translate working hours display
echo trans_working_hours('Monday-Friday', '9:00 AM', '5:00 PM', 'bn');
// Output: সোমবার-শুক্রবার: ৯:০০ পূর্বাহ্ণ - ৫:০০ অপরাহ্ণ
```

#### Text Processing Functions

```php
// Replace placeholders in text with translations
$text = trans_placeholders(
    'Hello {{name}}, welcome to {{place}}',
    ['name' => 'John', 'place' => 'common.website'],
    'bn'
);
// Supports both {{key}} and :key formats
```

#### AI-Powered Translation Functions

```php
// Translate with replacements (Laravel-style)
$text = ai_trans('welcome.message', ['name' => 'John'], 'bn');

// Translate with pluralization
$text = ai_trans_choice('items.count', 5, ['count' => 5], 'bn');
// Looks for 'items.count.singular' or 'items.count.plural'

// Check if translation exists
if (ai_has_trans('welcome.message', 'bn')) {
    // Translation exists in database
}

// Get translations for multiple keys at once
$translations = ai_trans_array(['key1', 'key2', 'key3'], 'bn');
// Returns: ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3']

// Get all translations for a specific group
$serviceTranslations = ai_trans_group('services', 'bn');
// Returns: ['service.name' => 'value', 'service.desc' => 'value', ...]
```

#### Function Reference Table

| Function | Purpose | Example |
|----------|---------|---------|
| `__t()` | Get translation with AI fallback | `__t('key', 'group', 'default', 'bn')` |
| `trans_set()` | Set/update translation | `trans_set('key', 'value', 'bn')` |
| `trans_auto()` | Auto-translate to multiple languages | `trans_auto('key', 'value', 'en', ['bn'])` |
| `trans_all()` | Get all translations for a locale | `trans_all('bn')` |
| `trans_clear_cache()` | Clear translation cache | `trans_clear_cache()` |
| `trans_groups()` | Get all translation groups | `trans_groups()` |
| `trans_history()` | Get translation history | `trans_history($id)` |
| `available_languages()` | Get active languages | `available_languages()` |
| `default_language()` | Get default language | `default_language()` |
| `language_to_country()` | Get country info | `language_to_country('bn')` |
| `trans_number()` | Convert digits to locale numerals — **always use alongside `__()`** | `trans_number(count($items), 'bn')` |
| `trans_time()` | Translate time string (AM/PM + digits) | `trans_time('10:30 AM', 'bn')` |
| `trans_working_hours()` | Translate working hours display | `trans_working_hours('Mon-Fri', '9AM', '5PM')` |
| `trans_placeholders()` | Replace placeholders | `trans_placeholders('Hello {{name}}', [...])` |
| `ai_trans()` | Translate with replacements | `ai_trans('key', ['name' => 'John'])` |
| `ai_trans_choice()` | Translate with pluralization | `ai_trans_choice('key', 5, ['count' => 5])` |
| `ai_has_trans()` | Check if translation exists | `ai_has_trans('key', 'bn')` |
| `ai_trans_array()` | Get multiple translations | `ai_trans_array(['key1', 'key2'])` |
| `ai_trans_group()` | Get group translations | `ai_trans_group('services', 'bn')` |
| `ai_languages()` | Get all languages | `ai_languages()` |
| `ai_default_language()` | Get default language | `ai_default_language()` |
| `ai_current_language()` | Get current language | `ai_current_language()` |
| `ai_set_language()` | Set app locale | `ai_set_language('bn')` |
| `ai_trans_missing()` | Get missing translation count | `ai_trans_missing('bn')` |

### Using with Models

Add the `HasTranslations` trait to your model:

```php
use Masum\AiTranslator\Traits\HasTranslations;

class Service extends Model
{
    use HasTranslations;

    protected array $translatableFields = ['name', 'description', 'short_description'];

    public function getTranslationGroup(): string
    {
        return 'services';
    }
}
```

Then use it:

```php
// Save translations
$service->saveTranslations([
    'en' => [
        'name' => 'Dental Checkup',
        'description' => 'Complete dental examination',
    ],
    'bn' => [
        'name' => 'দাঁতের চেকআপ',
        'description' => 'সম্পূর্ণ দাঁতের পরীক্ষা',
    ],
]);

// Get translated name
$name = $service->getTranslation('name', 'bn');
// Or use magic method:
$name = $service->getTranslatedName('bn');

// Auto-translate all fields
$service->autoTranslateFields(['name', 'description'], 'en');

// Get all translations for a field
$nameTranslations = $service->getTranslations('name');
// Returns: ['en' => 'Dental Checkup', 'bn' => 'দাঁতের চেকআপ', ...]
```

## API Endpoints

All API endpoints use the prefix `/api/translator` by default.

### Language Management

```http
GET    /api/translator/languages              # List all languages
POST   /api/translator/languages              # Create language
GET    /api/translator/languages/{code}       # Get language
PUT    /api/translator/languages/{code}       # Update language
DELETE /api/translator/languages/{code}       # Delete language
POST   /api/translator/languages/{code}/toggle # Toggle active status
POST   /api/translator/languages/{code}/default # Set as default
```

### Translation Management

```http
GET    /api/translator/translations           # List translations
POST   /api/translator/translations           # Create translation
GET    /api/translator/translations/{id}      # Get translation
PUT    /api/translator/translations/{id}      # Update translation
DELETE /api/translator/translations/{id}      # Delete translation
GET    /api/translator/translations/{id}/history # Get history
GET    /api/translator/translations/groups    # Get groups
POST   /api/translator/translations/clear-cache # Clear cache
```

### AI Translation

```http
POST   /api/translator/auto-translate         # Auto-translate single key
```

### Settings Management

```http
GET    /api/translator/settings               # Get all settings
GET    /api/translator/settings/{key}         # Get setting
PUT    /api/translator/settings/{key}         # Update setting
DELETE /api/translator/settings/{key}         # Delete setting
```

### Language to Country

```http
GET    /api/translator/language-to-country/{code} # Get country info
GET    /api/translator/countries              # Get all mappings
```

## API Examples

### Create Translation with Auto-Translate

```bash
curl -X POST /api/translator/translations \
  -H "Content-Type: application/json" \
  -d '{
    "key": "welcome.title",
    "value": "Welcome to our clinic",
    "language_code": "en",
    "group": "home",
    "auto_translate": true,
    "target_languages": ["bn", "fr", "es"]
  }'
```

### Update Gemini API Key

```bash
curl -X PUT /api/translator/settings/gemini_api_key \
  -H "Content-Type: application/json" \
  -d '{"value": "your-new-api-key"}'
```

### Get Language to Country Mapping

```bash
curl /api/translator/language-to-country/bn
```

Response:
```json
{
  "success": true,
  "data": {
    "language_code": "bn",
    "language_name": "Bengali",
    "country": "Bangladesh",
    "country_code": "BD",
    "region": "Asia"
  }
}
```

## Smart Caching Flow

The package implements a 3-tier translation retrieval system:

```
┌─────────────────┐
│  User Request   │
│  __t('key')     │
└────────┬────────┘
         │
         ▼
   ┌──────────┐
   │  Cache?  │
   └────┬─────┘
        │ No
        ▼
   ┌──────────┐
   │Database? │
   └────┬─────┘
        │ No
        ▼
   ┌──────────┐
   │ AI (Gemini)│
   └────┬─────┘
        │
        ▼
   ┌──────────┐
   │ Save & Cache│
   └──────────┘
```

**Benefits:**
- Fast response from cache (1st tier)
- Reliable fallback to database (2nd tier)
- Automatic translation via AI (3rd tier)
- Cache invalidation on create/update/delete

## Permission Gates

The package defines the following gates:

- `manage-languages` - Create, update, delete languages
- `manage-translations` - CRUD operations on translations
- `auto-translate` - Trigger AI translations
- `manage-translator-settings` - Update settings including API key
- `view-translations` - View translation data
- `delete-translations` - Delete translations

Customize gate logic in your `AuthServiceProvider`:

```php
Gate::define('manage-languages', function ($user) {
    return $user->isAdmin();
});
```

## API Key Priority

The Gemini API key is retrieved with the following priority:

1. **Database** (package_settings table)
2. **Config file** (config/ai-translator.php)
3. **Environment variable** (.env file)

This allows updating the API key from the frontend without redeploying.

## Audit Trail

All translation changes are tracked:

- Old value and new value
- User who made the change
- Change type (created, updated, deleted)
- IP address and user agent (optional)
- Timestamp

Access history via API:
```http
GET /api/translator/translations/{id}/history
```

## Translation Groups

Organize translations into groups for better management:

```php
// Common UI elements
trans_set('submit_button', 'Submit', 'en', 'common');

// Service-specific translations
trans_set('dental-checkup.name', 'Dental Checkup', 'en', 'services');

// Page-specific translations
trans_set('hero.title', 'Welcome', 'en', 'home');
```

## Middleware

The `SetLocale` middleware automatically detects and sets the application locale from multiple sources.

### Locale Detection Flow

The middleware checks sources in the following priority order (default configuration):

1. **Query Parameter** - `?locale=bn`
2. **Session** - Stored from previous request (e.g. after a language-switch redirect)
3. **Cookie** - Persisted locale preference

> **Note on `Accept-Language` header:** The `header` source is supported but **not recommended for web apps** where users can switch locale. Browsers always send their OS language as `Accept-Language` (e.g. `en-US`), which would override an explicit user-selected locale stored in the session on every request. Omit `header` from the `sources` list for user-selectable locales; keep it only for pure API endpoints.

### How It Works

```php
// 1. Query parameter (highest priority — useful for one-time switches)
GET /page?locale=bn

// 2. Session (set by language-switch route)
session(['locale' => 'bn']);

// 3. Cookie (automatically persisted)
// Cookie: app_locale=bn
```

### Configuration

Configure detection sources in `config/ai-translator.php`:

```php
'detection' => [
    // Detection sources in priority order.
    // Omit 'header' for web apps — browser Accept-Language headers
    // would override an explicit user-selected locale in the session.
    'sources' => ['query', 'session', 'cookie'],

    // Query parameter name
    'query_param' => 'locale',

    // HTTP header name for locale detection (only used when 'header' is in sources)
    'header_name' => 'Accept-Language',

    // Session key for storing locale
    'session_key' => 'locale',

    // Cookie settings
    'cookie_name' => 'app_locale',
    'cookie_expires' => 43200, // 30 days in minutes
    'persist_in_cookie' => true, // Auto-persist from query
],
```

### Environment Variables

```env
# Disable automatic cookie persistence
TRANSLATOR_PERSIST_LOCALE=false
```

### Cookie Persistence

When locale is detected from the **query parameter**, it's automatically stored in a cookie for future requests:

- **Cookie Name:** `app_locale` (configurable)
- **Expiration:** 30 days (configurable)
- **Disable:** Set `persist_in_cookie` to `false`

This means:
- First request with `?locale=bn` → Cookie set
- Subsequent requests → Locale remembered (no need to send query param)

### Usage with API Clients

#### JavaScript/Fetch
```javascript
// Option 1: Query parameter
fetch('/api/translator/translations?locale=bn');

// Option 2: Accept-Language header
fetch('/api/translator/translations', {
  headers: {
    'Accept-Language': 'bn'
  }
});
```

#### cURL
```bash
# Query parameter
curl "https://example.com/api/translator/translations?locale=bn"

# Accept-Language header
curl -H "Accept-Language: bn" https://example.com/api/translator/translations
```

#### Axios
```javascript
// Set default Accept-Language header
axios.defaults.headers.common['Accept-Language'] = 'bn';

// Or per request
axios.get('/api/translator/translations', {
  headers: { 'Accept-Language': 'bn' }
});
```

### Language Switcher (Recommended Pattern)

Add a route that switches the locale and redirects back:

```php
// routes/web.php
Route::get('/language/{code}', function (string $code) {
    if (ai_set_language($code)) {
        app()->setLocale($code);
    }
    return redirect()->back(fallback: '/');
})->name('language.switch');
```

`ai_set_language()` stores the locale in the session key defined by `config('ai-translator.detection.session_key', 'locale')` and in a cookie, so the `SetLocale` middleware picks it up on every subsequent request.

Then render a language dropdown in your Blade layout:

```blade
@php
    use Masum\AiTranslator\Models\Language;
    $languages = Language::where('is_active', true)->orderBy('name')->get();
    $currentCode = app()->getLocale();
@endphp

@if($languages->count() > 1)
    <div class="lang-selector">
        @foreach($languages as $lang)
            <a href="{{ route('language.switch', $lang->code) }}"
               class="{{ $lang->code === $currentCode ? 'active' : '' }}">
                {{ $lang->native_name }}
            </a>
        @endforeach
    </div>
@endif
```

### Manual Locale Setting

You can also set locale programmatically:

```php
// In your controller or middleware
app()->setLocale('bn');

// Or using helper function
ai_set_language('bn');
```

## Advanced Features

### Find Missing Translations

```php
$missing = $service->syncMissingTranslations('services');
// Returns array of missing translations
```

### Clear Cache

```php
// Clear specific translation
trans_clear_cache('welcome.message', 'bn', 'home');

// Clear all translations for a language
trans_clear_cache(null, 'bn');

// Clear all translation caches
trans_clear_cache();
```

## Building a Custom Admin Translation Manager

If you want to build an admin UI (Blade, Livewire, Filament, etc.) where editors can view and edit translations directly, there are a few implementation details you need to follow to work correctly with the package's internal design.

### How keys are stored

The `key` column in the `translations` table stores **`md5($sourceText)`** — never the original English string. This keeps the column length predictable regardless of how long the source string is. There is no `source_text` column; the hash is the identifier.

```
translations
├── id
├── language_id
├── group          (nullable — Laravel translation group)
├── key            ← md5("Your source text here")
├── value          ← "অনুবাদিত পাঠ্য"
├── is_auto_translated
└── translated_by_user_id
```

This means:
- You **cannot** reverse a key back to its source text (MD5 is one-way).
- Searching by `key` is only useful if you already know the MD5 hash.
- Search the `value` column to find translations by their translated content.

### Correct cache invalidation after update

When you update a translation via `$translation->update()`, the model's `saved` hook calls `clearCache()`. However, `clearCache()` passes `$this->key` (already an MD5 hash) to `CacheService::forget()`, which hashes it again — resulting in a double-hash that doesn't match the stored cache entry.

The reliable fix is to build and bust the correct cache key directly after updating:

```php
use Illuminate\Support\Facades\Cache;
use Masum\AiTranslator\Models\Translation;

public function updateTranslation(Request $request, int $id): RedirectResponse
{
    $translation = Translation::with('language')->findOrFail($id);

    $validated = $request->validate([
        'value' => ['required', 'string', 'min:1'],
    ]);

    $translation->update([
        'value'                 => $validated['value'],
        'is_auto_translated'    => false,
        'translated_by_user_id' => auth()->id(),
        'is_active'             => true,
    ]);

    // Build the correct cache key manually.
    // $translation->key is already md5(source_text), so do NOT hash it again.
    $prefix    = config('ai-translator.translation.cache_prefix', 'ai_translator');
    $groupPart = $translation->group ? ".{$translation->group}" : '';
    Cache::forget("{$prefix}{$groupPart}.{$translation->key}.{$translation->language->code}");

    return back()->with('success', 'Translation updated.');
}
```

The cache key format used internally is:

```
{prefix}.{group (if set)}.{md5(source_text)}.{locale}
# e.g. ai_translator.dc09f4b19e8e42f901857acb84a5c910.bn
# e.g. ai_translator.messages.dc09f4b19e8e42f901857acb84a5c910.bn
```

### Example: listing translations for a language

```php
use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;

public function translations(Request $request, string $code): View
{
    $language = Language::where('code', $code)->firstOrFail();

    $query = Translation::where('language_id', $language->id);

    // Search by translated value only — key is an MD5 hash and not searchable.
    if ($search = $request->input('search')) {
        $query->where('value', 'like', "%{$search}%");
    }

    if ($group = $request->input('group')) {
        $query->where('group', $group);
    }

    // Show strings that have not been translated yet.
    if ($request->boolean('missing')) {
        $query->where(function ($q) {
            $q->whereNull('value')->orWhere('value', '');
        });
    }

    $translations = $query->orderBy('group')->orderBy('key')->paginate(50)->withQueryString();

    return view('your.view', compact('language', 'translations'));
}
```

### Deleting a translation (re-queue for AI)

Deleting a translation record removes it from the DB and clears its cache via the model's `deleted` hook. On the next page load that calls `__('source text')` for that locale, the key will be queued for AI translation again via `BatchTranslateJob`.

```php
public function deleteTranslation(int $id): RedirectResponse
{
    Translation::findOrFail($id)->delete();

    return back()->with('success', 'Translation removed — will be re-queued for AI translation on next visit.');
}
```

## Markdown File Translation

The database is the right storage for short UI strings (`__('Details →')`). It is the **wrong** storage for long-form content like documentation articles, blog posts, or feature pages — translating those sentence-by-sentence through `__()` is expensive and misses the article body entirely.

Use `translator:translate-markdown` instead: it translates a whole file (front matter + body) in one pass and saves a locale-specific copy on disk. Controllers resolve the right file at runtime with zero DB overhead.

### Why file-based for markdown?

| Concern | DB strings | Markdown files |
|---|---|---|
| Short labels (`Details →`) | ✓ ideal | wasteful |
| Article body (paragraphs, lists, tables) | ✗ not supported | ✓ full translation |
| Cache overhead per request | DB query + cache | single `file_exists()` |
| Token cost | per string | per section chunk |
| Re-translate after source edit | automatic | run command again |

### Command usage

```bash
# Translate all .md files in a directory to all active non-source locales
php artisan translator:translate-markdown feature-pages/

# Translate to a specific locale
php artisan translator:translate-markdown feature-pages/ --locale=bn

# Translate to multiple locales
php artisan translator:translate-markdown feature-pages/ --locale=bn,fr,ar

# Translate a single file
php artisan translator:translate-markdown feature-pages/map/cable-drawing.md --locale=bn

# Re-translate and overwrite existing locale files
php artisan translator:translate-markdown feature-pages/ --locale=bn --force

# Override the source language (default: fallback_locale from config)
php artisan translator:translate-markdown docs/ --locale=bn --source=en
```

### Output structure

The command writes translated files into `{locale}/` sub-directories alongside the originals:

```
feature-pages/
  map/
    cable-drawing.md          ← source (English)
    core-trace.md
  bn/
    map/
      cable-drawing.md        ← Bengali translation (generated)
      core-trace.md
  fr/
    map/
      cable-drawing.md        ← French translation (generated)
```

The command automatically **skips** any directory whose name is a two-letter locale code (like `bn/`, `fr/`), so running it again on the same root never re-translates already-translated files unless `--force` is passed.

### What gets translated

**Front matter** — only content fields are translated; metadata is preserved verbatim:

| Field | Translated |
|---|---|
| `title`, `lead`, `description`, `excerpt`, `summary` | ✓ yes |
| `tags`, `sort_order`, `reading_time`, `icon` | ✗ kept as-is |

**Body** — split on `## ` level-2 headings. Each section is a separate Gemini call, keeping token usage predictable for articles of any length.

### Resolving locale files in controllers

After running the command, update your controller to check for the locale file before falling back to English:

```php
$locale     = app()->getLocale();
$sourceLang = config('ai-translator.translation.fallback_locale', 'en');

// e.g. feature-pages/bn/map/cable-drawing.md
$localePath = base_path("feature-pages/{$locale}/{$dir}/" . basename($file));
$raw        = ($locale !== $sourceLang && file_exists($localePath))
    ? file_get_contents($localePath)
    : file_get_contents($file);

$meta = $this->parseFrontMatter($raw);
// $meta['title'] and $meta['lead'] are now already in the user's language
// $raw body is also already translated — render it directly
```

This pattern gives you:
- **Zero `__()` calls** for markdown content — the file itself is the translation
- **Full body translation** — not just front matter
- **English fallback** — if the locale file doesn't exist yet, the English original is served
- **No DB queries** for content — just a `file_exists()` check

### Running in CI/CD

Add the command to your deployment pipeline so new or edited content is automatically translated:

```yaml
# .github/workflows/deploy.yml
- name: Translate markdown content
  run: php artisan translator:translate-markdown feature-pages/ --locale=bn,fr
```

Or run it manually whenever source files change:

```bash
php artisan translator:translate-markdown feature-pages/ --force
```

---

## ⚠️ API Quota Caution (Free Tier)

> **This only applies the first time a key is seen.** Once translated and cached, zero Gemini calls are made — ever. The quota is only relevant during the initial "learning" phase.

### How many API calls does one page load make?

The batch translator collects all missing keys on a page and splits them into chunks of `batch_size` (default: **10**). Each chunk = **1 Gemini API call**.

```
API calls per page load = ceil(missing_keys / batch_size)
```

**Example — a marketing landing page (first visit in a new locale):**

| Stat | Value |
|------|-------|
| Unique translatable strings on page | ~35 |
| Default `batch_size` | 10 |
| Gemini calls on first load | `ceil(35 / 10)` = **4 calls** |
| Gemini calls on second load | **0** (all cached) |

### Free tier limits (as of 2025)

| Model | Free requests/min | Free requests/day |
|-------|:-----------------:|:-----------------:|
| `gemini-2.0-flash` | 15 | 1,500 |
| `gemini-2.5-flash` | 10 | 500 |
| `gemini-1.5-flash` | 15 | 1,500 |

> Check current limits at [ai.google.dev/gemini-api/docs/rate-limits](https://ai.google.dev/gemini-api/docs/rate-limits)

### When will you hit the limit?

With default `batch_size=10` and a 15 req/min free quota (e.g. `gemini-2.0-flash`):

```
Pages you can translate simultaneously = floor(15 / 4) ≈ 3 pages/min
```

**Risk scenario:** Reloading a page with 35 new keys repeatedly during development:
- Load 1 → 4 calls (11 remaining quota)
- Load 2 → 4 calls (7 remaining quota)
- Load 3 → 4 calls (3 remaining quota)
- Load 4 → 4 calls → **quota exceeded** (retry in ~26s)

### How to avoid hitting the limit

**Option 1 — Increase `batch_size` (fewer calls per page):**
```env
# .env
TRANSLATOR_BATCH_SIZE=20
```
With `batch_size=20`: `ceil(35/20)` = 2 calls per page → 7 pages/min before quota.

> **Trade-off:** Larger batches produce more output tokens. If the translated text in a verbose language (Bengali, Arabic, Chinese) exceeds `GEMINI_MAX_OUTPUT_TOKENS`, the response will be truncated and the translation will fail. Start at 10–15 and increase only if your strings are short.

**Option 2 — Use a paid Gemini API plan:**

Paid plans remove the per-minute cap entirely. Recommended for production.

**Option 3 — Pre-translate with Artisan (before going live):**

Run a seeder or Artisan command to translate all known keys before the site goes live, so users never trigger live API calls.

**Option 4 — Disable auto-translate, translate manually:**
```env
TRANSLATOR_AUTO_TRANSLATE=false
```
Translations only happen when you explicitly call the API.

### Quick reference: `batch_size` vs calls per page

| `batch_size` | Keys on page | API calls | Pages/min (15 req/min quota) |
|:---:|:---:|:---:|:---:|
| 5 | 35 | 7 | 2 |
| 10 | 35 | 4 | 3 |
| 15 | 35 | 3 | 5 |
| 20 | 35 | 2 | 7 |
| 35 | 35 | 1 | 15 |

---

## Troubleshooting

### Language switch has no effect / reverts to English on reload

**Cause:** `SetLocale` middleware is registered with `prepend` instead of `append`, so it runs before `StartSession` and cannot read the session.

**Fix:** Always append it:

```php
// bootstrap/app.php
$middleware->web(append: [SetLocale::class]);
// NOT: $middleware->web(prepend: [SetLocale::class]);
```

---

### Locale from session is ignored, browser language always wins

**Cause:** `header` is included in detection `sources` before `session`. Browsers send `Accept-Language: en-US` on every request, overriding the user-selected session value.

**Fix:** Remove `header` from sources for web apps:

```php
'sources' => ['query', 'session', 'cookie'],
```

---

### AI translation not working — Gemini API key is null

**Cause:** `google-gemini-php/laravel` binds its singleton using `config('gemini.api_key')` at first resolution. If you haven't published `config/gemini.php`, the key is always null regardless of `.env`.

**Fix:**

```bash
php artisan vendor:publish --provider="Gemini\Laravel\ServiceProvider"
```

Then set `GEMINI_API_KEY` in `.env`.

---

### `__PHP_Incomplete_Class` error from cache

**Cause:** Stale serialized Eloquent models in the cache from before the package was installed (or after a PHP/package upgrade).

**Fix:** Clear the application cache:

```bash
php artisan cache:clear
```

The package's `Language::getActive()` also validates cached values and self-heals on the next request.

---

### Stack overflow / infinite recursion in translation

**Cause:** This was a bug in early versions where `Translation::translateWithAi()` could recurse into itself when the source locale matched the fallback locale.

**Fix:** Ensure you are on the latest package version. The fix is:
- The guard `if ($targetLanguage === $fallbackLocale) return null;` runs **before** the `$sourceText === null` check, not inside it.
- `Translation::get()` skips inline AI when called via `AiTranslator` (detected by `$default === $key`).

---

### File changes to local package not picked up (symlinked path repository)

**Cause:** PHP-FPM (or Apache) caches compiled opcache bytecode. Changes to files in a symlinked `vendor/` path are not seen until opcache is cleared.

**Fix:** Restart PHP-FPM:

```bash
sudo systemctl restart php-fpm
# or
sudo systemctl restart php8.x-fpm
```

---

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## Contributing

Contributions are welcome! Please submit a pull request or open an issue.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

- **Author:** Masum
- **Laravel Framework:** Taylor Otwell
- **Google Gemini API:** Google

## Support

For issues and questions, please open an issue on GitHub.

---

Made with ❤️ for the Laravel community
