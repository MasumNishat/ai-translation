# TASK 05: Advanced Translation Features

**Priority:** P2 (High)
**Total Estimated Time:** 30-40 hours
**Dependencies:** TASK_03 (Testing)
**Status:** ⏳ Pending

---

## Overview

Enhance translation functionality with advanced features including validation, search capabilities, missing translation detection, pluralization support, and translation suggestions.

---

## Subtasks

### P2-T05-S01: Translation Validation System

**Estimated Time:** 6-8 hours
**Priority:** P2
**Dependencies:** None

#### Description
Implement comprehensive validation for translation values including HTML tag matching, placeholder validation, length constraints, and format verification.

#### Implementation

**1. Create Validation Service**

```php
<?php

namespace Masum\AiTranslator\Services;

use Illuminate\Support\Str;

class TranslationValidationService
{
    protected array $validationRules = [];

    /**
     * Validate translation value
     */
    public function validate(string $sourceValue, string $translatedValue, array $options = []): array
    {
        $errors = [];

        // HTML tag validation
        if ($options['check_html'] ?? true) {
            $htmlErrors = $this->validateHtmlTags($sourceValue, $translatedValue);
            $errors = array_merge($errors, $htmlErrors);
        }

        // Placeholder validation
        if ($options['check_placeholders'] ?? true) {
            $placeholderErrors = $this->validatePlaceholders($sourceValue, $translatedValue);
            $errors = array_merge($errors, $placeholderErrors);
        }

        // Length validation
        if (isset($options['max_length'])) {
            $lengthErrors = $this->validateLength($translatedValue, $options['max_length']);
            $errors = array_merge($errors, $lengthErrors);
        }

        // Format validation
        if ($options['check_format'] ?? true) {
            $formatErrors = $this->validateFormat($sourceValue, $translatedValue);
            $errors = array_merge($errors, $formatErrors);
        }

        // Custom rules
        if (isset($options['custom_rules'])) {
            foreach ($options['custom_rules'] as $rule) {
                $customErrors = $this->applyCustomRule($translatedValue, $rule);
                $errors = array_merge($errors, $customErrors);
            }
        }

        return $errors;
    }

    /**
     * Validate HTML tags match between source and translation
     */
    protected function validateHtmlTags(string $source, string $translation): array
    {
        $errors = [];

        // Extract HTML tags
        preg_match_all('/<\/?([a-z][a-z0-9]*)\b[^>]*>/i', $source, $sourceTags);
        preg_match_all('/<\/?([a-z][a-z0-9]*)\b[^>]*>/i', $translation, $translationTags);

        // Sort for comparison
        sort($sourceTags[1]);
        sort($translationTags[1]);

        if ($sourceTags[1] !== $translationTags[1]) {
            $errors[] = [
                'type' => 'html_mismatch',
                'message' => 'HTML tags do not match between source and translation',
                'expected' => $sourceTags[1],
                'actual' => $translationTags[1],
            ];
        }

        return $errors;
    }

    /**
     * Validate placeholders (e.g., :name, {count}, %s)
     */
    protected function validatePlaceholders(string $source, string $translation): array
    {
        $errors = [];

        // Laravel-style placeholders: :name, :attribute
        $sourcePlaceholders = $this->extractPlaceholders($source, '/:[a-z_]+/i');
        $translationPlaceholders = $this->extractPlaceholders($translation, '/:[a-z_]+/i');

        if (array_diff($sourcePlaceholders, $translationPlaceholders)) {
            $errors[] = [
                'type' => 'placeholder_mismatch',
                'message' => 'Placeholders do not match between source and translation',
                'expected' => $sourcePlaceholders,
                'actual' => $translationPlaceholders,
                'missing' => array_diff($sourcePlaceholders, $translationPlaceholders),
            ];
        }

        // ICU-style placeholders: {count}, {name}
        $sourcePlaceholders = $this->extractPlaceholders($source, '/\{[a-z_]+\}/i');
        $translationPlaceholders = $this->extractPlaceholders($translation, '/\{[a-z_]+\}/i');

        if (array_diff($sourcePlaceholders, $translationPlaceholders)) {
            $errors[] = [
                'type' => 'icu_placeholder_mismatch',
                'message' => 'ICU placeholders do not match',
                'expected' => $sourcePlaceholders,
                'actual' => $translationPlaceholders,
            ];
        }

        // sprintf-style placeholders: %s, %d, %1$s
        $sourcePlaceholders = $this->extractPlaceholders($source, '/%(?:\d+\$)?[sdfu]/');
        $translationPlaceholders = $this->extractPlaceholders($translation, '/%(?:\d+\$)?[sdfu]/');

        if (count($sourcePlaceholders) !== count($translationPlaceholders)) {
            $errors[] = [
                'type' => 'sprintf_placeholder_mismatch',
                'message' => 'sprintf placeholders count mismatch',
                'expected_count' => count($sourcePlaceholders),
                'actual_count' => count($translationPlaceholders),
            ];
        }

        return $errors;
    }

    /**
     * Extract placeholders using regex pattern
     */
    protected function extractPlaceholders(string $text, string $pattern): array
    {
        preg_match_all($pattern, $text, $matches);
        return $matches[0] ?? [];
    }

    /**
     * Validate translation length
     */
    protected function validateLength(string $translation, int $maxLength): array
    {
        $errors = [];

        if (mb_strlen($translation) > $maxLength) {
            $errors[] = [
                'type' => 'length_exceeded',
                'message' => "Translation exceeds maximum length of {$maxLength} characters",
                'length' => mb_strlen($translation),
                'max_length' => $maxLength,
            ];
        }

        return $errors;
    }

    /**
     * Validate format consistency (e.g., capitalization, punctuation)
     */
    protected function validateFormat(string $source, string $translation): array
    {
        $errors = [];

        // Check if source ends with punctuation
        $sourcePunctuation = preg_match('/[.!?]$/', trim($source));
        $translationPunctuation = preg_match('/[.!?]$/', trim($translation));

        if ($sourcePunctuation && !$translationPunctuation) {
            $errors[] = [
                'type' => 'punctuation_mismatch',
                'message' => 'Source ends with punctuation but translation does not',
                'suggestion' => 'Add ending punctuation to translation',
            ];
        }

        // Check capitalization
        $sourceCapitalized = preg_match('/^[A-Z]/', $source);
        $translationCapitalized = preg_match('/^[A-Z]/', $translation);

        if ($sourceCapitalized && !$translationCapitalized) {
            $errors[] = [
                'type' => 'capitalization_mismatch',
                'message' => 'Source starts with capital letter but translation does not',
                'suggestion' => 'Capitalize first letter of translation',
            ];
        }

        return $errors;
    }

    /**
     * Apply custom validation rule
     */
    protected function applyCustomRule(string $translation, callable $rule): array
    {
        try {
            $result = $rule($translation);
            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            return [[
                'type' => 'custom_rule_error',
                'message' => $e->getMessage(),
            ]];
        }
    }
}
```

**2. Add Validation to Translation Model**

```php
// Add to Translation model

use Masum\AiTranslator\Services\TranslationValidationService;

/**
 * Validate translation value
 */
public function validateTranslation(string $sourceValue, array $options = []): array
{
    $validator = app(TranslationValidationService::class);
    return $validator->validate($sourceValue, $this->value, $options);
}

/**
 * Check if translation is valid
 */
public function isValid(string $sourceValue, array $options = []): bool
{
    $errors = $this->validateTranslation($sourceValue, $options);
    return empty($errors);
}
```

**3. Add API Endpoint**

```php
// Add to TranslationController

public function validate(Request $request, int $id): JsonResponse
{
    $translation = Translation::findOrFail($id);

    $validated = $request->validate([
        'source_value' => 'required|string',
        'check_html' => 'boolean',
        'check_placeholders' => 'boolean',
        'check_format' => 'boolean',
        'max_length' => 'nullable|integer|min:1',
    ]);

    $errors = $translation->validateTranslation(
        $validated['source_value'],
        $validated
    );

    return response()->json([
        'success' => true,
        'data' => [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'translation' => [
                'key' => $translation->key,
                'value' => $translation->value,
                'language' => $translation->language->code,
            ],
        ],
    ]);
}
```

#### Testing

```php
test('validates HTML tags in translations', function () {
    $validator = app(TranslationValidationService::class);

    $source = '<strong>Hello</strong> world';
    $translation = '<strong>Hola</strong> mundo';

    $errors = $validator->validate($source, $translation);

    expect($errors)->toBeEmpty();
});

test('detects missing HTML tags', function () {
    $validator = app(TranslationValidationService::class);

    $source = '<strong>Hello</strong> world';
    $translation = 'Hola mundo'; // Missing <strong> tag

    $errors = $validator->validate($source, $translation);

    expect($errors)->toHaveCount(1)
        ->and($errors[0]['type'])->toBe('html_mismatch');
});

test('validates placeholders in translations', function () {
    $validator = app(TranslationValidationService::class);

    $source = 'Hello :name, you have {count} messages';
    $translation = 'Hola :name, tienes {count} mensajes';

    $errors = $validator->validate($source, $translation);

    expect($errors)->toBeEmpty();
});

test('detects missing placeholders', function () {
    $validator = app(TranslationValidationService::class);

    $source = 'Hello :name';
    $translation = 'Hola'; // Missing :name

    $errors = $validator->validate($source, $translation);

    expect($errors)->not->toBeEmpty();
});
```

#### Acceptance Criteria
- [ ] Validates HTML tags match between source and translation
- [ ] Validates all placeholder types (:name, {count}, %s)
- [ ] Validates translation length constraints
- [ ] Validates format consistency (punctuation, capitalization)
- [ ] Supports custom validation rules
- [ ] API endpoint returns detailed validation errors
- [ ] Tests achieve 90%+ coverage

---

### P2-T05-S02: Advanced Search and Filtering

**Estimated Time:** 8-10 hours
**Priority:** P2
**Dependencies:** None

#### Description
Implement full-text search, fuzzy matching, and advanced filtering capabilities for translations.

#### Implementation

**1. Add Search Scope to Translation Model**

```php
// Add to Translation model

/**
 * Search translations by text
 */
public function scopeSearch($query, string $search)
{
    return $query->where(function ($q) use ($search) {
        $q->where('key', 'like', "%{$search}%")
          ->orWhere('value', 'like', "%{$search}%");
    });
}

/**
 * Filter by status
 */
public function scopeByStatus($query, string $status)
{
    return match($status) {
        'missing' => $query->whereNull('value')->orWhere('value', ''),
        'translated' => $query->whereNotNull('value')->where('value', '!=', ''),
        'needs_review' => $query->whereHas('meta', function ($q) {
            $q->where('needs_review', true);
        }),
        default => $query,
    };
}

/**
 * Filter by date range
 */
public function scopeDateRange($query, ?string $from, ?string $to)
{
    if ($from) {
        $query->where('created_at', '>=', $from);
    }

    if ($to) {
        $query->where('created_at', '<=', $to);
    }

    return $query;
}

/**
 * Filter by character length
 */
public function scopeLengthBetween($query, ?int $min, ?int $max)
{
    if ($min !== null) {
        $query->whereRaw('LENGTH(value) >= ?', [$min]);
    }

    if ($max !== null) {
        $query->whereRaw('LENGTH(value) <= ?', [$max]);
    }

    return $query;
}
```

**2. Enhanced Search Endpoint**

```php
public function search(Request $request): JsonResponse
{
    $validated = $request->validate([
        'q' => 'nullable|string',
        'language' => 'nullable|string|exists:languages,code',
        'group' => 'nullable|string',
        'status' => 'nullable|in:missing,translated,needs_review',
        'date_from' => 'nullable|date',
        'date_to' => 'nullable|date',
        'min_length' => 'nullable|integer|min:0',
        'max_length' => 'nullable|integer|min:1',
        'sort_by' => 'nullable|in:key,value,created_at,updated_at',
        'sort_dir' => 'nullable|in:asc,desc',
        'per_page' => 'nullable|integer|min:1|max:100',
    ]);

    $query = Translation::with(['language']);

    // Apply filters
    if (!empty($validated['q'])) {
        $query->search($validated['q']);
    }

    if (!empty($validated['language'])) {
        $query->whereHas('language', function ($q) use ($validated) {
            $q->where('code', $validated['language']);
        });
    }

    if (!empty($validated['group'])) {
        $query->where('group', $validated['group']);
    }

    if (!empty($validated['status'])) {
        $query->byStatus($validated['status']);
    }

    $query->dateRange($validated['date_from'] ?? null, $validated['date_to'] ?? null);
    $query->lengthBetween($validated['min_length'] ?? null, $validated['max_length'] ?? null);

    // Sorting
    $sortBy = $validated['sort_by'] ?? 'created_at';
    $sortDir = $validated['sort_dir'] ?? 'desc';
    $query->orderBy($sortBy, $sortDir);

    // Pagination
    $perPage = $validated['per_page'] ?? 20;
    $translations = $query->paginate($perPage);

    return response()->json([
        'success' => true,
        'data' => TranslationResource::collection($translations),
        'meta' => [
            'current_page' => $translations->currentPage(),
            'total' => $translations->total(),
            'per_page' => $translations->perPage(),
            'last_page' => $translations->lastPage(),
        ],
    ]);
}
```

**3. Add Full-Text Search (MySQL)**

```php
// Create migration for full-text index

public function up()
{
    DB::statement('ALTER TABLE translations ADD FULLTEXT fulltext_index (key, value)');
}

// Update search scope
public function scopeFullTextSearch($query, string $search)
{
    return $query->whereRaw(
        "MATCH(key, value) AGAINST(? IN BOOLEAN MODE)",
        [$search]
    )->orderByRaw(
        "MATCH(key, value) AGAINST(?) DESC",
        [$search]
    );
}
```

#### Acceptance Criteria
- [ ] Can search translations by key or value
- [ ] Can filter by language, group, status
- [ ] Can filter by date range
- [ ] Can filter by character length
- [ ] Supports sorting by multiple fields
- [ ] Returns paginated results
- [ ] Full-text search available for MySQL
- [ ] Search is performant with 10k+ translations

---

### P2-T05-S03: Missing Translation Detection

**Estimated Time:** 6-8 hours
**Priority:** P2
**Dependencies:** None

#### Description
Implement system to detect and report missing translations across languages.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\Services;

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Illuminate\Support\Collection;

class MissingTranslationService
{
    /**
     * Find missing translations for a language
     */
    public function findMissing(string $languageCode, ?string $group = null): Collection
    {
        $language = Language::where('code', $languageCode)->firstOrFail();
        $defaultLanguage = Language::where('is_default', true)->firstOrFail();

        // Get all keys from default language
        $defaultKeysQuery = Translation::where('language_id', $defaultLanguage->id);

        if ($group) {
            $defaultKeysQuery->where('group', $group);
        }

        $defaultKeys = $defaultKeysQuery->pluck('key', 'id');

        // Get existing translations for target language
        $existingKeysQuery = Translation::where('language_id', $language->id);

        if ($group) {
            $existingKeysQuery->where('group', $group);
        }

        $existingKeys = $existingKeysQuery->pluck('key');

        // Find missing keys
        $missingKeys = $defaultKeys->diff($existingKeys);

        // Get full translation data for missing keys
        $missingTranslations = Translation::whereIn('id', $missingKeys->keys())
            ->with('language')
            ->get()
            ->map(function ($translation) use ($languageCode) {
                return [
                    'key' => $translation->key,
                    'source_value' => $translation->value,
                    'source_language' => $translation->language->code,
                    'target_language' => $languageCode,
                    'group' => $translation->group,
                ];
            });

        return $missingTranslations;
    }

    /**
     * Generate missing translation report for all languages
     */
    public function generateReport(?string $group = null): array
    {
        $languages = Language::where('is_active', true)->get();
        $defaultLanguage = Language::where('is_default', true)->first();

        $report = [
            'generated_at' => now()->toIso8601String(),
            'default_language' => $defaultLanguage->code,
            'languages' => [],
            'summary' => [],
        ];

        foreach ($languages as $language) {
            if ($language->is_default) {
                continue;
            }

            $missing = $this->findMissing($language->code, $group);

            $total = Translation::where('language_id', $language->id);
            if ($group) {
                $total->where('group', $group);
            }
            $totalCount = $total->count();

            $defaultTotal = Translation::where('language_id', $defaultLanguage->id);
            if ($group) {
                $defaultTotal->where('group', $group);
            }
            $defaultTotalCount = $defaultTotal->count();

            $completionPercentage = $defaultTotalCount > 0
                ? round(($totalCount / $defaultTotalCount) * 100, 2)
                : 0;

            $report['languages'][$language->code] = [
                'language' => [
                    'code' => $language->code,
                    'name' => $language->name,
                ],
                'total_translations' => $totalCount,
                'missing_count' => $missing->count(),
                'completion_percentage' => $completionPercentage,
                'missing_translations' => $missing->toArray(),
            ];

            $report['summary'][] = [
                'language' => $language->code,
                'missing' => $missing->count(),
                'completion' => $completionPercentage,
            ];
        }

        return $report;
    }

    /**
     * Auto-generate missing translations using AI
     */
    public function autoFillMissing(string $languageCode, ?string $group = null): array
    {
        $missing = $this->findMissing($languageCode, $group);
        $language = Language::where('code', $languageCode)->firstOrFail();

        $stats = [
            'total' => $missing->count(),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($missing as $item) {
            try {
                Translation::create([
                    'key' => $item['key'],
                    'value' => '', // Will be filled by AI translation job
                    'language_id' => $language->id,
                    'group' => $item['group'],
                ]);

                // Dispatch AI translation job
                \Masum\AiTranslator\Jobs\TranslateJob::dispatch(
                    $item['key'],
                    $item['source_value'],
                    $item['source_language'],
                    [$languageCode]
                );

                $stats['success']++;
            } catch (\Exception $e) {
                $stats['failed']++;
                $stats['errors'][] = [
                    'key' => $item['key'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $stats;
    }
}
```

**API Endpoints**

```php
// Add to routes/api.php
Route::get('missing-translations/{languageCode}', [TranslationController::class, 'getMissing']);
Route::get('missing-translations/report', [TranslationController::class, 'getMissingReport']);
Route::post('missing-translations/{languageCode}/auto-fill', [TranslationController::class, 'autoFillMissing']);
```

#### Testing

```php
test('finds missing translations for a language', function () {
    $defaultLang = createLanguage(['code' => 'en', 'is_default' => true]);
    $targetLang = createLanguage(['code' => 'es']);

    createTranslation(['key' => 'home.title', 'language_id' => $defaultLang->id]);
    createTranslation(['key' => 'home.subtitle', 'language_id' => $defaultLang->id]);
    createTranslation(['key' => 'home.title', 'language_id' => $targetLang->id]);

    $service = app(MissingTranslationService::class);
    $missing = $service->findMissing('es');

    expect($missing)->toHaveCount(1)
        ->and($missing->first()['key'])->toBe('home.subtitle');
});
```

#### Acceptance Criteria
- [ ] Can detect missing translations for a language
- [ ] Can generate comprehensive report for all languages
- [ ] Report includes completion percentage
- [ ] Can auto-fill missing translations with AI
- [ ] API endpoints return proper data structure
- [ ] Tests achieve 85%+ coverage

---

### P2-T05-S04: Pluralization Support

**Estimated Time:** 6-8 hours
**Priority:** P3
**Dependencies:** None

#### Description
Add support for plural forms based on language-specific rules.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\Services;

class PluralizationService
{
    protected array $pluralRules = [
        'en' => fn($n) => $n == 1 ? 0 : 1, // one, other
        'ru' => fn($n) => $n % 10 == 1 && $n % 100 != 11 ? 0 : ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2), // one, few, many
        'ar' => fn($n) => $n == 0 ? 0 : ($n == 1 ? 1 : ($n == 2 ? 2 : ($n % 100 >= 3 && $n % 100 <= 10 ? 3 : ($n % 100 >= 11 ? 4 : 5)))), // zero, one, two, few, many, other
    ];

    /**
     * Get plural form for count
     */
    public function getPlural(string $languageCode, int $count, array $forms): string
    {
        $rule = $this->pluralRules[$languageCode] ?? $this->pluralRules['en'];
        $index = $rule($count);

        return $forms[$index] ?? end($forms);
    }
}
```

#### Acceptance Criteria
- [ ] Supports plural forms for major languages
- [ ] Handles complex plural rules (e.g., Arabic, Russian)
- [ ] API for retrieving plural forms
- [ ] Tests for all supported languages

---

### P2-T05-S05: Translation Suggestions

**Estimated Time:** 4-6 hours
**Priority:** P3
**Dependencies:** P2-T05-S02

#### Description
Provide translation suggestions based on similar existing translations and AI.

#### Implementation

```php
public function getSuggestions(string $key, string $languageCode): array
{
    // Find similar keys
    $similar = Translation::where('language_code', $languageCode)
        ->where('key', 'like', "%{$key}%")
        ->limit(5)
        ->get();

    // Get AI suggestion if enabled
    $aiSuggestion = null;
    if (config('ai-translator.features.suggestions')) {
        $aiSuggestion = $this->aiService->getSuggestion($key, $languageCode);
    }

    return [
        'similar' => $similar,
        'ai_suggestion' => $aiSuggestion,
    ];
}
```

#### Acceptance Criteria
- [ ] Returns similar translations by key
- [ ] Provides AI-powered suggestions
- [ ] Ranks suggestions by relevance
- [ ] Fast response time (< 200ms)

---

## Definition of Done

- [ ] All 5 subtasks completed
- [ ] All acceptance criteria met
- [ ] Unit tests written and passing (85%+ coverage)
- [ ] Feature tests written and passing
- [ ] API documentation updated
- [ ] README updated with examples
- [ ] Performance tested
- [ ] Code reviewed and approved

---

## Notes

- Consider adding Translation Memory for reusing previous translations
- Add support for context-aware translations
- Consider machine learning for translation quality scoring
- Add support for terminology management
