# Laravel AI Translator

AI-powered Laravel translation package with Google Gemini API integration, smart caching, and automatic translation management.

## Features

- **Smart 3-Tier Translation Retrieval** (Cache → Database → AI)
- **Automatic AI Translation** using Google Gemini API
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
- Laravel 11 or 12
- Google Gemini API key

## Installation

### 1. Install via Composer

```bash
composer require masum/laravel-ai-translator
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=ai-translator-config
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

Add the locale middleware to your routes:

```php
// In bootstrap/app.php or routes
->middleware(['translator.locale'])
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

```php
// Translate numbers (useful for Bengali, Arabic, Persian, etc.)
echo trans_number(12345, 'bn'); // ১২৩৪৫
echo trans_number(789, 'ar'); // ٧٨٩
echo trans_number(456, 'fa'); // ۴۵۶

// Translate time format
echo trans_time('10:30 AM', 'bn'); // ১০:৩০ পূর্বাহ্ণ

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
| `trans_number()` | Translate numbers | `trans_number(123, 'bn')` |
| `trans_time()` | Translate time format | `trans_time('10:30 AM', 'bn')` |
| `trans_working_hours()` | Translate working hours | `trans_working_hours('Mon-Fri', '9AM', '5PM')` |
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

The middleware checks sources in the following priority order:

1. **Query Parameter** - `?locale=bn`
2. **Accept-Language Header** - `Accept-Language: bn,en-US;q=0.9`
3. **Session** - Stored from previous request
4. **Cookie** - Persisted locale preference

### How It Works

```php
// 1. Query parameter (highest priority)
GET /api/translator/translations?locale=bn

// 2. Accept-Language header
curl -H "Accept-Language: bn,en-US;q=0.9,en;q=0.8" https://example.com/api

// 3. Session (automatically stored when locale is set)
session(['locale' => 'bn']);

// 4. Cookie (automatically persisted for 30 days)
// Cookie: app_locale=bn
```

### Accept-Language Header Support

The middleware intelligently parses the `Accept-Language` header:

```php
// Standard format with quality values
Accept-Language: bn,en-US;q=0.9,en;q=0.8,fr;q=0.7

// The middleware will:
// 1. Parse all language codes
// 2. Validate against active languages in database
// 3. Return the first valid language
// 4. Store in cookie for future requests
```

**Example:**
```bash
# Browser automatically sends Accept-Language header
curl -H "Accept-Language: bn" https://example.com/api/translator/translations

# The response will be in Bengali (bn) if it's an active language
# A cookie will be set: app_locale=bn (expires in 30 days)
```

### Configuration

Configure detection sources in `config/ai-translator.php`:

```php
'detection' => [
    // Detection sources in priority order
    'sources' => ['query', 'header', 'session', 'cookie'],

    // Query parameter name
    'query_param' => 'locale',

    // HTTP header name for locale detection
    'header_name' => 'Accept-Language',

    // Session key for storing locale
    'session_key' => 'locale',

    // Cookie settings
    'cookie_name' => 'app_locale',
    'cookie_expires' => 43200, // 30 days in minutes
    'persist_in_cookie' => true, // Auto-persist from header/query
],
```

### Environment Variables

```env
# Disable automatic cookie persistence
TRANSLATOR_PERSIST_LOCALE=false
```

### Cookie Persistence

When locale is detected from **header** or **query parameter**, it's automatically stored in a cookie for future requests:

- **Cookie Name:** `app_locale` (configurable)
- **Expiration:** 30 days (configurable)
- **Disable:** Set `persist_in_cookie` to `false`

This means:
- First request with `?locale=bn` → Cookie set
- Subsequent requests → Locale remembered (no need to send query param)
- Works with Accept-Language header too!

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
