Package Architecture Design

1. Package Structure

translation-package/
├── src/
│   ├── Models/
│   │   ├── Translation.php
│   │   ├── TranslationHistory.php
│   │   ├── Language.php
│   │   └── PackageSetting.php
│   ├── Services/
│   │   ├── TranslationService.php
│   │   ├── GeminiTranslationService.php
│   │   └── LanguageDetectionService.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── LanguageController.php
│   │   │   ├── TranslationController.php
│   │   │   └── SettingController.php
│   │   ├── Middleware/
│   │   │   └── SetLocale.php
│   │   ├── Requests/
│   │   │   ├── StoreLanguageRequest.php
│   │   │   ├── TranslateRequest.php
│   │   │   └── UpdateSettingRequest.php
│   │   └── Resources/
│   │       ├── TranslationResource.php
│   │       └── LanguageResource.php
│   ├── Gates/
│   │   └── TranslationGates.php
│   ├── Helpers/
│   │   └── translation_helpers.php
│   ├── Console/
│   │   └── Commands/
│   │       ├── TranslateCommand.php
│   │       └── SyncTranslationsCommand.php
│   ├── Traits/
│   │   └── HasTranslations.php
│   ├── AiTranslatorServiceProvider.php
│   └── config/
│       └── ai-translator.php
├── database/
│   └── migrations/
│       ├── create_languages_table.php
│       ├── create_translations_table.php
│       ├── create_translation_histories_table.php
│       └── create_package_settings_table.php
├── routes/
│   └── api.php
├── tests/
├── composer.json
└── README.md

2. Database Schema

languages table:
- id
- code (en, bn, fr) - unique
- name (English, Bengali, French)
- native_name (English, বাংলা, Français)
- direction (ltr/rtl)
- is_active
- is_default
- country_code (US, BD, FR) - nullable
- region - nullable
- created_at, updated_at

translations table:
- id
- language_id (FK to languages)
- group - nullable, indexed
- key - indexed
- value - text
- is_active
- is_auto_translated (boolean - from AI)
- translated_by_user_id - nullable (FK to users)
- created_at, updated_at
- unique(language_id, group, key)

translation_histories table:
- id
- translation_id (FK to translations)
- old_value
- new_value
- changed_by_user_id (FK to users) - nullable
- change_type (created, updated, deleted)
- created_at

package_settings table:
- id
- key (gemini_api_key, default_locale, fallback_locale, cache_ttl, etc.)
- value - text
- type (string, integer, boolean, json)
- is_encrypted (boolean - for API keys)
- created_at, updated_at
- unique(key)

3. Core Configuration (config/ai-translator.php)

return [
// API Configuration
'gemini' => [
'api_key' => env('GEMINI_API_KEY'),
'model' => env('GEMINI_MODEL', 'gemini-pro'),
'timeout' => env('GEMINI_TIMEOUT', 30),
],

      // Key structure configuration
      'key_structure' => [
          'separator' => '.', // e.g., 'services.dental-checkup.name'
          'allow_nested' => true,
          'max_depth' => 5,
      ],

      // Translation behavior
      'translation' => [
          'fallback_locale' => 'en',
          'cache_ttl' => 3600, // 1 hour
          'auto_translate' => true, // Auto-translate on create
          'batch_size' => 50, // For bulk operations
      ],

      // Permissions
      'permissions' => [
          'manage_languages' => 'manage-languages',
          'manage_translations' => 'manage-translations',
          'auto_translate' => 'auto-translate',
          'manage_settings' => 'manage-translator-settings',
      ],

      // API Routes
      'routes' => [
          'prefix' => 'api/translator',
          'middleware' => ['api', 'auth:sanctum'],
      ],

      // Language detection
      'detection' => [
          'sources' => ['query', 'header', 'session', 'cookie'],
          'cookie_name' => 'app_locale',
          'session_key' => 'locale',
          'query_param' => 'locale',
      ],

      // User model for audit trail
      'user_model' => env('TRANSLATOR_USER_MODEL', 'App\\Models\\User'),
];

4. Key Features Implementation

A. Gemini AI Translation Service

class GeminiTranslationService
{
public function translate(
string $text,
string $sourceLang,
array $targetLangs,
array $context = []
): array;

      public function batchTranslate(
          array $texts,
          string $sourceLang,
          array $targetLangs
      ): array;

      public function detectLanguage(string $text): string;
}

B. API Endpoints

# Language Management
GET    /api/translator/languages              - List all languages
POST   /api/translator/languages              - Add new language
PUT    /api/translator/languages/{code}       - Update language
DELETE /api/translator/languages/{code}       - Delete language
POST   /api/translator/languages/{code}/toggle - Activate/deactivate

# Translation Management
GET    /api/translator/translations           - List translations (filterable)
POST   /api/translator/translations           - Create translation
PUT    /api/translator/translations/{id}      - Update translation
DELETE /api/translator/translations/{id}      - Delete translation

# AI Translation
POST   /api/translator/auto-translate         - Auto-translate using AI
POST   /api/translator/batch-translate        - Batch translate multiple keys

# Settings
GET    /api/translator/settings               - Get all settings
PUT    /api/translator/settings/{key}         - Update setting (includes API key)

# Language to Country Conversion
GET    /api/translator/language-to-country/{code} - Get country info for language
GET    /api/translator/countries              - List all language-country mappings

C. Helper Functions

// Enhanced from your current implementation
__t($key, $group, $default, $locale) - Get translation
trans_set($key, $value, $locale, $group) - Set translation
trans_auto($key, $value, $sourceLang, $targetLangs, $group) - AI translate and save
trans_all($locale) - Get all translations
trans_number($number, $locale) - Translate digits
trans_time($time, $locale) - Translate time format
trans_history($translationId) - Get translation history
available_languages() - Get active languages
default_language() - Get default language
language_to_country($langCode) - Convert language code to country info

D. Trait for Models

trait HasTranslations
{
public function saveTranslations(array $translations, bool $autoTranslate = false);
public function getTranslation(string $field, ?string $locale = null);
public function getTranslations(string $field);
public function autoTranslateFields(array $fields, string $sourceLocale);
}

5. Usage Example

// 1. User configures key structure in config
// 2. Create translation with AI support
$translation = app(TranslationService::class)->createWithAI([
'key' => 'welcome.title',
'group' => 'home',
'value' => 'Welcome to our clinic',
'source_language' => 'en',
'target_languages' => ['bn', 'fr', 'es'], // From config
]);

// Result: Creates translations for all target languages using Gemini AI
// translations table will have:
// - en: "Welcome to our clinic" (original)
// - bn: "আমাদের ক্লিনিকে স্বাগতম" (AI translated)
// - fr: "Bienvenue dans notre clinique" (AI translated)
// - es: "Bienvenido a nuestra clínica" (AI translated)

// 3. Edit translation via API (with permission check)
PUT /api/translator/translations/123
{
"value": "স্বাগতম আমাদের ক্লিনিকে",
"language": "bn"
}

// 4. Get language-country info
GET /api/translator/language-to-country/bn
// Returns: {"code": "bn", "name": "Bengali", "country": "Bangladesh", "country_code": "BD"}

Now, shall I proceed with creating the package? I'll:

1. Create the package structure in translation-package/
2. Implement all migrations, models, and services
3. Build the APIs with Laravel Gates authorization
4. Add Gemini AI integration
5. Create comprehensive documentation

the translation system in this project is robust. i want to create a laravel package for translation support from this structure in @translation-package folder. the package will provide builtin translation migrations,
middlewares, apis to change/add/delete language. 2. user will configure the key structure in config than they will provide key params and data of any language with language code of the given data like en, bn, fr etc. 3. the
package will request to Gemini AI to translate that to other languages required by user in config. and save it to db. 4. the package will provide apis to edit translation with permission configuration. 5. package will
previde language to country/place conversion api.

after getting return from Gemini, the translations will be saved to database andclear cache of that key. the translation finding process will be 1. cache, if not fount 2. db if not found 3. ai. after any update or create
to database by ai/store/update the cacke of that key will be deleted immidiately. when user call the translation with helpers or api it will be set to cache if not found in cache. 
