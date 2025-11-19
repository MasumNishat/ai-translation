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
    | Configure fallback and auto-translation behavior.
    |
    */
    'translation' => [
        'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

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
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching strategy for translation retrieval.
    | Cache tagging allows granular invalidation by language or group.
    |
    */
    'cache' => [
        // Enable caching
        'enabled' => env('TRANSLATOR_CACHE_ENABLED', true),

        // Cache TTL in seconds (3600 = 1 hour, 0 = forever)
        'ttl' => env('TRANSLATOR_CACHE_TTL', 3600),

        // Cache key prefix to avoid conflicts
        'prefix' => env('TRANSLATOR_CACHE_PREFIX', 'ai_translator'),

        // Cache tags for granular invalidation
        // Note: Only supported by redis, memcached, and array drivers
        'use_tags' => env('TRANSLATOR_CACHE_USE_TAGS', true),

        // Warm up cache on application boot
        'warmup_on_boot' => env('TRANSLATOR_CACHE_WARMUP', false),

        // Languages to warm up (empty = all)
        'warmup_languages' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure queue settings for asynchronous translation operations.
    | Queuing translations improves UX for slow AI operations.
    |
    */
    'queue' => [
        // Enable queue processing for translations
        'enabled' => env('TRANSLATOR_QUEUE_ENABLED', true),

        // Queue name for single translation jobs
        'name' => env('TRANSLATOR_QUEUE_NAME', 'translations'),

        // Queue name for batch/bulk translation jobs
        'bulk_name' => env('TRANSLATOR_QUEUE_BULK_NAME', 'translations-bulk'),

        // Queue connection (null = default)
        'connection' => env('TRANSLATOR_QUEUE_CONNECTION', null),

        // Job timeout in seconds
        'timeout' => env('TRANSLATOR_QUEUE_TIMEOUT', 120),

        // Number of retry attempts for failed jobs
        'retries' => env('TRANSLATOR_QUEUE_RETRIES', 3),

        // Backoff strategy in seconds for retries
        'backoff' => [10, 30, 60], // 10s, 30s, 60s

        // Enable job batching for bulk operations
        'batch_enabled' => env('TRANSLATOR_BATCH_ENABLED', true),

        // Batch size for splitting large operations
        'batch_size' => env('TRANSLATOR_BATCH_SIZE', 50),
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
        // Enable public API mode (bypasses all authentication/authorization)
        // WARNING: Only enable for public-facing APIs or testing
        'public_api' => env('TRANSLATOR_PUBLIC_API', false),

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

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for API endpoints to prevent abuse.
    | Different limiters can be applied to different endpoint groups.
    |
    */
    'rate_limiting' => [
        // General translation API requests
        'translations' => [
            'max_attempts' => env('TRANSLATOR_RATE_LIMIT', 60),
            'decay_seconds' => 60, // 1 minute
        ],

        // Auto-translation requests (more expensive, stricter limit)
        'auto_translate' => [
            'max_attempts' => env('TRANSLATOR_AI_RATE_LIMIT', 10),
            'decay_seconds' => 60, // 1 minute
        ],

        // Import/Export operations (bulk operations, very strict)
        'bulk' => [
            'max_attempts' => env('TRANSLATOR_BULK_RATE_LIMIT', 5),
            'decay_seconds' => 60, // 1 minute
        ],

        // Language management
        'languages' => [
            'max_attempts' => env('TRANSLATOR_LANGUAGE_RATE_LIMIT', 30),
            'decay_seconds' => 60, // 1 minute
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Input Sanitization
    |--------------------------------------------------------------------------
    |
    | Configure how translation values are sanitized to prevent XSS and
    | other injection attacks. Different modes provide different levels
    | of security vs. flexibility.
    |
    */
    'sanitization' => [
        // Sanitization mode: strict, moderate, permissive, none
        // - strict: No HTML allowed, all special chars encoded
        // - moderate: Some HTML tags allowed (b, i, a, etc.)
        // - permissive: Most HTML allowed but dangerous content removed
        // - none: No sanitization (use with caution!)
        'mode' => env('TRANSLATOR_SANITIZATION_MODE', 'moderate'),

        // Enable sanitization for translation values
        'enabled' => env('TRANSLATOR_SANITIZATION_ENABLED', true),

        // Sanitize on input (when creating/updating translations)
        'sanitize_on_input' => true,

        // Sanitize on output (when retrieving translations)
        'sanitize_on_output' => false,

        // Log sanitization warnings
        'log_warnings' => env('APP_DEBUG', false),

        // Allowed HTML tags (for moderate mode)
        'allowed_tags' => ['b', 'i', 'u', 'strong', 'em', 'a', 'br', 'p', 'span'],

        // Allowed HTML attributes per tag
        'allowed_attributes' => [
            'a' => ['href', 'title', 'target'],
            'span' => ['class'],
        ],
    ],
];
