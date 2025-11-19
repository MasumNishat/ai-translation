# TASK 06: Developer Tools & Helpers

**Priority:** P2 (High)
**Total Estimated Time:** 20-30 hours
**Dependencies:** TASK_03 (Testing)
**Status:** ⏳ Pending

---

## Overview

Create developer-friendly tools including helper functions, Blade directives, Artisan commands, and debugging utilities to improve the developer experience.

---

## Subtasks

### P2-T06-S01: Helper Functions

**Estimated Time:** 4-6 hours
**Priority:** P2
**Dependencies:** None

#### Description
Create convenient helper functions for common translation operations.

#### Implementation

**1. Create Helpers File**

```php
<?php

// src/helpers.php

use Masum\AiTranslator\Models\Translation;
use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Services\TranslationService;

if (!function_exists('ai_trans')) {
    /**
     * Translate the given key with AI fallback
     */
    function ai_trans(string $key, array $replace = [], ?string $locale = null): string
    {
        $service = app(TranslationService::class);
        $locale = $locale ?? app()->getLocale();

        return $service->get($key, $locale, $replace);
    }
}

if (!function_exists('ai_trans_choice')) {
    /**
     * Translate with pluralization
     */
    function ai_trans_choice(string $key, int $count, array $replace = [], ?string $locale = null): string
    {
        $service = app(TranslationService::class);
        $locale = $locale ?? app()->getLocale();

        return $service->choice($key, $count, $replace, $locale);
    }
}

if (!function_exists('ai_has_trans')) {
    /**
     * Check if translation exists
     */
    function ai_has_trans(string $key, ?string $locale = null): bool
    {
        $locale = $locale ?? app()->getLocale();
        $language = Language::where('code', $locale)->first();

        if (!$language) {
            return false;
        }

        return Translation::where('key', $key)
            ->where('language_id', $language->id)
            ->exists();
    }
}

if (!function_exists('ai_trans_array')) {
    /**
     * Get translations for multiple keys
     */
    function ai_trans_array(array $keys, ?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $language = Language::where('code', $locale)->first();

        if (!$language) {
            return array_fill_keys($keys, '');
        }

        $translations = Translation::where('language_id', $language->id)
            ->whereIn('key', $keys)
            ->pluck('value', 'key')
            ->toArray();

        return array_merge(array_fill_keys($keys, ''), $translations);
    }
}

if (!function_exists('ai_trans_group')) {
    /**
     * Get all translations for a group
     */
    function ai_trans_group(string $group, ?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $language = Language::where('code', $locale)->first();

        if (!$language) {
            return [];
        }

        return Translation::where('language_id', $language->id)
            ->where('group', $group)
            ->pluck('value', 'key')
            ->toArray();
    }
}

if (!function_exists('ai_languages')) {
    /**
     * Get all active languages
     */
    function ai_languages(bool $activeOnly = true): Collection
    {
        $query = Language::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }
}

if (!function_exists('ai_default_language')) {
    /**
     * Get default language
     */
    function ai_default_language(): ?Language
    {
        return Language::where('is_default', true)->first();
    }
}

if (!function_exists('ai_current_language')) {
    /**
     * Get current language based on locale
     */
    function ai_current_language(): ?Language
    {
        $locale = app()->getLocale();
        return Language::where('code', $locale)->first();
    }
}

if (!function_exists('ai_set_language')) {
    /**
     * Set application locale
     */
    function ai_set_language(string $languageCode): bool
    {
        $language = Language::where('code', $languageCode)
            ->where('is_active', true)
            ->first();

        if (!$language) {
            return false;
        }

        app()->setLocale($languageCode);
        session(['language' => $languageCode]);

        return true;
    }
}

if (!function_exists('ai_trans_missing')) {
    /**
     * Get count of missing translations for a language
     */
    function ai_trans_missing(string $languageCode): int
    {
        $service = app(\Masum\AiTranslator\Services\MissingTranslationService::class);
        return $service->findMissing($languageCode)->count();
    }
}
```

**2. Register Helpers in composer.json**

```json
{
    "autoload": {
        "files": [
            "src/helpers.php"
        ]
    }
}
```

**3. Create Facade**

```php
<?php

namespace Masum\AiTranslator\Facades;

use Illuminate\Support\Facades\Facade;

class AiTranslator extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Masum\AiTranslator\Services\TranslationService::class;
    }
}
```

#### Testing

```php
test('ai_trans helper returns translation', function () {
    $language = createLanguage(['code' => 'en']);
    createTranslation([
        'key' => 'welcome',
        'value' => 'Welcome!',
        'language_id' => $language->id,
    ]);

    app()->setLocale('en');

    expect(ai_trans('welcome'))->toBe('Welcome!');
});

test('ai_has_trans checks if translation exists', function () {
    $language = createLanguage(['code' => 'en']);
    createTranslation([
        'key' => 'test',
        'language_id' => $language->id,
    ]);

    app()->setLocale('en');

    expect(ai_has_trans('test'))->toBeTrue()
        ->and(ai_has_trans('nonexistent'))->toBeFalse();
});

test('ai_trans_group returns all translations for group', function () {
    $language = createLanguage(['code' => 'en']);
    createTranslation(['key' => 'home.title', 'value' => 'Home', 'group' => 'pages', 'language_id' => $language->id]);
    createTranslation(['key' => 'home.subtitle', 'value' => 'Welcome', 'group' => 'pages', 'language_id' => $language->id]);

    app()->setLocale('en');

    $translations = ai_trans_group('pages');

    expect($translations)->toHaveCount(2)
        ->and($translations['home.title'])->toBe('Home');
});
```

#### Acceptance Criteria
- [ ] All helper functions work correctly
- [ ] Helpers handle edge cases (missing translations, invalid locales)
- [ ] Facade available for service access
- [ ] Helpers are well-documented
- [ ] Tests achieve 90%+ coverage

---

### P2-T06-S02: Blade Directives

**Estimated Time:** 4-6 hours
**Priority:** P2
**Dependencies:** P2-T06-S01

#### Description
Create custom Blade directives for easy translation in views.

#### Implementation

**1. Register Directives in Service Provider**

```php
<?php

namespace Masum\AiTranslator\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class BladeDirectivesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // @aitrans('key', ['name' => 'John'])
        Blade::directive('aitrans', function ($expression) {
            return "<?php echo ai_trans($expression); ?>";
        });

        // @aitranschoice('key', $count)
        Blade::directive('aitranschoice', function ($expression) {
            return "<?php echo ai_trans_choice($expression); ?>";
        });

        // @language('en') ... @endlanguage
        Blade::directive('language', function ($expression) {
            return "<?php app()->setLocale($expression); ?>";
        });

        Blade::directive('endlanguage', function () {
            return "<?php app()->setLocale(config('app.locale')); ?>";
        });

        // @languages - loop through active languages
        Blade::directive('languages', function ($expression) {
            return "<?php foreach(ai_languages() as $expression): ?>";
        });

        Blade::directive('endlanguages', function () {
            return "<?php endforeach; ?>";
        });

        // @currentlang - get current language code
        Blade::directive('currentlang', function () {
            return "<?php echo app()->getLocale(); ?>";
        });

        // @defaultlang - get default language code
        Blade::directive('defaultlang', function () {
            return "<?php echo ai_default_language()?->code; ?>";
        });

        // @rtl - check if current language is RTL
        Blade::directive('rtl', function () {
            return "<?php if(ai_current_language()?->is_rtl): ?>";
        });

        Blade::directive('endrtl', function () {
            return "<?php endif; ?>";
        });

        // @ltr - check if current language is LTR
        Blade::directive('ltr', function () {
            return "<?php if(!ai_current_language()?->is_rtl): ?>";
        });

        Blade::directive('endltr', function () {
            return "<?php endif; ?>";
        });

        // @hastrans('key') ... @endhastrans
        Blade::directive('hastrans', function ($expression) {
            return "<?php if(ai_has_trans($expression)): ?>";
        });

        Blade::directive('endhastrans', function () {
            return "<?php endif; ?>";
        });

        // @transgroup('group') - output all translations in group as JSON
        Blade::directive('transgroup', function ($expression) {
            return "<?php echo json_encode(ai_trans_group($expression)); ?>";
        });

        // @missingtrans - show count of missing translations (dev mode only)
        Blade::directive('missingtrans', function ($expression) {
            return "<?php if(config('app.debug')): echo 'Missing: ' . ai_trans_missing($expression); endif; ?>";
        });
    }
}
```

**2. Example Usage in Blade**

```blade
{{-- Basic translation --}}
@aitrans('welcome.message')

{{-- Translation with parameters --}}
@aitrans('welcome.user', ['name' => $user->name])

{{-- Pluralization --}}
@aitranschoice('messages.count', $count, ['count' => $count])

{{-- Temporary language switch --}}
@language('es')
    <p>@aitrans('welcome.message')</p>
@endlanguage

{{-- Loop through languages --}}
@languages($lang)
    <a href="{{ route('set-language', $lang->code) }}">
        {{ $lang->native_name }}
    </a>
@endlanguages

{{-- RTL/LTR specific content --}}
@rtl
    <div dir="rtl" class="text-right">
        @aitrans('content')
    </div>
@endrtl

@ltr
    <div dir="ltr" class="text-left">
        @aitrans('content')
    </div>
@endltr

{{-- Conditional translation --}}
@hastrans('optional.message')
    <div class="alert">@aitrans('optional.message')</div>
@endhastrans

{{-- Output group as JSON for JavaScript --}}
<script>
    const translations = @transgroup('validation');
</script>

{{-- Development helper --}}
@missingtrans(app()->getLocale())
```

#### Testing

```php
test('aitrans blade directive works', function () {
    $language = createLanguage(['code' => 'en']);
    createTranslation(['key' => 'test', 'value' => 'Test Value', 'language_id' => $language->id]);

    app()->setLocale('en');

    $blade = "@aitrans('test')";
    $compiled = Blade::compileString($blade);

    ob_start();
    eval("?>$compiled");
    $output = ob_get_clean();

    expect($output)->toBe('Test Value');
});
```

#### Acceptance Criteria
- [ ] All Blade directives work correctly
- [ ] Directives handle missing translations gracefully
- [ ] RTL/LTR directives work properly
- [ ] Language loop directive works
- [ ] Development helpers only show in debug mode
- [ ] Tests achieve 85%+ coverage

---

### P2-T06-S03: Artisan Commands

**Estimated Time:** 6-8 hours
**Priority:** P2
**Dependencies:** None

#### Description
Create Artisan commands for common translation tasks.

#### Implementation

**1. Sync Translations Command**

```php
<?php

namespace Masum\AiTranslator\Console\Commands;

use Illuminate\Console\Command;
use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Services\MissingTranslationService;

class SyncTranslationsCommand extends Command
{
    protected $signature = 'translator:sync
                          {language? : The language code to sync}
                          {--auto-translate : Automatically translate missing entries}
                          {--group= : Only sync specific group}';

    protected $description = 'Sync missing translations from default language';

    public function handle(MissingTranslationService $service): int
    {
        $languageCode = $this->argument('language');
        $group = $this->option('group');

        if ($languageCode) {
            $languages = [Language::where('code', $languageCode)->firstOrFail()];
        } else {
            $languages = Language::where('is_active', true)
                ->where('is_default', false)
                ->get();
        }

        foreach ($languages as $language) {
            $this->info("Syncing translations for {$language->name} ({$language->code})...");

            $missing = $service->findMissing($language->code, $group);

            if ($missing->isEmpty()) {
                $this->info("  ✓ No missing translations");
                continue;
            }

            $this->warn("  ⚠ Found {$missing->count()} missing translations");

            if ($this->option('auto-translate')) {
                $this->info("  🤖 Auto-translating...");

                $stats = $service->autoFillMissing($language->code, $group);

                $this->info("  ✓ Created {$stats['success']} translations");

                if ($stats['failed'] > 0) {
                    $this->error("  ✗ Failed: {$stats['failed']}");
                }
            } else {
                $this->table(
                    ['Key', 'Source Value', 'Group'],
                    $missing->take(10)->map(fn($m) => [
                        $m['key'],
                        \Str::limit($m['source_value'], 50),
                        $m['group'],
                    ])->toArray()
                );

                if ($missing->count() > 10) {
                    $this->info("  ... and " . ($missing->count() - 10) . " more");
                }
            }
        }

        return Command::SUCCESS;
    }
}
```

**2. Export Translations Command**

```php
<?php

namespace Masum\AiTranslator\Console\Commands;

use Illuminate\Console\Command;
use Masum\AiTranslator\Services\JsonImportExportService;

class ExportTranslationsCommand extends Command
{
    protected $signature = 'translator:export
                          {language : The language code to export}
                          {--format=json : Export format (json, csv, yaml, po)}
                          {--group= : Only export specific group}
                          {--output= : Output file path}';

    protected $description = 'Export translations to file';

    public function handle(): int
    {
        $languageCode = $this->argument('language');
        $format = $this->option('format');
        $group = $this->option('group');
        $output = $this->option('output') ?? storage_path("translations/{$languageCode}.{$format}");

        $this->info("Exporting {$languageCode} translations to {$format}...");

        $service = match($format) {
            'json' => app(JsonImportExportService::class),
            'csv' => app(CsvImportExportService::class),
            'yaml' => app(YamlImportExportService::class),
            'po' => app(PoImportExportService::class),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
        };

        $data = $service->export($languageCode, $group);

        if ($format === 'json') {
            $data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        // Ensure directory exists
        $directory = dirname($output);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($output, $data);

        $this->info("✓ Exported to: {$output}");

        return Command::SUCCESS;
    }
}
```

**3. Import Translations Command**

```php
<?php

namespace Masum\AiTranslator\Console\Commands;

use Illuminate\Console\Command;

class ImportTranslationsCommand extends Command
{
    protected $signature = 'translator:import
                          {file : The file to import}
                          {--format=json : Import format (json, csv, yaml, po)}
                          {--overwrite : Overwrite existing translations}';

    protected $description = 'Import translations from file';

    public function handle(): int
    {
        $file = $this->argument('file');
        $format = $this->option('format');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return Command::FAILURE;
        }

        $this->info("Importing translations from {$file}...");

        $service = match($format) {
            'json' => app(JsonImportExportService::class),
            'csv' => app(CsvImportExportService::class),
            'yaml' => app(YamlImportExportService::class),
            'po' => app(PoImportExportService::class),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
        };

        if ($format === 'json' || $format === 'yaml') {
            $contents = file_get_contents($file);
            $data = $format === 'json' ? json_decode($contents, true) : \Symfony\Component\Yaml\Yaml::parse($contents);
            $stats = $service->import($data, ['overwrite' => $this->option('overwrite')]);
        } else {
            $stats = $service->import($file, ['overwrite' => $this->option('overwrite')]);
        }

        $this->info("✓ Created: {$stats['created']}");
        $this->info("✓ Updated: {$stats['updated']}");
        $this->warn("⊝ Skipped: {$stats['skipped']}");

        if (!empty($stats['errors'])) {
            $this->error("✗ Errors: " . count($stats['errors']));
            foreach ($stats['errors'] as $error) {
                $this->line("  - {$error['key']}: {$error['error']}");
            }
        }

        return Command::SUCCESS;
    }
}
```

**4. Clear Cache Command**

```php
<?php

namespace Masum\AiTranslator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearTranslationCacheCommand extends Command
{
    protected $signature = 'translator:clear-cache
                          {--language= : Clear cache for specific language}
                          {--group= : Clear cache for specific group}';

    protected $description = 'Clear translation cache';

    public function handle(): int
    {
        $language = $this->option('language');
        $group = $this->option('group');

        if ($language || $group) {
            $pattern = 'ai_translator';

            if ($group) {
                $pattern .= ".{$group}";
            }

            if ($language) {
                $pattern .= ".*{$language}";
            }

            // This is simplified - actual implementation would use cache tags or scan keys
            Cache::flush();
            $this->info("✓ Cleared cache for pattern: {$pattern}");
        } else {
            Cache::flush();
            $this->info("✓ Cleared all translation cache");
        }

        return Command::SUCCESS;
    }
}
```

**5. Statistics Command**

```php
<?php

namespace Masum\AiTranslator\Console\Commands;

use Illuminate\Console\Command;
use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;

class TranslationStatsCommand extends Command
{
    protected $signature = 'translator:stats';

    protected $description = 'Show translation statistics';

    public function handle(): int
    {
        $languages = Language::withCount('translations')->get();
        $totalTranslations = Translation::count();
        $totalGroups = Translation::distinct('group')->count('group');

        $this->info("📊 Translation Statistics\n");

        $this->table(
            ['Language', 'Code', 'Status', 'Translations', 'Completion %'],
            $languages->map(function ($lang) use ($totalTranslations) {
                $percentage = $totalTranslations > 0
                    ? round(($lang->translations_count / $totalTranslations) * 100, 1)
                    : 0;

                return [
                    $lang->name,
                    $lang->code,
                    $lang->is_active ? '✓ Active' : '✗ Inactive',
                    $lang->translations_count,
                    $percentage . '%',
                ];
            })->toArray()
        );

        $this->newLine();
        $this->info("Total Groups: {$totalGroups}");
        $this->info("Total Translations: {$totalTranslations}");

        return Command::SUCCESS;
    }
}
```

#### Testing

```php
test('sync command finds missing translations', function () {
    $defaultLang = createLanguage(['code' => 'en', 'is_default' => true]);
    $targetLang = createLanguage(['code' => 'es']);

    createTranslation(['key' => 'test', 'language_id' => $defaultLang->id]);

    $this->artisan('translator:sync', ['language' => 'es'])
        ->expectsOutput('Syncing translations for Spanish (es)...')
        ->assertExitCode(0);
});
```

#### Acceptance Criteria
- [ ] All commands work correctly
- [ ] Commands have proper progress indicators
- [ ] Commands handle errors gracefully
- [ ] Commands have helpful output
- [ ] Tests achieve 85%+ coverage

---

### P2-T06-S04: Debug Toolbar Integration

**Estimated Time:** 4-6 hours
**Priority:** P3
**Dependencies:** None

#### Description
Integration with Laravel Debugbar to show translation queries and cache hits.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\DataCollectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

class TranslationDataCollector extends DataCollector implements Renderable
{
    protected array $translations = [];
    protected array $queries = [];
    protected int $cacheHits = 0;
    protected int $cacheMisses = 0;

    public function collect(): array
    {
        return [
            'count' => count($this->translations),
            'translations' => $this->translations,
            'queries' => $this->queries,
            'cache_hits' => $this->cacheHits,
            'cache_misses' => $this->cacheMisses,
            'cache_hit_rate' => $this->getCacheHitRate(),
        ];
    }

    public function getName(): string
    {
        return 'translations';
    }

    public function getWidgets(): array
    {
        return [
            'translations' => [
                'icon' => 'language',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'translations.translations',
                'default' => '[]',
            ],
            'translations:badge' => [
                'map' => 'translations.count',
                'default' => 0,
            ],
        ];
    }

    protected function getCacheHitRate(): string
    {
        $total = $this->cacheHits + $this->cacheMisses;
        if ($total === 0) {
            return '0%';
        }

        return round(($this->cacheHits / $total) * 100, 1) . '%';
    }
}
```

#### Acceptance Criteria
- [ ] Shows all translation lookups
- [ ] Displays cache hit/miss statistics
- [ ] Shows database queries for translations
- [ ] Integration with Laravel Debugbar

---

### P2-T06-S05: IDE Helper Generation

**Estimated Time:** 2-4 hours
**Priority:** P3
**Dependencies:** P2-T06-S01

#### Description
Generate IDE helper files for autocomplete support.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\Console\Commands;

use Illuminate\Console\Command;

class GenerateIdeHelperCommand extends Command
{
    protected $signature = 'translator:ide-helper';

    protected $description = 'Generate IDE helper file for translation keys';

    public function handle(): int
    {
        $translations = Translation::pluck('key')->unique();

        $helper = "<?php\n\nnamespace {\n";
        $helper .= "    /**\n";
        $helper .= "     * AI Translator IDE Helper\n";
        $helper .= "     * @generated\n";
        $helper .= "     */\n";
        $helper .= "    class TranslationKeys {\n";

        foreach ($translations as $key) {
            $constant = strtoupper(str_replace(['.', '-'], '_', $key));
            $helper .= "        const {$constant} = '{$key}';\n";
        }

        $helper .= "    }\n}\n";

        file_put_contents(base_path('_ide_helper_translations.php'), $helper);

        $this->info('✓ IDE helper generated');

        return Command::SUCCESS;
    }
}
```

#### Acceptance Criteria
- [ ] Generates valid PHP helper file
- [ ] All translation keys included
- [ ] File compatible with IDE autocomplete
- [ ] Command runs without errors

---

## Definition of Done

- [ ] All 5 subtasks completed
- [ ] All acceptance criteria met
- [ ] Unit tests written and passing
- [ ] Documentation complete
- [ ] Helper examples in README
- [ ] Code reviewed

---

## Notes

- Consider VS Code extension for translation management
- Add PHPStorm plugin metadata
- Consider translation key validation in CI/CD
