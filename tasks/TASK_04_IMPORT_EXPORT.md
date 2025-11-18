# TASK 04: Import/Export System

**Priority:** P2 (High)
**Total Estimated Time:** 25-35 hours
**Dependencies:** TASK_03 (Testing infrastructure)
**Status:** ⏳ Pending

---

## Overview

Implement a comprehensive import/export system for translations, supporting multiple formats (JSON, CSV, YAML, PO files). This enables easy migration between systems, backup/restore, and integration with external translation services.

---

## Subtasks

### P2-T04-S01: JSON Import/Export

**Estimated Time:** 6-8 hours
**Priority:** P2
**Dependencies:** None

#### Description
Implement JSON format import/export with support for nested structures, metadata, and validation.

#### Implementation

**1. Create Import/Export Service**

```php
<?php

namespace Masum\AiTranslator\Services;

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class JsonImportExportService
{
    /**
     * Export translations to JSON format
     */
    public function export(string $languageCode, ?string $group = null): array
    {
        $language = Language::where('code', $languageCode)->firstOrFail();

        $query = Translation::where('language_id', $language->id);

        if ($group) {
            $query->where('group', $group);
        }

        $translations = $query->get();

        return [
            'meta' => [
                'version' => '1.0',
                'language' => [
                    'code' => $language->code,
                    'name' => $language->name,
                    'native_name' => $language->native_name,
                    'direction' => $language->direction,
                ],
                'exported_at' => now()->toIso8601String(),
                'total_translations' => $translations->count(),
                'groups' => $translations->pluck('group')->unique()->values(),
            ],
            'translations' => $this->formatTranslationsForExport($translations),
        ];
    }

    /**
     * Format translations for export (nested structure)
     */
    protected function formatTranslationsForExport(Collection $translations): array
    {
        $formatted = [];

        foreach ($translations as $translation) {
            $keys = explode('.', $translation->key);
            $current = &$formatted;

            foreach ($keys as $index => $key) {
                if ($index === count($keys) - 1) {
                    $current[$key] = [
                        'value' => $translation->value,
                        'group' => $translation->group,
                        'created_at' => $translation->created_at->toIso8601String(),
                        'updated_at' => $translation->updated_at->toIso8601String(),
                    ];
                } else {
                    if (!isset($current[$key])) {
                        $current[$key] = [];
                    }
                    $current = &$current[$key];
                }
            }
        }

        return $formatted;
    }

    /**
     * Import translations from JSON
     */
    public function import(array $data, array $options = []): array
    {
        $this->validateImportData($data);

        $languageCode = $data['meta']['language']['code'];
        $language = Language::firstOrCreate(
            ['code' => $languageCode],
            [
                'name' => $data['meta']['language']['name'],
                'native_name' => $data['meta']['language']['native_name'],
                'direction' => $data['meta']['language']['direction'],
            ]
        );

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $flattenedTranslations = $this->flattenTranslations($data['translations']);

        foreach ($flattenedTranslations as $key => $item) {
            try {
                $translation = Translation::where('key', $key)
                    ->where('language_id', $language->id)
                    ->first();

                if ($translation) {
                    if ($options['overwrite'] ?? true) {
                        $translation->update(['value' => $item['value']]);
                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } else {
                    Translation::create([
                        'key' => $key,
                        'value' => $item['value'],
                        'language_id' => $language->id,
                        'group' => $item['group'] ?? 'general',
                    ]);
                    $stats['created']++;
                }
            } catch (\Exception $e) {
                $stats['errors'][] = [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $stats;
    }

    /**
     * Flatten nested translations
     */
    protected function flattenTranslations(array $translations, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($translations as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value) && isset($value['value'])) {
                // Leaf node with translation data
                $flattened[$newKey] = $value;
            } elseif (is_array($value)) {
                // Nested structure
                $flattened = array_merge(
                    $flattened,
                    $this->flattenTranslations($value, $newKey)
                );
            }
        }

        return $flattened;
    }

    /**
     * Validate import data structure
     */
    protected function validateImportData(array $data): void
    {
        $validator = Validator::make($data, [
            'meta' => 'required|array',
            'meta.version' => 'required|string',
            'meta.language' => 'required|array',
            'meta.language.code' => 'required|string',
            'meta.language.name' => 'required|string',
            'translations' => 'required|array',
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException(
                'Invalid import data: ' . $validator->errors()->first()
            );
        }
    }
}
```

**2. Add API Endpoints**

```php
// routes/api.php
Route::group(['prefix' => 'import-export'], function () {
    Route::get('export/{languageCode}', [ImportExportController::class, 'exportJson']);
    Route::get('export/{languageCode}/{group}', [ImportExportController::class, 'exportJsonByGroup']);
    Route::post('import', [ImportExportController::class, 'importJson']);
});
```

**3. Create Controller**

```php
<?php

namespace Masum\AiTranslator\Http\Controllers;

use Masum\AiTranslator\Services\JsonImportExportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ImportExportController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected JsonImportExportService $importExportService
    ) {}

    public function exportJson(string $languageCode): JsonResponse
    {
        $this->authorize('export-translations');

        $data = $this->importExportService->export($languageCode);

        return response()->json($data)
            ->header('Content-Disposition', "attachment; filename=\"translations-{$languageCode}.json\"");
    }

    public function exportJsonByGroup(string $languageCode, string $group): JsonResponse
    {
        $this->authorize('export-translations');

        $data = $this->importExportService->export($languageCode, $group);

        return response()->json($data)
            ->header('Content-Disposition', "attachment; filename=\"translations-{$languageCode}-{$group}.json\"");
    }

    public function importJson(Request $request): JsonResponse
    {
        $this->authorize('import-translations');

        $validated = $request->validate([
            'file' => 'required|file|mimes:json',
            'overwrite' => 'boolean',
        ]);

        $contents = file_get_contents($validated['file']->path());
        $data = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid JSON file: ' . json_last_error_msg(),
            ], 422);
        }

        $stats = $this->importExportService->import($data, [
            'overwrite' => $validated['overwrite'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Import completed successfully',
            'data' => $stats,
        ]);
    }
}
```

#### Testing

```php
// tests/Feature/JsonImportExportTest.php
test('can export translations to JSON', function () {
    $language = createLanguage(['code' => 'en']);
    createTranslation([
        'key' => 'home.title',
        'value' => 'Welcome Home',
        'language_id' => $language->id,
        'group' => 'pages',
    ]);

    $response = $this->getJson("/api/translator/import-export/export/en");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'meta' => ['version', 'language', 'exported_at', 'total_translations'],
            'translations',
        ]);
});

test('can import translations from JSON', function () {
    $data = [
        'meta' => [
            'version' => '1.0',
            'language' => [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'direction' => 'ltr',
            ],
        ],
        'translations' => [
            'home' => [
                'title' => ['value' => 'Home', 'group' => 'pages'],
            ],
        ],
    ];

    Storage::fake('local');
    $file = UploadedFile::fake()->createWithContent(
        'translations.json',
        json_encode($data)
    );

    $response = $this->postJson('/api/translator/import-export/import', [
        'file' => $file,
        'overwrite' => true,
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('translations', [
        'key' => 'home.title',
        'value' => 'Home',
    ]);
});
```

#### Acceptance Criteria
- [ ] Can export all translations for a language to JSON
- [ ] Can export translations by group
- [ ] JSON export includes metadata (language info, timestamps)
- [ ] Can import translations from valid JSON file
- [ ] Import supports overwrite and skip modes
- [ ] Import validates JSON structure
- [ ] Import returns statistics (created, updated, skipped, errors)
- [ ] Tests achieve 90%+ coverage

---

### P2-T04-S02: CSV Import/Export

**Estimated Time:** 5-7 hours
**Priority:** P2
**Dependencies:** P2-T04-S01

#### Description
Add CSV format support for easy editing in spreadsheet applications.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\Services;

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Illuminate\Support\Collection;
use League\Csv\Reader;
use League\Csv\Writer;

class CsvImportExportService
{
    /**
     * Export translations to CSV
     */
    public function export(string $languageCode, ?string $group = null): string
    {
        $language = Language::where('code', $languageCode)->firstOrFail();

        $query = Translation::where('language_id', $language->id);

        if ($group) {
            $query->where('group', $group);
        }

        $translations = $query->get();

        $csv = Writer::createFromString();

        // Header row
        $csv->insertOne([
            'Key',
            'Value',
            'Group',
            'Language Code',
            'Created At',
            'Updated At',
        ]);

        // Data rows
        foreach ($translations as $translation) {
            $csv->insertOne([
                $translation->key,
                $translation->value,
                $translation->group,
                $language->code,
                $translation->created_at->toDateTimeString(),
                $translation->updated_at->toDateTimeString(),
            ]);
        }

        return $csv->toString();
    }

    /**
     * Import translations from CSV
     */
    public function import(string $filePath, array $options = []): array
    {
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($csv as $record) {
            try {
                $languageCode = $record['Language Code'];
                $language = Language::where('code', $languageCode)->first();

                if (!$language) {
                    $stats['errors'][] = [
                        'key' => $record['Key'],
                        'error' => "Language '{$languageCode}' not found",
                    ];
                    continue;
                }

                $translation = Translation::where('key', $record['Key'])
                    ->where('language_id', $language->id)
                    ->first();

                if ($translation) {
                    if ($options['overwrite'] ?? true) {
                        $translation->update([
                            'value' => $record['Value'],
                            'group' => $record['Group'] ?? 'general',
                        ]);
                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } else {
                    Translation::create([
                        'key' => $record['Key'],
                        'value' => $record['Value'],
                        'language_id' => $language->id,
                        'group' => $record['Group'] ?? 'general',
                    ]);
                    $stats['created']++;
                }
            } catch (\Exception $e) {
                $stats['errors'][] = [
                    'key' => $record['Key'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $stats;
    }
}
```

#### Testing

```php
test('can export translations to CSV', function () {
    $language = createLanguage(['code' => 'en']);
    createTranslation([
        'key' => 'test.key',
        'value' => 'Test Value',
        'language_id' => $language->id,
    ]);

    $service = app(CsvImportExportService::class);
    $csv = $service->export('en');

    expect($csv)->toContain('Key,Value,Group')
        ->toContain('test.key,Test Value');
});
```

#### Acceptance Criteria
- [ ] Can export to CSV with proper headers
- [ ] CSV includes all translation fields
- [ ] Can import from CSV file
- [ ] Handles special characters and quotes correctly
- [ ] Tests achieve 85%+ coverage

---

### P2-T04-S03: YAML Import/Export

**Estimated Time:** 4-6 hours
**Priority:** P3
**Dependencies:** P2-T04-S01

#### Description
Add YAML format support for nested, human-readable translation files.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\Services;

use Symfony\Component\Yaml\Yaml;

class YamlImportExportService
{
    /**
     * Export translations to YAML
     */
    public function export(string $languageCode, ?string $group = null): string
    {
        $jsonService = app(JsonImportExportService::class);
        $data = $jsonService->export($languageCode, $group);

        return Yaml::dump($data, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    /**
     * Import translations from YAML
     */
    public function import(string $filePath, array $options = []): array
    {
        $data = Yaml::parseFile($filePath);

        $jsonService = app(JsonImportExportService::class);
        return $jsonService->import($data, $options);
    }
}
```

#### Acceptance Criteria
- [ ] Can export to YAML format
- [ ] YAML is properly formatted and indented
- [ ] Can import from YAML files
- [ ] Handles nested structures correctly

---

### P2-T04-S04: PO/POT File Support

**Estimated Time:** 6-8 hours
**Priority:** P3
**Dependencies:** P2-T04-S01

#### Description
Add GNU gettext PO/POT file support for compatibility with professional translation tools.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\Services;

use Gettext\Translations;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;

class PoImportExportService
{
    /**
     * Export translations to PO format
     */
    public function export(string $languageCode, ?string $group = null): string
    {
        $language = Language::where('code', $languageCode)->firstOrFail();

        $query = Translation::where('language_id', $language->id);

        if ($group) {
            $query->where('group', $group);
        }

        $translations = Translations::create('', $languageCode);

        foreach ($query->get() as $translation) {
            $t = $translations->add(null, $translation->key);
            $t->translate($translation->value);
            $t->addComment($translation->group, 'extracted');
        }

        $generator = new PoGenerator();
        return $generator->generateString($translations);
    }

    /**
     * Import translations from PO file
     */
    public function import(string $filePath, string $languageCode, array $options = []): array
    {
        $loader = new PoLoader();
        $translations = $loader->loadFile($filePath);

        $language = Language::where('code', $languageCode)->firstOrFail();

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($translations as $translation) {
            try {
                $key = $translation->getId();
                $value = $translation->getTranslation();
                $group = $translation->getExtractedComments()[0] ?? 'general';

                $existing = Translation::where('key', $key)
                    ->where('language_id', $language->id)
                    ->first();

                if ($existing) {
                    if ($options['overwrite'] ?? true) {
                        $existing->update(['value' => $value]);
                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } else {
                    Translation::create([
                        'key' => $key,
                        'value' => $value,
                        'language_id' => $language->id,
                        'group' => $group,
                    ]);
                    $stats['created']++;
                }
            } catch (\Exception $e) {
                $stats['errors'][] = [
                    'key' => $key ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $stats;
    }
}
```

#### Acceptance Criteria
- [ ] Can export to PO format
- [ ] PO files include metadata (language, project)
- [ ] Can import from PO files
- [ ] Compatible with Poedit and other tools

---

### P2-T04-S05: Bulk Operations UI

**Estimated Time:** 4-6 hours
**Priority:** P3
**Dependencies:** All previous subtasks

#### Description
Create API endpoints for bulk import/export operations.

#### Implementation

```php
// Add to ImportExportController.php

public function bulkExport(Request $request): Response
{
    $validated = $request->validate([
        'languages' => 'required|array',
        'languages.*' => 'exists:languages,code',
        'format' => 'required|in:json,csv,yaml,po',
        'group' => 'nullable|string',
    ]);

    $zip = new ZipArchive();
    $filename = storage_path("exports/translations-" . now()->timestamp . ".zip");

    if ($zip->open($filename, ZipArchive::CREATE) !== true) {
        return response()->json(['error' => 'Could not create zip file'], 500);
    }

    foreach ($validated['languages'] as $languageCode) {
        $service = $this->getServiceForFormat($validated['format']);
        $content = $service->export($languageCode, $validated['group'] ?? null);

        $extension = $validated['format'];
        $zip->addFromString("{$languageCode}.{$extension}", $content);
    }

    $zip->close();

    return response()->download($filename)->deleteFileAfterSend();
}
```

#### Acceptance Criteria
- [ ] Can export multiple languages at once
- [ ] Creates ZIP archive for bulk exports
- [ ] Can select format for bulk operations
- [ ] API properly handles large datasets

---

## Definition of Done

- [ ] All 5 subtasks completed
- [ ] All acceptance criteria met
- [ ] Unit tests written and passing (85%+ coverage)
- [ ] Feature tests written and passing
- [ ] API documentation updated in openapi.json
- [ ] README updated with import/export examples
- [ ] Performance tested with large datasets (10k+ translations)
- [ ] Code reviewed and approved
- [ ] No regressions in existing functionality

---

## Notes

- Consider adding progress callbacks for large imports
- Add validation for file size limits
- Consider queuing large import/export operations
- Add support for partial imports (continue on error)
- Consider adding data transformation hooks for custom formats
