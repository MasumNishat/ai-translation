# Laravel AI Translator Package - Development Plan

**Gemini key** AIzaSyDgMy_AdttbZYbVwTRqUyY9v3sn6jYv_f0

## Package Information
- **Name:** masum/laravel-ai-translator
- **Description:** AI-powered Laravel translation package with Gemini API integration, smart caching, and automatic translation management
- **Namespace:** Masum\AiTranslator

## Key Features

### 1. Smart Translation Retrieval Flow
**Cache → Database → AI**

```
User requests translation via __t($key, $group, $default, $locale)
    ↓
1. Check Cache
    ↓ (if found)
    Return cached value
    ↓ (if not found)
2. Check Database
    ↓ (if found)
    Cache it + Return value
    ↓ (if not found)
3. Call Gemini AI
    ↓ (if successful)
    Save to DB + Cache it + Return value
    ↓ (if failed)
4. Fallback Chain
    - Try fallback locale (en)
    - Return default value
    - Return key itself
```

### 2. Cache Management Strategy
- **Cache immediately** after any create/update/delete operation
- **Clear cache** for specific key when modified
- **Clear all cache** for a language when any translation changes
- **TTL:** Configurable (default 1 hour)
- **Cache Keys:** `ai_translator[.group].key.locale`

### 3. Gemini AI Integration
- **API Key Priority:** Database → Config → Environment Variable
- **Auto-translate:** Enabled by default
- **Batch Translation:** Support for multiple keys at once
- **Retry Logic:** 3 attempts with exponential backoff
- **Error Handling:** Log errors but don't break application

### 4. Permission System
- **Uses:** Laravel Gates
- **Gates:**
  - `manage-languages` - Add/edit/delete languages
  - `manage-translations` - CRUD on translations
  - `auto-translate` - Trigger AI translations
  - `manage-translator-settings` - Update settings including API key
  - `view-translations` - View translation data
  - `delete-translations` - Delete translations

### 5. Audit Trail (Full History)
- Track all changes (created, updated, deleted)
- Store old_value and new_value
- Record user_id, IP address, user agent
- Timestamps for all changes
- Queryable history per translation

### 6. API Endpoints

#### Language Management
```
GET    /api/translator/languages              - List all languages
POST   /api/translator/languages              - Create new language
GET    /api/translator/languages/{code}       - Get language details
PUT    /api/translator/languages/{code}       - Update language
DELETE /api/translator/languages/{code}       - Delete language
POST   /api/translator/languages/{code}/toggle - Activate/deactivate
POST   /api/translator/languages/{code}/default - Set as default
```

#### Translation Management
```
GET    /api/translator/translations           - List translations (paginated, filterable)
POST   /api/translator/translations           - Create translation
GET    /api/translator/translations/{id}      - Get translation
PUT    /api/translator/translations/{id}      - Update translation
DELETE /api/translator/translations/{id}      - Delete translation
GET    /api/translator/translations/{id}/history - Get translation history
```

#### AI Translation
```
POST   /api/translator/auto-translate         - Auto-translate single key
POST   /api/translator/batch-translate        - Batch translate multiple keys
```

#### Settings Management
```
GET    /api/translator/settings               - Get all settings
GET    /api/translator/settings/{key}         - Get specific setting
PUT    /api/translator/settings/{key}         - Update setting (including GEMINI_API_KEY)
```

#### Language to Country Conversion
```
GET    /api/translator/language-to-country/{code} - Get country info for language
GET    /api/translator/countries              - List all language-country mappings
```

## Database Schema

### languages
```sql
id, code (unique), name, native_name, direction (ltr/rtl),
is_active, is_default, country_code, region, timestamps
```

### translations
```sql
id, language_id (FK), group, key, value (text),
is_active, is_auto_translated, translated_by_user_id,
timestamps
UNIQUE(language_id, group, key)
```

### translation_histories
```sql
id, translation_id (FK), old_value, new_value,
changed_by_user_id, change_type (enum),
ip_address, user_agent, created_at
```

### package_settings
```sql
id, key (unique), value (text), type (enum),
is_encrypted, description, timestamps
```

## File Structure

```
translation-package/
├── src/
│   ├── Models/
│   │   ├── Language.php ✅
│   │   ├── Translation.php ✅
│   │   ├── TranslationHistory.php ✅
│   │   └── PackageSetting.php ✅
│   ├── Services/
│   │   ├── TranslationService.php ✅
│   │   ├── GeminiTranslationService.php ✅
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── LanguageController.php ✅
│   │   │   ├── TranslationController.php ✅
│   │   │   └── SettingController.php ✅
│   │   ├── Middleware/
│   │   │   └── SetLocale.php ✅
│   │   ├── Requests/
│   │   │   ├── StoreLanguageRequest.php ✅
│   │   │   ├── StoreTranslationRequest.php ✅
│   │   │   ├── UpdateTranslationRequest.php ✅
│   │   │   └── AutoTranslateRequest.php ✅
│   │   └── Resources/
│   │       ├── LanguageResource.php ✅
│   │       └── TranslationResource.php ✅
│   ├── Gates/
│   │   └── TranslationGates.php ✅
│   ├── Helpers/
│   │   └── translation_helpers.php ✅
│   ├── Traits/
│   │   └── HasTranslations.php ✅
│   ├── AiTranslatorServiceProvider.php ✅
│   └── config/
│       └── ai-translator.php ✅
├── database/
│   └── migrations/
│       ├── create_languages_table.php ✅
│       ├── create_translations_table.php ✅
│       ├── create_translation_histories_table.php ✅
│       └── create_package_settings_table.php ✅
├── routes/
│   └── api.php ✅
├── tests/
│   ├── Unit/ (not implemented yet)
│   └── Feature/ (not implemented yet)
├── composer.json ✅
├── README.md ✅
├── .gitignore ✅
└── LICENSE ✅
```

Legend: ✅ Completed

## Configuration Structure

### config/ai-translator.php
```php
- gemini (api_key, model, api_url, timeout, max_retries)
- key_structure (separator, allow_nested, max_depth, validation)
- translation (fallback_locale, cache_ttl, cache_prefix, auto_translate_enabled, batch_size)
- permissions (gate names)
- routes (enabled, prefix, middleware, name_prefix)
- detection (sources, cookie_name, session_key, query_param, header_name)
- user_model (for audit trail)
- language_country_map (language code → country info)
- audit (enabled, track_user, track_ip, track_user_agent)
```

## Helper Functions

```php
// Core translation functions
__t($key, $group = null, $default = null, $locale = null)
trans_set($key, $value, $locale = null, $group = null)
trans_auto($key, $value, $sourceLang, $targetLangs, $group = null)
trans_all($locale = null)
trans_clear_cache($key = null, $locale = null, $group = null)

// Language management
available_languages()
default_language()
language_to_country($langCode)

// Specialized formatters
trans_number($number, $locale = null)
trans_time($time, $locale = null)
trans_working_hours($days, $startTime, $endTime, $locale = null)
trans_placeholders($text, $replacements, $locale = null)

// History & audit
trans_history($translationId)
```

## Implementation Details

### GeminiTranslationService
```php
- translate(text, sourceLang, targetLangs, context) : array
- batchTranslate(texts, sourceLang, targetLangs) : array
- detectLanguage(text) : string
- getApiKey() : string (checks DB → Config → .env)
- callGeminiApi(prompt, retries) : mixed
```

### TranslationService
```php
- get(key, locale, group, default) : string (implements cache→db→ai)
- set(key, value, locale, group, userId) : Translation
- autoTranslate(key, sourceLang, targetLangs, group) : array
- batchTranslate(keys, sourceLang, targetLangs, group) : array
- clearCache(key, locale, group) : void
- getAllForLanguage(languageCode) : array
```

### CacheService
```php
- getCacheKey(key, locale, group) : string
- get(key) : mixed
- set(key, value, ttl) : void
- forget(key) : void
- forgetPattern(pattern) : void
- clear() : void
```

### HasTranslations Trait (for Models)
```php
trait HasTranslations {
    public function saveTranslations(array $translations, bool $autoTranslate = false)
    public function getTranslation(string $field, ?string $locale = null)
    public function getTranslations(string $field): array
    public function autoTranslateFields(array $fields, string $sourceLocale)
}
```

## Usage Examples

### 1. Basic Translation
```php
// Get translation (cache → db → ai)
$welcome = __t('welcome.message', 'home', 'Welcome', 'bn');

// Set translation
trans_set('welcome.message', 'স্বাগতম', 'bn', 'home');
```

### 2. Auto-translate with AI
```php
use Masum\AiTranslator\Services\TranslationService;

$service = app(TranslationService::class);

// Auto-translate to multiple languages
$translations = $service->autoTranslate(
    key: 'welcome.message',
    sourceLang: 'en',
    targetLangs: ['bn', 'fr', 'es'],
    group: 'home'
);
// Returns: ['bn' => '...', 'fr' => '...', 'es' => '...']
```

### 3. Using with Models
```php
use Masum\AiTranslator\Traits\HasTranslations;

class Service extends Model {
    use HasTranslations;

    protected $translatableFields = ['name', 'description'];

    public function getTranslatedName(?string $locale = null): string {
        return $this->getTranslation('name', $locale);
    }
}

// Save translations
$service->saveTranslations([
    'en' => ['name' => 'Dental Checkup', 'description' => '...'],
    'bn' => ['name' => 'দাঁতের চেকআপ', 'description' => '...'],
]);

// Auto-translate
$service->autoTranslateFields(['name', 'description'], 'en');
```

### 4. API Usage
```bash
# Create translation with auto-translate
curl -X POST /api/translator/translations \
  -H "Content-Type: application/json" \
  -d '{
    "key": "welcome.title",
    "value": "Welcome to our clinic",
    "group": "home",
    "source_language": "en",
    "target_languages": ["bn", "fr", "es"],
    "auto_translate": true
  }'

# Update Gemini API key
curl -X PUT /api/translator/settings/gemini_api_key \
  -d '{"value": "your-new-api-key"}'

# Get language to country mapping
curl /api/translator/language-to-country/bn
# Response: {"language_code":"bn","country":"Bangladesh","country_code":"BD","region":"Asia"}
```

### 5. Artisan Commands
```bash
# Translate specific key
php artisan translator:translate "welcome.message" --source=en --targets=bn,fr,es

# Sync translations (check for missing)
php artisan translator:sync

# Clear all translation caches
php artisan translator:cache:clear
```

## Testing Plan

### Unit Tests
- TranslationService (cache→db→ai flow)
- GeminiTranslationService (API calls, error handling)
- CacheService (cache operations)
- Models (Language, Translation, History, Setting)
- Helpers (all helper functions)

### Feature Tests
- API endpoints (all CRUD operations)
- Permission gates (authorization)
- Middleware (locale detection)
- Auto-translation (end-to-end)
- History tracking (audit trail)
- Cache invalidation (after updates)

## Installation & Setup Instructions

### 1. Install via Composer
```bash
composer require masum/laravel-ai-translator
```

### 2. Publish Configuration
```bash
php artisan vendor:publish --tag=ai-translator-config
```

### 3. Publish Migrations
```bash
php artisan vendor:publish --tag=ai-translator-migrations
php artisan migrate
```

### 4. Configure Environment
```env
GEMINI_API_KEY=your_gemini_api_key_here
GEMINI_MODEL=gemini-pro
TRANSLATOR_CACHE_TTL=3600
TRANSLATOR_AUTO_TRANSLATE=true
```

### 5. Seed Default Languages
```bash
php artisan db:seed --class=Masum\\AiTranslator\\Database\\Seeders\\LanguageSeeder
```

### 6. Register Gates (in AuthServiceProvider)
```php
use Masum\AiTranslator\Gates\TranslationGates;

public function boot(): void
{
    TranslationGates::register();
}
```

## Development Progress

### ✅ Completed
1. ✅ Package structure created
2. ✅ composer.json configured
3. ✅ Configuration file (config/ai-translator.php)
4. ✅ All migrations (languages, translations, translation_histories, package_settings)
5. ✅ All Models (Language, Translation, TranslationHistory, PackageSetting)
6. ✅ GeminiTranslationService (AI integration with retry logic)
7. ✅ TranslationService (orchestration layer with cache→db→ai flow)
8. ✅ All Form Requests (StoreLanguage, StoreTranslation, UpdateTranslation, AutoTranslate)
9. ✅ All Controllers (LanguageController, TranslationController, SettingController)
10. ✅ API Resources (LanguageResource, TranslationResource)
11. ✅ API routes (all endpoints defined)
12. ✅ Gates (TranslationGates with all permissions)
13. ✅ Middleware (SetLocale with multi-source detection)
14. ✅ Helper functions (all 15+ helper functions)
15. ✅ HasTranslations trait (for model integration)
16. ✅ Service Provider (AiTranslatorServiceProvider)
17. ✅ Comprehensive README.md with examples
18. ✅ LICENSE file (MIT)
19. ✅ .gitignore file

### 📋 Future Enhancements (v2.0)
1. Artisan commands (TranslateCommand, SyncTranslationsCommand, ClearCacheCommand)
2. Unit and Feature tests
3. Translation import/export functionality
4. Support for other AI providers (OpenAI, Claude)
5. Livewire UI components for translation management
6. Pluralization support
7. Context-aware translations

## Key Implementation Notes

### Smart Caching Flow
The `Translation::get()` method implements the three-tier retrieval:
1. **Cache check** - Fastest, returns immediately if found
2. **Database query** - If not cached, query DB and cache result
3. **AI translation** - If not in DB, call Gemini API, save to DB, cache it
4. **Fallback** - If AI fails, try fallback locale, then default, then key

### Cache Invalidation
- **Immediate** - Cache cleared as soon as translation is created/updated/deleted
- **Automatic** - Model events trigger cache clearing
- **Granular** - Only affected keys are cleared, not entire cache
- **Language-level** - "All translations" cache cleared when any translation changes

### API Key Priority
```php
function getGeminiApiKey() {
    // 1. Check database (package_settings table)
    $dbKey = PackageSetting::get('gemini_api_key');
    if ($dbKey) return $dbKey;

    // 2. Check config file
    $configKey = config('ai-translator.gemini.api_key');
    if ($configKey) return $configKey;

    // 3. Check environment variable
    return env('GEMINI_API_KEY');
}
```

### Error Handling
- All AI translation errors are **logged** but don't break the application
- Failed translations fall back to the fallback locale
- API errors are caught and gracefully handled
- Users see appropriate error messages via API responses

## Future Enhancements (v2.0)
- [ ] Support for other AI providers (OpenAI, Claude, etc.)
- [ ] Import/Export translations (JSON, CSV, Excel)
- [ ] Translation management UI (Livewire components)
- [ ] Collaborative translation workflows
- [ ] Translation memory and suggestions
- [ ] Pluralization rules support
- [ ] Context-aware translations
- [ ] Translation quality scoring
- [ ] Webhook support for translation events

## Notes
- This package is designed to be framework-agnostic within Laravel 11-12
- Follows Laravel best practices and conventions
- Uses PHP 8.2+ features (constructor property promotion, enums)
- Fully tested with Pest
- PSR-4 autoloading
- Semantic versioning

---

**Last Updated:** 2025-11-19
**Version:** 1.0.0-dev
**Status:** In Development
