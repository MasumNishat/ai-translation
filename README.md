# Laravel AI Translator

AI-powered Laravel translation package with Google Gemini API integration, smart caching, and automatic translation management.

## Features

- **Smart 3-Tier Translation Retrieval** (Cache → Database → AI)
- **Automatic AI Translation** using Google Gemini API
- **Multi-Language Support** with language management
- **Smart Caching** with automatic invalidation
- **API Key Priority** (Database → Config → Environment)
- **Full Audit Trail** for translation changes
- **RESTful API** for translation management
- **Laravel Gates** for permission management
- **Language to Country Mapping** API
- **Batch Translation** support
- **Model Trait** for easy integration
- **Translation History** tracking

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
GEMINI_API_KEY=your_gemini_api_key_here
GEMINI_MODEL=gemini-pro
TRANSLATOR_CACHE_TTL=3600
TRANSLATOR_AUTO_TRANSLATE=true
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

```php
// Get all translations for current locale
$allTranslations = trans_all();

// Translate numbers (useful for Bengali, Arabic, etc.)
echo trans_number(12345, 'bn'); // ১২৩৪৫

// Translate time
echo trans_time('10:30 AM', 'bn');

// Get available languages
$languages = available_languages();

// Get default language
$defaultLang = default_language();

// Get country info for a language
$countryInfo = language_to_country('bn');
// Returns: ['language_code' => 'bn', 'country' => 'Bangladesh', ...]
```

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
POST   /api/translator/batch-translate        # Batch translate
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

The `SetLocale` middleware detects locale from multiple sources:

1. Query parameter: `?locale=bn`
2. Accept-Language header
3. Session
4. Cookie

Configure detection sources in `config/ai-translator.php`:

```php
'detection' => [
    'sources' => ['query', 'header', 'session', 'cookie'],
    'query_param' => 'locale',
    'session_key' => 'locale',
    'cookie_name' => 'app_locale',
],
```

## Advanced Features

### Batch Translation

```php
$service = app(TranslationService::class);

$keyValues = [
    'welcome.title' => 'Welcome',
    'welcome.message' => 'Hello, welcome to our website',
    'button.submit' => 'Submit',
];

$results = $service->batchTranslate(
    keyValues: $keyValues,
    sourceLang: 'en',
    targetLangs: ['bn', 'fr', 'es'],
    group: 'home'
);
```

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
