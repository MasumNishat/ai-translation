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
     *
     * @param string $languageCode The language code to export
     * @param string|null $group Optional group to filter by
     * @return array The export data structure
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
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
                'groups' => $translations->pluck('group')->unique()->values()->toArray(),
            ],
            'translations' => $this->formatTranslationsForExport($translations),
        ];
    }

    /**
     * Format translations for export (nested structure)
     *
     * @param Collection $translations
     * @return array
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
     *
     * @param array $data The import data
     * @param array $options Import options (overwrite, create_language)
     * @return array Statistics about the import
     * @throws InvalidArgumentException
     */
    public function import(array $data, array $options = []): array
    {
        $this->validateImportData($data);

        $languageCode = $data['meta']['language']['code'];

        // Create or find language
        if ($options['create_language'] ?? false) {
            $language = Language::firstOrCreate(
                ['code' => $languageCode],
                [
                    'name' => $data['meta']['language']['name'],
                    'native_name' => $data['meta']['language']['native_name'],
                    'direction' => $data['meta']['language']['direction'],
                ]
            );
        } else {
            $language = Language::where('code', $languageCode)->first();

            if (!$language) {
                throw new InvalidArgumentException(
                    "Language '{$languageCode}' not found. Use create_language option to create it."
                );
            }
        }

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
     *
     * @param array $translations
     * @param string $prefix
     * @return array
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
     *
     * @param array $data
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validateImportData(array $data): void
    {
        $validator = Validator::make($data, [
            'meta' => 'required|array',
            'meta.version' => 'required|string',
            'meta.language' => 'required|array',
            'meta.language.code' => 'required|string',
            'meta.language.name' => 'required|string',
            'meta.language.native_name' => 'required|string',
            'meta.language.direction' => 'required|in:ltr,rtl',
            'translations' => 'required|array',
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException(
                'Invalid import data: ' . $validator->errors()->first()
            );
        }
    }

    /**
     * Export all languages to a combined structure
     *
     * @param array|null $languageCodes Specific languages to export, or null for all
     * @param string|null $group Optional group filter
     * @return array
     */
    public function exportAll(?array $languageCodes = null, ?string $group = null): array
    {
        $query = Language::where('is_active', true);

        if ($languageCodes) {
            $query->whereIn('code', $languageCodes);
        }

        $languages = $query->get();

        $exports = [];
        foreach ($languages as $language) {
            $exports[$language->code] = $this->export($language->code, $group);
        }

        return [
            'meta' => [
                'version' => '1.0',
                'exported_at' => now()->toIso8601String(),
                'languages' => $languageCodes ?? $languages->pluck('code')->toArray(),
                'group' => $group,
            ],
            'data' => $exports,
        ];
    }
}
