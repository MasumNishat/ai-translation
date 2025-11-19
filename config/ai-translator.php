<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gemini AI Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Google Gemini API settings for automatic translations.
    | Priority: Database → Config → Environment Variable
    |
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-pro'),
        'api_url' => env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => env('GEMINI_TIMEOUT', 30),
        'max_retries' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Translation Key Structure
    |--------------------------------------------------------------------------
    |
    | Configure how translation keys are structured and validated.
    |
    */
    'key_structure' => [
        'separator' => '.',
        'allow_nested' => true,
        'max_depth' => 5,
        'min_key_length' => 2,
        'max_key_length' => 255,
    ],

    /*
    |--------------------------------------------------------------------------
    | Translation Behavior
    |--------------------------------------------------------------------------
    |
    | Configure caching, fallback, and auto-translation behavior.
    |
    */
    'translation' => [
        'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

        // Cache TTL in seconds (3600 = 1 hour)
        'cache_ttl' => env('TRANSLATOR_CACHE_TTL', 3600),

        // Cache key prefix
        'cache_prefix' => 'ai_translator',

        // Enable automatic AI translation for missing translations
        'auto_translate_enabled' => env('TRANSLATOR_AUTO_TRANSLATE', true),

        // Batch size for bulk operations
        'batch_size' => 50,

        // Queue AI translations (set to false for sync)
        'queue_translations' => false,

        // Translation retrieval flow: cache → db → ai
        'retrieval_flow' => ['cache', 'db', 'ai'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Gates
    |--------------------------------------------------------------------------
    |
    | Define gate names for translation management permissions.
    |
    */
    'permissions' => [
        'manage_languages' => 'manage-languages',
        'manage_translations' => 'manage-translations',
        'auto_translate' => 'auto-translate',
        'manage_settings' => 'manage-translator-settings',
        'view_translations' => 'view-translations',
        'delete_translations' => 'delete-translations',
        'export_translations' => 'export-translations',
        'import_translations' => 'import-translations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security & Authorization
    |--------------------------------------------------------------------------
    |
    | Configure security and authorization behavior.
    |
    */
    'security' => [
        // Require authentication for all API endpoints
        'require_authentication' => env('TRANSLATOR_REQUIRE_AUTH', false),

        // Allow guest access when authentication is not required
        'allow_guest_access' => env('TRANSLATOR_ALLOW_GUEST', true),

        // Authorization mode: 'permissive' allows all when no user, 'strict' denies
        'authorization_mode' => env('TRANSLATOR_AUTH_MODE', 'permissive'),

        // Superadmin bypass: users with this permission bypass all checks
        'superadmin_permission' => env('TRANSLATOR_SUPERADMIN', 'translator-superadmin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Configure API route prefix and middleware.
    |
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'api/translator',
        'middleware' => ['api'],
        'name_prefix' => 'translator.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Locale Detection
    |--------------------------------------------------------------------------
    |
    | Configure how the package detects the current locale.
    | Sources are checked in order: query → header → session → cookie
    |
    */
    'detection' => [
        'sources' => ['query', 'header', 'session', 'cookie'],
        'cookie_name' => 'app_locale',
        'session_key' => 'locale',
        'query_param' => 'locale',
        'header_name' => 'Accept-Language',
    ],

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The User model for audit trail and history tracking.
    |
    */
    'user_model' => env('TRANSLATOR_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Language to Country Mapping
    |--------------------------------------------------------------------------
    |
    | Map language codes to their primary countries/regions.
    |
    */
    'language_country_map' => [
        'en' => ['country' => 'United States', 'country_code' => 'US', 'region' => 'Americas'],
        'bn' => ['country' => 'Bangladesh', 'country_code' => 'BD', 'region' => 'Asia'],
        'es' => ['country' => 'Spain', 'country_code' => 'ES', 'region' => 'Europe'],
        'fr' => ['country' => 'France', 'country_code' => 'FR', 'region' => 'Europe'],
        'de' => ['country' => 'Germany', 'country_code' => 'DE', 'region' => 'Europe'],
        'zh' => ['country' => 'China', 'country_code' => 'CN', 'region' => 'Asia'],
        'ja' => ['country' => 'Japan', 'country_code' => 'JP', 'region' => 'Asia'],
        'ar' => ['country' => 'Saudi Arabia', 'country_code' => 'SA', 'region' => 'Middle East'],
        'hi' => ['country' => 'India', 'country_code' => 'IN', 'region' => 'Asia'],
        'pt' => ['country' => 'Portugal', 'country_code' => 'PT', 'region' => 'Europe'],
        'ru' => ['country' => 'Russia', 'country_code' => 'RU', 'region' => 'Europe'],
        'ko' => ['country' => 'South Korea', 'country_code' => 'KR', 'region' => 'Asia'],
        'it' => ['country' => 'Italy', 'country_code' => 'IT', 'region' => 'Europe'],
        'nl' => ['country' => 'Netherlands', 'country_code' => 'NL', 'region' => 'Europe'],
        'tr' => ['country' => 'Turkey', 'country_code' => 'TR', 'region' => 'Middle East'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Trail
    |--------------------------------------------------------------------------
    |
    | Enable full audit trail for translation changes.
    |
    */
    'audit' => [
        'enabled' => true,
        'track_user' => true,
        'track_ip' => false,
        'track_user_agent' => false,
    ],
];
